<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\NewTicket;
use App\Enums\TicketChannel;
use App\Enums\UserRole;
use App\Http\Requests\Support\PostmarkInboundRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The email channel's adapter: turns what Postmark parsed into a ticket, the
 * same way the web form turns a submission into one (§3 — every channel
 * reduces itself to the `NewTicket` DTO).
 *
 * Threading a reply onto an existing ticket, loop protection and the policy
 * on an unknown sender are steps 29 and 30: every message this step sees
 * becomes a new ticket.
 */
class PostmarkInboundController extends Controller
{
    /**
     * Nothing to authorize in the ability sense: the caller is Postmark, not
     * a user, and the credential is checked by the request itself.
     */
    protected static bool $authorizesResources = false;

    public function store(PostmarkInboundRequest $request): Response
    {
        $validated = $request->validated();

        $requester = $this->requesterFor(
            $validated['FromFull']['Name'] ?? $validated['FromFull']['Email'],
            $validated['FromFull']['Email'],
        );

        app(CreateTicket::class)(new NewTicket(
            requester: $requester,
            subject: $this->subjectFor($validated['Subject'] ?? null),
            body: $this->bodyFor($validated['TextBody'] ?? null),
            channel: TicketChannel::Email,
        ));

        return response()->noContent();
    }

    /**
     * `tickets.subject` is a `varchar(255)`: a header longer than that is cut
     * down to fit rather than bounced back to Postmark, which would only
     * retry the same oversized subject forever.
     */
    private function subjectFor(?string $subject): string
    {
        $subject = trim((string) $subject);

        return $subject === '' ? '(nessun oggetto)' : mb_substr($subject, 0, 255);
    }

    /**
     * The description is the first message of the thread (§3), and a thread
     * cannot start with nothing in it: an email with no text body still opens
     * a ticket, it just says so.
     */
    private function bodyFor(?string $body): string
    {
        $body = trim((string) $body);

        return $body === '' ? '(nessun testo)' : $body;
    }

    /**
     * The person behind the address, created if this is the first time they
     * write. Same rule as the web form (§3): the address is the identity, and
     * an account that already exists is not renamed by a new message.
     */
    private function requesterFor(string $name, string $email): User
    {
        $requester = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Str::password()],
        );

        if ($requester->wasRecentlyCreated) {
            $requester->assignRole(UserRole::Requester->value);
        }

        return $requester;
    }
}
