<?php

use App\Models\Ticket;
use App\Notifications\TicketResolved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Scrapkit\NotificationKit\Domain\Templates\Models\NotificationTemplate;

/*
 * The endpoint the console actually calls (step 35), so the trigger is
 * exercised the way it fires in production and not just the domain event on
 * its own.
 */
function resolveTicket(Ticket $ticket): void
{
    test()->actingAs(userWithPermissions(['ticket:view', 'ticket:update']))
        ->patch(route('tickets.update-status', $ticket), ['status' => 'risolto'])
        ->assertRedirect();
}

test('the requester is told their ticket is resolved', function () {
    Notification::fake();

    $ticket = Ticket::factory()->inLavorazione()->create();

    resolveTicket($ticket);

    Notification::assertSentTo($ticket->requester, TicketResolved::class);
});

test('nothing is sent when the passage is refused', function () {
    Notification::fake();

    // `nuovo` does not admit `risolto` (§4): the request never reaches the
    // transition, so nothing should reach the requester either.
    $ticket = Ticket::factory()->nuovo()->create();

    $this->actingAs(userWithPermissions(['ticket:view', 'ticket:update']))
        ->patch(route('tickets.update-status', $ticket), ['status' => 'risolto'])
        ->assertInvalid(['status']);

    Notification::assertNothingSent();
});

test('reopening a resolved ticket sends nothing', function () {
    Notification::fake();

    $ticket = Ticket::factory()->risolto()->create();

    $this->actingAs(userWithPermissions(['ticket:view', 'ticket:update']))
        ->patch(route('tickets.update-status', $ticket), ['status' => 'in_lavorazione'])
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('the notification is queued and never sent in line', function () {
    expect(new TicketResolved(Ticket::factory()->create()))
        ->toBeInstanceOf(ShouldQueue::class);
});

test('the reference is in the subject, where a mailbox shows it', function () {
    $ticket = Ticket::factory()->create();

    $message = (new TicketResolved($ticket))->toMail($ticket->requester);

    expect($message->subject)->toContain($ticket->reference);
});

test('the notification carries the link that opens the ticket', function () {
    $this->freezeTime();

    $ticket = Ticket::factory()->create();

    $rendered = (string) (new TicketResolved($ticket))->toMail($ticket->requester)->render();

    expect($rendered)->toContain(e(TicketResolved::linkTo($ticket)))
        ->and(TicketResolved::linkTo($ticket))->toContain('signature=');
});

test('the template is synced from code', function () {
    $this->artisan('notification-kit:sync')->assertSuccessful();

    $template = NotificationTemplate::query()->where('key', 'tickets.resolved')->first();

    expect($template)->not->toBeNull()
        ->and($template->type->value)->toBe('email')
        ->and(collect($template->placeholders)->pluck('key')->all())
        ->toBe(['requester.name', 'ticket.reference', 'ticket.subject', 'app.name', 'action.url']);
});

test('the notification uses the content edited in the database', function () {
    $this->artisan('notification-kit:sync')->assertSuccessful();

    NotificationTemplate::query()->where('key', 'tickets.resolved')->firstOrFail()->update([
        'subject' => 'Richiesta {{ ticket.reference }} risolta',
        'body' => "Ciao **{{ requester.name }}**,\n\n[Rivedi la richiesta]({{ action.url }})",
    ]);

    $ticket = Ticket::factory()->create();
    $ticket->requester->update(['name' => 'Anna Rossi']);

    $message = (new TicketResolved($ticket))->toMail($ticket->requester->fresh());

    expect($message->subject)->toBe('Richiesta '.$ticket->reference.' risolta')
        ->and((string) $message->render())->toContain('Anna Rossi')
        ->and((string) $message->render())->toContain('Rivedi la richiesta');
});
