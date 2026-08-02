<?php

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\assertDatabaseCount;

beforeEach(function () {
    // The private disk is faked, so no test ever writes into the real one.
    Storage::fake(Attachment::DISK);
});

test('guests are redirected to the login page', function () {
    $ticket = Ticket::factory()->create();

    $this->post(route('tickets.messages.store', $ticket), ['body' => 'Ciao.'])
        ->assertRedirect(route('login'));
});

test('posting a message is refused without ticketMessage:create', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(userWithPermissions([]))
        ->post(route('tickets.messages.store', $ticket), ['body' => 'Ciao.'])
        ->assertForbidden();

    assertDatabaseCount('ticket_messages', 0);
});

test('an agent posts a public reply that lands on the thread', function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->assegnato()->create();

    $this->actingAs($agent)
        ->post(route('tickets.messages.store', $ticket), ['body' => 'Abbiamo controllato il log.'])
        ->assertRedirect();

    $message = $ticket->fresh()->messages->last();

    expect($message->body)->toBe('Abbiamo controllato il log.')
        ->and($message->is_internal)->toBeFalse()
        ->and($message->author_id)->toBe($agent->id);
});

test('an agent writes an internal note instead of a reply', function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->assegnato()->create();

    $this->actingAs($agent)
        ->post(route('tickets.messages.store', $ticket), [
            'body' => 'Da verificare con il fornitore prima di rispondere.',
            'is_internal' => true,
        ])
        ->assertRedirect();

    $message = $ticket->fresh()->messages->last();

    expect($message->is_internal)->toBeTrue();
});

test('an empty body is refused', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(userWithPermissions(['ticketMessage:create']))
        ->post(route('tickets.messages.store', $ticket), ['body' => ''])
        ->assertInvalid(['body']);

    assertDatabaseCount('ticket_messages', 0);
});

test('a file picked in the composer arrives with the message', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(userWithPermissions(['ticketMessage:create']))
        ->post(route('tickets.messages.store', $ticket), [
            'body' => 'Allego lo screenshot.',
            'attachments' => [UploadedFile::fake()->image('errore.png')],
        ])
        ->assertRedirect();

    $message = $ticket->fresh()->messages->last();

    expect($message->attachments)->toHaveCount(1);

    $attachment = $message->attachments->first();

    expect($attachment->original_name)->toBe('errore.png')
        ->and($attachment->disk)->toBe(Attachment::DISK);

    Storage::disk(Attachment::DISK)->assertExists($attachment->path);
});

test('one attachment more than the limit is refused', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(userWithPermissions(['ticketMessage:create']))
        ->post(route('tickets.messages.store', $ticket), [
            'body' => 'Troppi allegati.',
            'attachments' => array_map(
                fn (int $index): UploadedFile => UploadedFile::fake()->image("file-{$index}.png"),
                range(1, Attachment::MAX_PER_MESSAGE + 1),
            ),
        ])
        ->assertInvalid(['attachments']);

    assertDatabaseCount('ticket_messages', 0);
});

test('a type nobody sends to a helpdesk is refused', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(userWithPermissions(['ticketMessage:create']))
        ->post(route('tickets.messages.store', $ticket), [
            'body' => 'Ecco lo script.',
            'attachments' => [UploadedFile::fake()->create('script.php', 10, 'application/x-php')],
        ])
        ->assertInvalid(['attachments.0']);

    assertDatabaseCount('ticket_messages', 0);
});
