<?php

namespace App\Actions\Tickets;

use App\Enums\TicketChannel;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * What an inbound email becomes: a reply threaded onto the ticket it
 * belongs to, a ticket of its own, or nothing at all (roadmap step 29).
 *
 * Three things stand between a parsed email and the domain, in the order
 * they are cheapest to rule out: an autoresponder loop, a webhook Postmark
 * has already delivered once, and a sender writing faster than any person
 * does. None of the three is a request to lose — they are requests this
 * step has already answered, on the delivery that answered them.
 */
class ReceiveInboundEmail
{
    /**
     * How many messages one address may land in a minute (§5). Two
     * autorisponditori answering each other reach this in seconds; a person
     * writing three emails in a row never does.
     */
    public const MESSAGES_PER_SENDER_PER_MINUTE = 5;

    public function __invoke(InboundEmail $email): ?Ticket
    {
        if ($email->autoSubmitted) {
            return null;
        }

        if ($this->alreadyReceived($email)) {
            return null;
        }

        if (! $this->withinRateLimit($email)) {
            return null;
        }

        $ticket = $this->ticketToThread($email);

        if ($ticket !== null) {
            return app(ReplyFromRequester::class)(new RequesterReply(
                ticket: $ticket,
                requester: $ticket->requester,
                body: $email->body,
                channel: TicketChannel::Email,
                externalMessageId: $email->externalMessageId,
                attachments: $email->attachments,
            ));
        }

        return app(CreateTicket::class)(new NewTicket(
            requester: $this->requesterFor($email),
            subject: $email->subject,
            body: $email->body,
            channel: TicketChannel::Email,
            externalMessageId: $email->externalMessageId,
            attachments: $email->attachments,
        ));
    }

    /**
     * Whether this exact email has already become a message. The column is
     * `unique` for this reason (§4): a provider delivering the same webhook
     * twice must not append the same email to a thread twice, or open the
     * same ticket twice.
     */
    private function alreadyReceived(InboundEmail $email): bool
    {
        if ($email->externalMessageId === null) {
            return false;
        }

        return TicketMessage::query()
            ->where('external_message_id', $email->externalMessageId)
            ->exists();
    }

    /**
     * Whether this address is still under the cap. A duplicate delivery
     * never reaches here — {@see alreadyReceived} answers it first — so this
     * only ever spends the budget of a message that is actually new.
     */
    private function withinRateLimit(InboundEmail $email): bool
    {
        return RateLimiter::attempt(
            'inbound-email:'.$email->fromEmail,
            self::MESSAGES_PER_SENDER_PER_MINUTE,
            fn (): bool => true,
            60,
        );
    }

    /**
     * The ticket this email threads onto, if it may. A reference in the
     * subject or a header pointing at a message already in the system is
     * only half the story — either one is a value the sender chose to send
     * back, not proof of who they are.  The policy on an unknown sender
     * (§5) is the other half: the match only holds if the address writing
     * now is the address the ticket already belongs to. A stranger quoting
     * somebody else's reference does not get to write into their thread —
     * their email opens one of its own instead.
     */
    private function ticketToThread(InboundEmail $email): ?Ticket
    {
        $ticket = $this->ticketFromSubject($email->subject) ?? $this->ticketFromHeaders($email);

        if ($ticket === null) {
            return null;
        }

        return $ticket->requester->email === $email->fromEmail ? $ticket : null;
    }

    private function ticketFromSubject(string $subject): ?Ticket
    {
        $prefix = preg_quote(Ticket::REFERENCE_PREFIX, '/');

        if (! preg_match('/'.$prefix.'\d+/', $subject, $matches)) {
            return null;
        }

        return Ticket::query()->where('reference', $matches[0])->first();
    }

    private function ticketFromHeaders(InboundEmail $email): ?Ticket
    {
        $candidates = array_values(array_filter([$email->inReplyTo, ...$email->references]));

        if ($candidates === []) {
            return null;
        }

        return TicketMessage::query()
            ->whereIn('external_message_id', $candidates)
            ->first()
            ?->ticket;
    }

    /**
     * The person behind the address, created if this is the first time they
     * write. Same rule as every other channel (§3): the address is the
     * identity, and an account that already exists is not renamed by a new
     * message.
     */
    private function requesterFor(InboundEmail $email): User
    {
        $requester = User::query()->firstOrCreate(
            ['email' => $email->fromEmail],
            ['name' => $email->fromName, 'password' => Str::password()],
        );

        if ($requester->wasRecentlyCreated) {
            $requester->assignRole(UserRole::Requester->value);
        }

        return $requester;
    }
}
