<?php

use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    config([
        'services.postmark.inbound.username' => 'postmark',
        'services.postmark.inbound.password' => 'secret',
    ]);

    // The private disk is faked, so no test ever writes into the real one.
    Storage::fake(Attachment::DISK);
});

/**
 * A minimal, genuinely valid PNG — real bytes, so the whitelist sniffs it
 * for what it is instead of trusting a made up `ContentType`.
 */
function fakePngContent(): string
{
    ob_start();
    imagepng(imagecreatetruecolor(2, 2));

    return ob_get_clean();
}

/**
 * A Postmark attachment entry, base64-encoded the way the payload carries
 * one.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function postmarkAttachment(array $overrides = []): array
{
    return [
        'Name' => 'errore.png',
        'Content' => base64_encode(fakePngContent()),
        'ContentType' => 'image/png',
        ...$overrides,
    ];
}

/**
 * A payload shaped the way Postmark's inbound webhook sends one.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function postmarkPayload(array $overrides = []): array
{
    return [
        'FromFull' => [
            'Email' => 'anna.rossi@example.com',
            'Name' => 'Anna Rossi',
        ],
        'Subject' => 'La stampante non risponde',
        'TextBody' => 'Da stamattina la stampante del secondo piano non stampa.',
        'Headers' => [
            ['Name' => 'Message-ID', 'Value' => '<msg-1@mail.example.com>'],
        ],
        ...$overrides,
    ];
}

function postToInboundWebhook(array $payload, ?string $username = 'postmark', ?string $password = 'secret')
{
    $request = test()->withBasicAuth($username ?? '', $password ?? '');

    return $request->postJson(route('webhooks.postmark.inbound'), $payload);
}

/*
 * The endpoint that opens the door has to lock it: the caller is Postmark, not
 * a browser, so the credential is the Basic Auth the request itself verifies.
 */
test('a request without the right credentials is refused', function () {
    postToInboundWebhook(postmarkPayload(), password: 'wrong')->assertForbidden();

    assertDatabaseCount('tickets', 0);
});

test('a request is refused when nobody configured the credentials', function () {
    config([
        'services.postmark.inbound.username' => null,
        'services.postmark.inbound.password' => null,
    ]);

    postToInboundWebhook(postmarkPayload())->assertForbidden();

    assertDatabaseCount('tickets', 0);
});

test('a well authenticated email opens a ticket on the email channel', function () {
    postToInboundWebhook(postmarkPayload())->assertNoContent();

    $ticket = Ticket::sole();

    expect($ticket->channel)->toBe(TicketChannel::Email)
        ->and($ticket->subject)->toBe('La stampante non risponde')
        ->and($ticket->requester->email)->toBe('anna.rossi@example.com');

    assertDatabaseHas('ticket_messages', [
        'ticket_id' => $ticket->id,
        'body' => 'Da stamattina la stampante del secondo piano non stampa.',
        'is_internal' => false,
    ]);
});

/*
 * The address is the identity (§3), same as the web form: an email from
 * somebody who has already written lands on their existing account.
 */
test('an address that already writes lands on its existing account', function () {
    $requester = User::factory()->requester()->create(['email' => 'anna.rossi@example.com']);

    postToInboundWebhook(postmarkPayload());

    expect(Ticket::sole()->requester_id)->toBe($requester->id)
        ->and(User::query()->where('email', 'anna.rossi@example.com')->count())->toBe(1);
});

test('a first time address gets a requester account', function () {
    postToInboundWebhook(postmarkPayload());

    $requester = User::query()->where('email', 'anna.rossi@example.com')->sole();

    expect($requester->hasRole(UserRole::Requester->value))->toBeTrue();
});

/*
 * An inbound email carries no category (§3): it lands unclassified in the
 * pool, exactly like the web form's own unclassified case.
 */
test('the email lands unclassified, with no category and no team', function () {
    postToInboundWebhook(postmarkPayload());

    $ticket = Ticket::sole();

    expect($ticket->category_id)->toBeNull()
        ->and($ticket->team_id)->toBeNull();
});

