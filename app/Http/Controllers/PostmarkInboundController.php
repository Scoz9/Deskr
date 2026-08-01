<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\InboundEmail;
use App\Actions\Tickets\NewAttachment;
use App\Actions\Tickets\NewTicket;
use App\Actions\Tickets\ReceiveInboundEmail;
use App\Http\Requests\Support\PostmarkInboundRequest;
use App\Models\Attachment;
use finfo;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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
            body: $this->bodyFor($validated['TextBody'] ?? null, $validated['StrippedTextReply'] ?? null),
            externalMessageId: $headers->get('message-id'),
            inReplyTo: $headers->get('in-reply-to'),
            references: $this->referencesFrom($headers->get('references')),
            autoSubmitted: $this->isAutoSubmitted($headers),
            attachments: $this->attachmentsFrom($validated['Attachments'] ?? []),
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
     *
     * `StrippedTextReply` is Postmark's own signature and quoted-text
     * removal (step 30) — the reply with everything below it already cut,
     * rather than a heuristic this application would have to invent and
     * maintain. It is only ever set when Postmark found a reply to strip, so
     * a first message with nothing to strip falls back to `TextBody`.
     */
    private function bodyFor(?string $body, ?string $strippedTextReply): string
    {
        $body = filled($strippedTextReply) ? trim($strippedTextReply) : trim((string) $body);

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

    /**
     * The real attachments of the email, written to disk and described for
     * the domain (step 30) — everything the whitelist and the size cap
     * refuse is left out rather than losing the whole message over one file.
     *
     * @param  list<array{Name?: string, Content?: string, ContentType?: string, ContentID?: string}>  $attachments
     * @return list<NewAttachment>
     */
    private function attachmentsFrom(array $attachments): array
    {
        return (new Collection($attachments))
            // A `ContentID` marks a part embedded in the body — the logo of a
            // signature, not a file the sender meant to attach. Removing the
            // signature (above) is only half the job if its image still
            // shows up as an attachment.
            ->reject(fn (array $attachment): bool => filled($attachment['ContentID'] ?? null))
            ->map(fn (array $attachment): ?NewAttachment => $this->storeAttachment($attachment))
            ->filter()
            ->take(Attachment::MAX_PER_MESSAGE)
            ->values()
            ->all();
    }

    /**
     * @param  array{Name?: string, Content?: string, ContentType?: string}  $attachment
     */
    private function storeAttachment(array $attachment): ?NewAttachment
    {
        $content = base64_decode((string) ($attachment['Content'] ?? ''), true);

        if ($content === false || $content === '' || strlen($content) > Attachment::MAX_KILOBYTES * 1024) {
            return null;
        }

        // Sniffed from the bytes and not trusted from `ContentType`: the
        // whitelist asks what the file *is*, not what the email that carried
        // it claims — the same rule the web form's `mimetypes` check follows.
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($content);

        if ($mimeType === false || ! in_array($mimeType, Attachment::ALLOWED_MIME_TYPES, true)) {
            return null;
        }

        $path = Attachment::DIRECTORY.'/'.Str::random(40);

        Storage::disk(Attachment::DISK)->put($path, $content);

        $name = trim((string) ($attachment['Name'] ?? ''));

        return new NewAttachment(
            disk: Attachment::DISK,
            path: $path,
            originalName: $name === '' ? 'allegato' : $name,
            mimeType: $mimeType,
            size: strlen($content),
        );
    }
}
