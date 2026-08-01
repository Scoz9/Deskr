<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\InboundEmail;
use App\Actions\Tickets\NewTicket;
use App\Actions\Tickets\ReceiveInboundEmail;
use App\Http\Requests\Support\PostmarkInboundRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The email channel's adapter: turns what Postmark parsed into the
 * {@see InboundEmail} DTO the domain reads, the same way the web form turns
 * a submission into {@see NewTicket} for its own
 * channel (§3 — every channel reduces itself to a DTO, never a second way
 * into the domain).
 *
 * What the email becomes — a new ticket, a threaded reply, or nothing —
 * is not decided here: {@see ReceiveInboundEmail} decides.
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
        $headers = $this->headersFrom($validated['Headers'] ?? []);

        app(ReceiveInboundEmail::class)(new InboundEmail(
            fromEmail: $validated['FromFull']['Email'],
            fromName: $this->fromNameFor($validated['FromFull']),
            subject: $this->subjectFor($validated['Subject'] ?? null),
            body: $this->bodyFor($validated['TextBody'] ?? null),
            externalMessageId: $headers->get('message-id'),
            inReplyTo: $headers->get('in-reply-to'),
            references: $this->referencesFrom($headers->get('references')),
            autoSubmitted: $this->isAutoSubmitted($headers),
        ));

        return response()->noContent();
    }

    /**
     * @param  array{Email: string, Name?: string|null}  $fromFull
     */
    private function fromNameFor(array $fromFull): string
    {
        $name = trim((string) ($fromFull['Name'] ?? ''));

        return $name === '' ? $fromFull['Email'] : $name;
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
     * cannot start with nothing in it: an email with no text body still
     * opens a ticket, it just says so.
     */
    private function bodyFor(?string $body): string
    {
        $body = trim((string) $body);

        return $body === '' ? '(nessun testo)' : $body;
    }

    /**
     * Postmark's own headers as a lower-cased lookup, so a name is matched
     * the same way regardless of how the sending client capitalised it —
     * `Message-ID` and `message-id` are the same header.
     *
     * @param  list<array{Name?: string, Value?: string}>  $headers
     * @return Collection<string, string>
     */
    private function headersFrom(array $headers): Collection
    {
        return (new Collection($headers))
            ->filter(fn (array $header): bool => filled($header['Name'] ?? null))
            ->mapWithKeys(fn (array $header): array => [
                Str::lower($header['Name']) => (string) ($header['Value'] ?? ''),
            ]);
    }

    /**
     * `References` is a whitespace-separated chain of every message id in
     * the thread, oldest first — checked when `In-Reply-To` alone does not
     * resolve to a ticket.
     *
     * @return list<string>
     */
    private function referencesFrom(?string $references): array
    {
        if ($references === null || trim($references) === '') {
            return [];
        }

        return preg_split('/\s+/', trim($references)) ?: [];
    }

    /**
     * RFC 3834: an autoresponder identifies itself with `Auto-Submitted` set
     * to anything but `no`. Two of them answering each other is the loop
     * this stops before it ever reaches the domain (§5).
     *
     * @param  Collection<string, string>  $headers
     */
    private function isAutoSubmitted(Collection $headers): bool
    {
        $value = $headers->get('auto-submitted');

        return $value !== null && Str::lower(trim($value)) !== 'no';
    }
}