/*
 * A ticket does not lose the request over a header nobody filled in: the
 * subject is filled in rather than the message being dropped.
 */
test('a missing subject does not lose the request', function () {
    postToInboundWebhook(postmarkPayload(['Subject' => null]))->assertNoContent();

    expect(Ticket::sole()->subject)->not->toBe('');
});

test('a subject longer than the column is cut down to fit, not bounced', function () {
    $longSubject = str_repeat('a', 300);

    postToInboundWebhook(postmarkPayload(['Subject' => $longSubject]))->assertNoContent();

    expect(mb_strlen(Ticket::sole()->subject))->toBeLessThanOrEqual(255);
});

test('a missing text body does not lose the request', function () {
    postToInboundWebhook(postmarkPayload(['TextBody' => null]))->assertNoContent();

    expect(Ticket::sole()->messages->first()->body)->not->toBe('');
});

test('an email with no sender address is refused', function () {
    postToInboundWebhook(postmarkPayload(['FromFull' => ['Email' => '', 'Name' => 'Anna']]))
        ->assertUnprocessable();

    assertDatabaseCount('tickets', 0);
});

test('the id in the Message-ID header lands on the message it opened', function () {
    postToInboundWebhook(postmarkPayload());

    assertDatabaseHas('ticket_messages', ['external_message_id' => '<msg-1@mail.example.com>']);
});

/*
 * The reference in the subject is the threading key, and the sender must be
 * the ticket's own requester (§5): the two together are what tells a real
 * reply apart from anybody quoting the same reference.
 */
test('a reply carrying the reference in its subject threads through the real webhook', function () {
    $requester = User::factory()->requester()->create(['email' => 'anna.rossi@example.com']);
    $ticket = Ticket::factory()->inAttesa()->for($requester, 'requester')->create();

    postToInboundWebhook(postmarkPayload([
        'Subject' => 'Re: ['.$ticket->reference.'] La stampante non risponde',
        'Headers' => [['Name' => 'Message-ID', 'Value' => '<reply-1@mail.example.com>']],
    ]))->assertNoContent();

    expect($ticket->fresh()->status)->toBe(TicketStatus::InLavorazione);

    assertDatabaseCount('tickets', 1);
});

test('In-Reply-To threads through the real webhook', function () {
    $requester = User::factory()->requester()->create(['email' => 'anna.rossi@example.com']);
    $ticket = Ticket::factory()->inAttesa()->for($requester, 'requester')->create();
    TicketMessage::factory()->for($ticket)->create(['external_message_id' => '<original@mail.example.com>']);

    postToInboundWebhook(postmarkPayload([
        'Subject' => 'Re: la mia richiesta',
        'Headers' => [
            ['Name' => 'Message-ID', 'Value' => '<reply-1@mail.example.com>'],
            ['Name' => 'In-Reply-To', 'Value' => '<original@mail.example.com>'],
        ],
    ]))->assertNoContent();

    expect($ticket->fresh()->status)->toBe(TicketStatus::InLavorazione);

    assertDatabaseCount('tickets', 1);
});

test('an autosubmitted email is accepted but dropped', function () {
    postToInboundWebhook(postmarkPayload([
        'Headers' => [
            ['Name' => 'Message-ID', 'Value' => '<auto-1@mail.example.com>'],
            ['Name' => 'Auto-Submitted', 'Value' => 'auto-replied'],
        ],
    ]))->assertNoContent();

    assertDatabaseCount('tickets', 0);
});

test('the same webhook delivered twice opens only one ticket', function () {
    $payload = postmarkPayload();

    postToInboundWebhook($payload)->assertNoContent();
    postToInboundWebhook($payload)->assertNoContent();

    assertDatabaseCount('tickets', 1);
});

/*
 * `StrippedTextReply` is Postmark's own signature and quoted-text removal
 * (step 30): used whenever it is there, rather than a heuristic this
 * application would have to invent.
 */
