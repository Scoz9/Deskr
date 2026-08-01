<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Scrapkit\NotificationKit\Contracts\Manageable;
use Scrapkit\NotificationKit\Domain\Templates\DataTransferObjects\PlaceholderDefinition;
use Scrapkit\NotificationKit\Domain\Templates\DataTransferObjects\TemplateDefinition;
use Scrapkit\NotificationKit\Domain\Templates\Enums\TemplateType;
use Scrapkit\NotificationKit\Notifications\Concerns\HasManagedContent;

/**
 * The receipt of a request: it tells whoever wrote that the helpdesk has it,
 * and gives them the two things they leave with — the reference, and the link
 * that opens the ticket.
 *
 * Queued, like every notification of this application (§5): an intake that
 * waits for the mail server is an intake that fails when the mail server does,
 * and a ticket that exists is worth more than a confirmation that arrives on
 * the same second.
 */
class TicketReceived extends Notification implements Manageable, ShouldQueue
{
    use HasManagedContent, Queueable;

    /**
     * How long the link stays good, in days (§5). It is reusable until then,
     * and the page that hands out a fresh one when it has expired arrives with
     * the portal of step 26.
     */
    public const LINK_DAYS = 7;

    public function __construct(public Ticket $ticket) {}

    /**
     * The link that opens the ticket.
     *
     * It lives here because this is what hands the key out: nothing else in the
     * application gives access to a ticket without an account, and the portal of
     * step 26 will take it over when it owns the whole flow. The signature
     * covers the id, so a link cannot be edited into somebody else's request.
     */
    public static function linkTo(Ticket $ticket): string
    {
        return URL::temporarySignedRoute(
            'support.ticket.show',
            now()->addDays(self::LINK_DAYS),
            ['ticket' => $ticket->getKey()],
        );
    }

    /**
     * The editable content behind this confirmation.
     */
    public static function template(): TemplateDefinition
    {
        return new TemplateDefinition(
            key: 'tickets.received',
            type: TemplateType::Email,
            name: 'Ticket received',
            description: 'Sent to the requester when a request becomes a ticket, with its reference and the link that opens it.',
            defaultSubject: '[{{ ticket.reference }}] {{ ticket.subject }}',
            defaultBody: <<<'MARKDOWN'
                Hi {{ requester.name }},

                We have your request and it is on our list as **{{ ticket.reference }}**.

                [Follow your request]({{ action.url }})

                Keep the reference at hand: it is what identifies your request when you write or call.
                MARKDOWN,
            placeholders: [
                new PlaceholderDefinition('requester.name', 'Name of whoever asked for help', 'Mario Rossi'),
                new PlaceholderDefinition('ticket.reference', 'Public reference of the ticket', 'DSK-000123'),
                new PlaceholderDefinition('ticket.subject', 'Subject of the request', 'La stampante non risponde'),
                new PlaceholderDefinition('app.name', 'Application name', config('app.name')),
                new PlaceholderDefinition('action.url', 'Link that opens the ticket', 'https://example.test/assistenza/ticket/1?signature=...'),
            ],
            sampleData: [
                'requester' => ['name' => 'Mario Rossi'],
                'ticket' => ['reference' => 'DSK-000123', 'subject' => 'La stampante non risponde'],
                'app' => ['name' => config('app.name')],
                'action' => ['url' => 'https://example.test/assistenza/ticket/1?signature=...'],
            ],
        );
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * The reference goes in the subject because that is where a mailbox shows
     * it: it is what the requester quotes on the phone, and what the inbound
     * email of step 29 threads on.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $content = $this->renderManaged([
            'requester' => ['name' => $notifiable->name],
            'ticket' => [
                'reference' => $this->ticket->reference,
                'subject' => $this->ticket->subject,
            ],
            'app' => ['name' => config('app.name')],
            'action' => ['url' => self::linkTo($this->ticket)],
        ]);

        return (new MailMessage)
            ->subject($content->subject ?? sprintf('[%s] %s', $this->ticket->reference, $this->ticket->subject))
            ->markdown('notification-kit::mail.managed', ['renderedBody' => $content->bodyHtml]);
    }
}
