<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\NewAttachment;
use App\Actions\Tickets\NewTicket;
use App\Enums\TicketChannel;
use App\Enums\UserRole;
use App\Http\Requests\Support\SupportRequestStoreRequest;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\User;
use App\Notifications\TicketReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public intake: the only page of the application that answers somebody
 * without an account, because a requester never registers (§3).
 *
 * The controller is the web adapter of the channel — it turns a form into the
 * `NewTicket` DTO and hands it to the Action. What a ticket is and where it
 * gets routed is not decided here.
 */
class SupportRequestController extends Controller
{
    /**
     * No resource authorization here, and it is not an omission: the intake
     * answers a guest by design, so there is neither a policy to consult nor a
     * user to consult it about. What defends this door is the rate limit on the
     * route and the honeypot in the form.
     */
    protected static bool $authorizesResources = false;

    /**
     * How many tickets one address may keep open at the same time (§5).
     */
    public const OPEN_TICKETS_PER_EMAIL = 10;

    /**
     * How many requests one address may send in an hour (§5).
     */
    public const SUBMISSIONS_PER_EMAIL_PER_HOUR = 5;

    /**
     * Show the form, with the categories it has to offer.
     *
     * The category is what routes the ticket to a team, so it is the one thing
     * the form cannot make up on its own. Nothing else of the category travels
     * to a public page: the team behind it is how the helpdesk is organised
     * inside, and whoever is asking for help has no business reading it.
     *
     * The reference comes from the session because it is the receipt of the
     * request just sent: it shows once and is gone on the next visit, which is
     * all the trace the requester has until the confirmation email of step 25.
     */
    public function create(): Response
    {
        return Inertia::render('support/create', [
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'reference' => session('reference'),
            // The whitelist and the limits are decided in one place and sent
            // to the page: a second copy written in TypeScript is the one that
            // would go stale the day the list changes.
            'attachmentLimits' => [
                'maxFiles' => Attachment::MAX_PER_MESSAGE,
                'maxBytes' => Attachment::MAX_KILOBYTES * 1024,
                'mimeTypes' => Attachment::ALLOWED_MIME_TYPES,
            ],
        ]);
    }

    /**
     * Turn the form into a ticket.
     *
     * A filled honeypot is answered exactly like everything else: a script that
     * can tell the refusal from the success has learnt how to get around the
     * trap. Nothing is written, and nobody is told.
     */
    public function store(SupportRequestStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (filled($validated['website'] ?? null)) {
            return to_route('support.create');
        }

        $requester = $this->requesterFor($validated['name'], $validated['email']);
        $category = Category::query()->whereKey($validated['categoryId'])->firstOrFail();

        $ticket = app(CreateTicket::class)(new NewTicket(
            requester: $requester,
            subject: $validated['subject'],
            body: $validated['body'],
            channel: TicketChannel::Web,
            category: $category,
            attachments: $this->storeAttachments($request->file('attachments', [])),
        ));

        // Queued, like every notification (§5): the ticket exists, and the
        // receipt of it can take the time the mail server takes.
        $requester->notify(new TicketReceived($ticket));

        return to_route('support.create')->with('reference', $ticket->reference);
    }

    /**
     * Write the picked files to the private disk and describe them for the
     * Action.
     *
     * The stored name is generated, never the one that came in: a file name is
     * input like any other, and one that decides where it lands is a file name
     * that can land anywhere. What the sender called it travels on the row, and
     * comes back only as the name of the download.
     *
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     * @return list<NewAttachment>
     */
    private function storeAttachments(array|UploadedFile|null $files): array
    {
        $files = $files instanceof UploadedFile ? [$files] : ($files ?? []);

        return array_map(
            fn (UploadedFile $file): NewAttachment => new NewAttachment(
                disk: Attachment::DISK,
                path: (string) $file->store(Attachment::DIRECTORY, Attachment::DISK),
                originalName: $file->getClientOriginalName(),
                mimeType: (string) $file->getMimeType(),
                size: (int) $file->getSize(),
            ),
            array_values($files),
        );
    }

    /**
     * The person behind the address, created if this is the first time they
     * write.
     *
     * The name of an account that already exists is left alone: writing to the
     * helpdesk opens a ticket, it does not rename somebody else. The password
     * is random and never sent anywhere — the portal of step 26 is reached by
     * magic link, and registration is off (§3).
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
