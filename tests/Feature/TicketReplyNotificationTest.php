<?php

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketReplied;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Scrapkit\NotificationKit\Domain\Templates\Models\NotificationTemplate;

test('the requester is told about a public reply from the console', function () {
    Notification::fake();
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->assegnato()->create();

    $this->actingAs($agent)
        ->post(route('tickets.messages.store', $ticket), ['body' => 'Abbiamo controllato il log.'])
        ->assertRedirect();

    Notification::assertSentTo($ticket->requester, TicketReplied::class);
});

/*
 * A note is written for the team and the requester never reads one (§3),
 * so telling them one exists would be worse than saying nothing.
 */
test('an internal note tells the requester nothing', function () {
    Notification::fake();
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->assegnato()->create();

    $this->actingAs($agent)
        ->post(route('tickets.messages.store', $ticket), [
            'body' => 'Da verificare con il fornitore.',
            'is_internal' => true,
        ])
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('nothing is sent when the reply is refused', function () {
    Notification::fake();
    $ticket = Ticket::factory()->create();

    $this->actingAs(userWithPermissions(['ticketMessage:create']))
        ->post(route('tickets.messages.store', $ticket), ['body' => ''])
        ->assertInvalid(['body']);

    Notification::assertNothingSent();
});

test('the notification is queued and never sent in line', function () {
    expect(new TicketReplied(Ticket::factory()->create()))
        ->toBeInstanceOf(ShouldQueue::class);
});

test('the reference is in the subject, where a mailbox shows it', function () {
    $ticket = Ticket::factory()->create();

    $message = (new TicketReplied($ticket))->toMail($ticket->requester);

    expect($message->subject)->toContain($ticket->reference);
});

test('the notification carries the link that opens the ticket', function () {
    $this->freezeTime();

    $ticket = Ticket::factory()->create();

    $rendered = (string) (new TicketReplied($ticket))->toMail($ticket->requester)->render();

    expect($rendered)->toContain(e(TicketReplied::linkTo($ticket)))
        ->and(TicketReplied::linkTo($ticket))->toContain('signature=');
});

test('the template is synced from code', function () {
    $this->artisan('notification-kit:sync')->assertSuccessful();

    $template = NotificationTemplate::query()->where('key', 'tickets.replied')->first();

    expect($template)->not->toBeNull()
        ->and($template->type->value)->toBe('email')
        ->and(collect($template->placeholders)->pluck('key')->all())
        ->toBe(['requester.name', 'ticket.reference', 'ticket.subject', 'app.name', 'action.url']);
});

test('the notification uses the content edited in the database', function () {
    $this->artisan('notification-kit:sync')->assertSuccessful();

    NotificationTemplate::query()->where('key', 'tickets.replied')->firstOrFail()->update([
        'subject' => 'Nuova risposta su {{ ticket.reference }}',
        'body' => "Ciao **{{ requester.name }}**,\n\n[Leggi la risposta]({{ action.url }})",
    ]);

    $ticket = Ticket::factory()->create();
    $ticket->requester->update(['name' => 'Anna Rossi']);

    $message = (new TicketReplied($ticket))->toMail($ticket->requester->fresh());

    expect($message->subject)->toBe('Nuova risposta su '.$ticket->reference)
        ->and((string) $message->render())->toContain('Anna Rossi')
        ->and((string) $message->render())->toContain('Leggi la risposta');
});
