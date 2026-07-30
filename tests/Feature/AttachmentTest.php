<?php

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

test('the factory persists an attachment hanging from a message', function () {
    $attachment = Attachment::factory()->create();

    expect($attachment->disk)->toBe(Attachment::DISK)
        ->and($attachment->path)->toStartWith(Attachment::DIRECTORY.'/')
        ->and($attachment->original_name)->toBeString()->not->toBeEmpty()
        ->and($attachment->mime_type)->toBeString()->not->toBeEmpty()
        ->and($attachment->size)->toBeInt()->toBeGreaterThan(0);

    $this->assertDatabaseHas('attachments', [
        'id' => $attachment->id,
        'ticket_message_id' => $attachment->ticket_message_id,
        'disk' => Attachment::DISK,
    ]);
});

test('an attachment belongs to the message it came in with', function () {
    $attachment = Attachment::factory()->create();

    expect($attachment->message)->toBeInstanceOf(TicketMessage::class);
});

test('a message carries the attachments that came in with it', function () {
    $message = TicketMessage::factory()->create();

    Attachment::factory()->count(2)->for($message, 'message')->create();

    expect($message->attachments)->toHaveCount(2)
        ->and($message->attachments->first())->toBeInstanceOf(Attachment::class);
});

/**
 * Attachments are not polimorphic: every one of them arrives with a message
 * (§4), so there is no state in which one exists on its own.
 */
test('an attachment cannot exist without a message to hang from', function () {
    expect(fn () => Attachment::factory()->create(['ticket_message_id' => null]))
        ->toThrow(QueryException::class);
});

test('deleting a message takes its attachments with it', function () {
    $attachment = Attachment::factory()->create();

    $attachment->message->delete();

    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
});

test('deleting a ticket takes the attachments of its thread with it', function () {
    $attachment = Attachment::factory()->create();

    $attachment->message->ticket->delete();

    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
});

test('an attachment cannot exist without the file it points at', function (string $column) {
    expect(fn () => Attachment::factory()->create([$column => null]))
        ->toThrow(QueryException::class);
})->with(['disk', 'path', 'original_name', 'mime_type', 'size']);

/**
 * Two rows pointing at the same file would delete it from under each other.
 */
test('the same file cannot be attached twice on the same disk', function () {
    $attachment = Attachment::factory()->create();

    expect(fn () => Attachment::factory()->create([
        'disk' => $attachment->disk,
        'path' => $attachment->path,
    ]))->toThrow(QueryException::class);
});

/**
 * The path is unique per disk, not on its own: the disk the file was written to
 * is stored on the row, so files already on a disk keep resolving after the
 * default one changes.
 */
test('the same path on another disk is another file', function () {
    $attachment = Attachment::factory()->create();

    $other = Attachment::factory()->create([
        'disk' => 's3',
        'path' => $attachment->path,
    ]);

    expect($other->path)->toBe($attachment->path)
        ->and($other->disk)->not->toBe($attachment->disk);
});

test('the size is stored in bytes as an integer', function () {
    $attachment = Attachment::factory()->create(['size' => 2048]);

    expect($attachment->refresh()->size)->toBe(2048);
});

/**
 * Attachments carry what a requester wrote to the helpdesk and are never served
 * from the web root: the disk is private, and the download goes through a
 * signed route and the policy (§8).
 */
test('attachments are written to a private disk, never the public one', function () {
    $disk = config('filesystems.disks.'.Attachment::DISK);

    expect(Attachment::DISK)->not->toBe('public')
        ->and($disk)->toBeArray()
        ->and($disk['visibility'] ?? null)->toBe('private')
        ->and($disk['url'] ?? null)->toBeNull()
        ->and($disk['root'])->toBe(storage_path('app/private/attachments'));
});

test('a file written to the attachments disk lands outside the web root', function () {
    $disk = Storage::disk(Attachment::DISK);

    $disk->put('deskr-private-check.txt', 'contents');

    try {
        $absolute = $disk->path('deskr-private-check.txt');

        expect(is_file($absolute))->toBeTrue()
            ->and($absolute)->toStartWith(storage_path('app/private'))
            ->and(str_starts_with($absolute, public_path()))->toBeFalse();
    } finally {
        $disk->delete('deskr-private-check.txt');
    }
});

/**
 * The name comes from outside: using it to build the path would let a sender
 * choose where the file lands and overwrite somebody else's attachment. The
 * storage pipeline generates the stored name, the original one is only shown.
 */
test('the stored path is generated, never the name the sender chose', function () {
    $attachment = Attachment::factory()->create(['original_name' => 'fattura.pdf']);

    expect($attachment->path)->toStartWith(Attachment::DIRECTORY.'/')
        ->and($attachment->path)->not->toContain('fattura')
        ->and($attachment->original_name)->toBe('fattura.pdf');
});

test('the image state is the screenshot a requester attaches to the form', function () {
    $attachment = Attachment::factory()->immagine()->create();

    expect($attachment->mime_type)->toBe('image/png')
        ->and($attachment->original_name)->toEndWith('.png')
        ->and($attachment->path)->toEndWith('.png');
});

test('the factory hangs the attachment on the thread of a single ticket', function () {
    $ticket = Ticket::factory()->create();
    $message = TicketMessage::factory()->for($ticket)->create();

    $attachment = Attachment::factory()->for($message, 'message')->create();

    expect($attachment->message->ticket_id)->toBe($ticket->id);
});
