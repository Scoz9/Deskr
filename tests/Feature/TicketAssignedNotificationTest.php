<?php

use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\TicketAssignment;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Tickets\TicketActor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Scrapkit\NotificationKit\Domain\Templates\Models\NotificationTemplate;

use function Pest\Laravel\assertDatabaseCount;

/*
 * "Assegna a me" of step 35 is the only door that reaches the assignment
 * out of the pool today, and it is always the agent finding out by their
 * own click — an email confirming the button they just pressed would be
 * noise, not news.
 */
test('claiming a ticket yourself sends no notification', function () {
    Notification::fake();
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->nuovo()->create();

    $this->actingAs($agent)
        ->post(route('tickets.assign-to-me', $ticket))
        ->assertRedirect();

    Notification::assertNothingSent();
});

/*
 * Exercised at the Action layer, the way `assignTicket($ticket, $agent,
 * TicketActor::user($admin))` already is in AssignTicketTest: nothing in
 * the console hands a ticket to somebody else yet, but the domain already
 * admits it, and the notification has to be correct for the day it does.
 */
test('the agent a ticket is handed to out of the pool is told', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->nuovo()->create();

    app(AssignTicket::class)(new TicketAssignment(
        ticket: $ticket,
        assignee: $agent,
        actor: TicketActor::user($admin),
    ));

    Notification::assertSentTo($agent, TicketAssigned::class);
});

test('a ticket already out of the pool changing hands sends nothing here', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->assegnato()->create();
    $other = User::factory()->agent()->create();

    app(AssignTicket::class)(new TicketAssignment(
        ticket: $ticket,
        assignee: $other,
        actor: TicketActor::user($admin),
    ));

    assertDatabaseCount('ticket_events', 1);
    Notification::assertNothingSent();
});

test('nothing is sent when the ticket already belongs to the agent', function () {
    Notification::fake();
    $ticket = Ticket::factory()->inLavorazione()->create();
    $assignee = $ticket->assignee;

    app(AssignTicket::class)(new TicketAssignment(
        ticket: $ticket,
        assignee: $assignee,
        actor: TicketActor::user($assignee),
    ));

    Notification::assertNothingSent();
});

test('the notification is queued and never sent in line', function () {
    expect(new TicketAssigned(Ticket::factory()->create()))
        ->toBeInstanceOf(ShouldQueue::class);
});

test('the reference is in the subject, where a mailbox shows it', function () {
    $ticket = Ticket::factory()->create();
    $agent = User::factory()->agent()->create();

    $message = (new TicketAssigned($ticket))->toMail($agent);

    expect($message->subject)->toContain($ticket->reference);
});

/*
 * The agent is a console account and not a portal visitor (§3): the link is
 * the ordinary authenticated route to the detail, not a signature nobody
 * asked for.
 */
test('the notification links to the console, not a signed portal link', function () {
    $ticket = Ticket::factory()->create();
    $agent = User::factory()->agent()->create();

    $rendered = (string) (new TicketAssigned($ticket))->toMail($agent)->render();

    expect($rendered)->toContain(e(route('tickets.show', $ticket)))
        ->and($rendered)->not->toContain('signature=');
});

test('the template is synced from code', function () {
    $this->artisan('notification-kit:sync')->assertSuccessful();

    $template = NotificationTemplate::query()->where('key', 'tickets.assigned')->first();

    expect($template)->not->toBeNull()
        ->and($template->type->value)->toBe('email')
        ->and(collect($template->placeholders)->pluck('key')->all())
        ->toBe(['assignee.name', 'ticket.reference', 'ticket.subject', 'app.name', 'action.url']);
});

test('the notification uses the content edited in the database', function () {
    $this->artisan('notification-kit:sync')->assertSuccessful();

    NotificationTemplate::query()->where('key', 'tickets.assigned')->firstOrFail()->update([
        'subject' => 'Ticket {{ ticket.reference }} assegnato a te',
        'body' => "Ciao **{{ assignee.name }}**,\n\n[Apri il ticket]({{ action.url }})",
    ]);

    $ticket = Ticket::factory()->create();
    $agent = User::factory()->agent()->create(['name' => 'Luca Bianchi']);

    $message = (new TicketAssigned($ticket))->toMail($agent);

    expect($message->subject)->toBe('Ticket '.$ticket->reference.' assegnato a te')
        ->and((string) $message->render())->toContain('Luca Bianchi')
        ->and((string) $message->render())->toContain('Apri il ticket');
});