test('the stripped reply is used over the raw text body when Postmark sends one', function () {
    postToInboundWebhook(postmarkPayload([
        'TextBody' => "Succede ancora.\n\nOn Mon, Anna wrote:\n> tutto il testo citato\n--\nAnna Rossi",
        'StrippedTextReply' => 'Succede ancora.',
    ]))->assertNoContent();

    expect(Ticket::sole()->messages->first()->body)->toBe('Succede ancora.');
});

test('the raw text body is used when there is nothing to strip', function () {
    postToInboundWebhook(postmarkPayload(['StrippedTextReply' => null]))->assertNoContent();

    expect(Ticket::sole()->messages->first()->body)
        ->toBe('Da stamattina la stampante del secondo piano non stampa.');
});

test('a real attachment lands on the first message', function () {
    postToInboundWebhook(postmarkPayload([
        'Attachments' => [postmarkAttachment()],
    ]))->assertNoContent();

    $attachment = Ticket::sole()->messages->first()->attachments->sole();

    expect($attachment->original_name)->toBe('errore.png')
        ->and($attachment->mime_type)->toBe('image/png')
        ->and($attachment->disk)->toBe(Attachment::DISK);

    Storage::disk(Attachment::DISK)->assertExists($attachment->path);
});

/*
 * A `ContentID` marks a part embedded in the body — the logo of a signature,
 * not a file the sender meant to send. Stripping the signature text (above)
 * is only half the job if its image still turns into an attachment.
 */
test('an inline image embedded in the signature is not treated as an attachment', function () {
    postToInboundWebhook(postmarkPayload([
        'Attachments' => [postmarkAttachment(['ContentID' => 'logo@mail.example.com'])],
    ]))->assertNoContent();

    assertDatabaseCount('attachments', 0);
});

/*
 * The whitelist asks what the file *is*, not what the email claims it is:
 * the same rule the web form's `mimetypes` check follows.
 */
test('a type nobody sends to a helpdesk is left out, not bounced', function () {
    postToInboundWebhook(postmarkPayload([
        'Attachments' => [postmarkAttachment([
            'Name' => 'script.php',
            'Content' => base64_encode('<?php echo 1; ?>'),
            'ContentType' => 'image/png',
        ])],
    ]))->assertNoContent();

    assertDatabaseCount('tickets', 1);
    assertDatabaseCount('attachments', 0);
});

test('an attachment heavier than the helpdesk accepts is left out, not bounced', function () {
    postToInboundWebhook(postmarkPayload([
        'Attachments' => [postmarkAttachment([
            'Content' => base64_encode(str_repeat('a', (Attachment::MAX_KILOBYTES + 1) * 1024)),
        ])],
    ]))->assertNoContent();

    assertDatabaseCount('attachments', 0);
});

test('only the first attachments up to the limit are kept', function () {
    postToInboundWebhook(postmarkPayload([
        'Attachments' => array_map(
            fn (int $index): array => postmarkAttachment(['Name' => "errore-{$index}.png"]),
            range(1, Attachment::MAX_PER_MESSAGE + 2),
        ),
    ]))->assertNoContent();

    assertDatabaseCount('attachments', Attachment::MAX_PER_MESSAGE);
});

test('an attachment on a threaded reply lands on the message it appends', function () {
    $requester = User::factory()->requester()->create(['email' => 'anna.rossi@example.com']);
    $ticket = Ticket::factory()->inAttesa()->for($requester, 'requester')->create();

    postToInboundWebhook(postmarkPayload([
        'Subject' => 'Re: ['.$ticket->reference.'] La stampante non risponde',
        'Headers' => [['Name' => 'Message-ID', 'Value' => '<reply-1@mail.example.com>']],
        'Attachments' => [postmarkAttachment()],
    ]))->assertNoContent();

    assertDatabaseCount('tickets', 1);

    $message = $ticket->fresh()->messages->last();

    expect($message->attachments)->toHaveCount(1);
});
