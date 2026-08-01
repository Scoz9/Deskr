<?php

use App\Models\Attachment;
use App\Models\Category;
use App\Models\Ticket;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\assertDatabaseCount;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    // The private disk is faked, so no test ever writes into the real one.
    Storage::fake(Attachment::DISK);
});

/**
 * The form as a person fills it in, with the files they picked.
 *
 * @param  array<int, UploadedFile>  $attachments
 * @return array<string, mixed>
 */
function requestWithAttachments(array $attachments): array
{
    return [
        'name' => 'Anna Rossi',
        'email' => 'anna.rossi@example.com',
        'categoryId' => Category::factory()->create()->id,
        'subject' => 'La stampante non risponde',
        'body' => 'Allego lo screenshot dell errore.',
        'website' => '',
        'attachments' => $attachments,
    ];
}

test('a file picked on the form arrives with the first message of the thread', function () {
    $this->post(route('support.store'), requestWithAttachments([
        UploadedFile::fake()->image('errore.png'),
    ]))->assertSessionHasNoErrors();

    $message = Ticket::sole()->messages->first();

    expect($message->attachments)->toHaveCount(1);

    $attachment = $message->attachments->first();

    expect($attachment->original_name)->toBe('errore.png')
        ->and($attachment->mime_type)->toBe('image/png')
        ->and($attachment->size)->toBeGreaterThan(0)
        ->and($attachment->disk)->toBe(Attachment::DISK);
});

/*
 * The bytes live on the private disk (§8), under a name the application chose:
 * the one the sender picked travels on the row, so that a crafted file name can
 * never decide where the file lands.
 */
test('the bytes are written to the private disk under a name of our own', function () {
    $this->post(route('support.store'), requestWithAttachments([
        UploadedFile::fake()->image('../../etc/passwd.png'),
    ]));

    $attachment = Attachment::sole();

    Storage::disk(Attachment::DISK)->assertExists($attachment->path);

    expect($attachment->path)->toStartWith(Attachment::DIRECTORY.'/')
        ->and($attachment->path)->not->toContain('..')
        ->and($attachment->path)->not->toContain('passwd');
});

test('a request with no files at all is still a request', function () {
    $this->post(route('support.store'), requestWithAttachments([]))
        ->assertSessionHasNoErrors();

    expect(Ticket::sole()->messages->first()->attachments)->toHaveCount(0);
});

test('every file picked comes in, up to the limit', function () {
    $this->post(route('support.store'), requestWithAttachments(
        array_map(
            fn (int $index): UploadedFile => UploadedFile::fake()->image("errore-{$index}.png"),
            range(1, Attachment::MAX_PER_MESSAGE),
        ),
    ))->assertSessionHasNoErrors();

    assertDatabaseCount('attachments', Attachment::MAX_PER_MESSAGE);
});

test('one file more than the limit is refused', function () {
    $this->post(route('support.store'), requestWithAttachments(
        array_map(
            fn (int $index): UploadedFile => UploadedFile::fake()->image("errore-{$index}.png"),
            range(1, Attachment::MAX_PER_MESSAGE + 1),
        ),
    ))->assertSessionHasErrors('attachments');

    assertDatabaseCount('tickets', 0);
});

/*
 * The whitelist is what a helpdesk actually receives — a screenshot, a
 * document, a log. Everything else is refused by what the file *is* and not by
 * what it is called: an extension is whatever the sender typed.
 */
test('a type nobody sends to a helpdesk is refused', function () {
    $this->post(route('support.store'), requestWithAttachments([
        UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
    ]))->assertSessionHasErrors('attachments.0');

    assertDatabaseCount('tickets', 0);
    assertDatabaseCount('attachments', 0);
});

test('a forbidden file renamed as an allowed one is still refused', function () {
    $this->post(route('support.store'), requestWithAttachments([
        UploadedFile::fake()->create('innocuo.png', 10, 'application/x-php'),
    ]))->assertSessionHasErrors('attachments.0');

    assertDatabaseCount('attachments', 0);
});

test('a file heavier than the helpdesk accepts is refused', function () {
    $this->post(route('support.store'), requestWithAttachments([
        UploadedFile::fake()->create(
            'enorme.pdf',
            Attachment::MAX_KILOBYTES + 1,
            'application/pdf',
        ),
    ]))->assertSessionHasErrors('attachments.0');

    assertDatabaseCount('attachments', 0);
});

/*
 * A refused request must not leave its files behind: what is written and what
 * is recorded are one thing.
 */
test('nothing is written when the request is refused', function () {
    $this->post(route('support.store'), requestWithAttachments([
        UploadedFile::fake()->image('errore.png'),
        UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
    ]))->assertSessionHasErrors();

    expect(Storage::disk(Attachment::DISK)->allFiles())->toBe([]);
});

test('an attachment is downloaded only through a signed link', function () {
    $this->post(route('support.store'), requestWithAttachments([
        UploadedFile::fake()->image('errore.png'),
    ]));

    $attachment = Attachment::sole();

    $this->get(route('attachments.show', $attachment))->assertForbidden();

    $this->get(URL::signedRoute('attachments.show', ['attachment' => $attachment]))
        ->assertOk()
        ->assertDownload('errore.png');
});

test('a link that has expired no longer opens the file', function () {
    $attachment = Attachment::factory()->create();

    $link = URL::temporarySignedRoute(
        'attachments.show',
        now()->addMinute(),
        ['attachment' => $attachment],
    );

    $this->travel(2)->minutes();

    $this->get($link)->assertForbidden();
});

/*
 * A row whose file is gone is a broken link, not an empty download: the private
 * disk throws, and what reaches whoever clicked has to be a plain 404.
 */
test('a row whose file has gone missing answers not found', function () {
    $attachment = Attachment::factory()->create();

    $this->get(URL::signedRoute('attachments.show', ['attachment' => $attachment]))
        ->assertNotFound();
});
