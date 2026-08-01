<?php

use App\Models\Attachment;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\TicketReceived;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Scrapkit\NotificationKit\Domain\Templates\Models\NotificationTemplate;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/**
 * The form as a person fills it in.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function confirmationRequest(array $overrides = []): array
{
    return [
        'name' => 'Anna Rossi',
        'email' => 'anna.rossi@example.com',
        'categoryId' => Category::factory()->create()->id,
        'subject' => 'La stampante non risponde',
        'body' => 'Da stamattina la stampante del secondo piano non stampa.',
        'website' => '',
        ...$overrides,
    ];
}

test('the requester is told their request arrived', function () {
    Notification::fake();

    $this->post(route('support.store'), confirmationRequest());

    $requester = Ticket::sole()->requester;

    Notification::assertSentTo($requester, TicketReceived::class);
});

/*
 * Every notification of this application is queued (§5): an intake that waits
 * for the mail server is an intake that fails when the mail server does.
 */
test('the confirmation is queued and never sent in line', function () {
    expect(new TicketReceived(Ticket::factory()->create()))
        ->toBeInstanceOf(ShouldQueue::class);
});

test('nothing is sent when the request is refused', function () {
    Notification::fake();

    $this->post(route('support.store'), confirmationRequest(['email' => 'anna']));

    Notification::assertNothingSent();
});

test('the honeypot is answered with the same silence as everything else', function () {
    Notification::fake();

    $this->post(route('support.store'), confirmationRequest([
        'website' => 'https://spam.example',
    ]));

    Notification::assertNothingSent();
});

/*
 * The reference is the one thing that has to survive in a mailbox: it is what
 * the requester quotes on the phone, and what the inbound email of step 29
 * threads on.
 */
test('the reference is in the subject, where a mailbox shows it', function () {
    $ticket = Ticket::factory()->create();

    $message = (new TicketReceived($ticket))->toMail($ticket->requester);

    expect($message->subject)->toContain($ticket->reference);
});

test('the confirmation carries the link that opens the ticket', function () {
    $this->freezeTime();

    $ticket = Ticket::factory()->create();

    $rendered = (string) (new TicketReceived($ticket))->toMail($ticket->requester)->render();

    expect($rendered)->toContain(e(TicketReceived::linkTo($ticket)))
        ->and(TicketReceived::linkTo($ticket))->toContain('signature=');
});

test('the template is synced from code', function () {
    $this->artisan('notification-kit:sync')->assertSuccessful();

    $template = NotificationTemplate::query()->where('key', 'tickets.received')->first();

    expect($template)->not->toBeNull()
        ->and($template->type->value)->toBe('email')
        ->and(collect($template->placeholders)->pluck('key')->all())
        ->toBe(['requester.name', 'ticket.reference', 'ticket.subject', 'app.name', 'action.url']);
});

test('the confirmation uses the content edited in the database', function () {
    $this->artisan('notification-kit:sync')->assertSuccessful();

    NotificationTemplate::query()->where('key', 'tickets.received')->firstOrFail()->update([
        'subject' => 'Richiesta {{ ticket.reference }} ricevuta',
        'body' => "Ciao **{{ requester.name }}**,\n\n[Segui la richiesta]({{ action.url }})",
    ]);

    $ticket = Ticket::factory()->create();
    $ticket->requester->update(['name' => 'Anna Rossi']);

    $message = (new TicketReceived($ticket))->toMail($ticket->requester->fresh());

    expect($message->subject)->toBe('Richiesta '.$ticket->reference.' ricevuta')
        ->and((string) $message->render())->toContain('Anna Rossi')
        ->and((string) $message->render())->toContain('Segui la richiesta');
});

/*
 * The link is the only key to the ticket, so it is the signature that has to
 * hold: without it the address is an id anybody can count up to.
 */
test('the ticket does not open without a signature', function () {
    $ticket = Ticket::factory()->create();

    $this->get(route('support.ticket.show', $ticket))->assertForbidden();
});

test('the signed link opens the ticket in read only', function () {
    $ticket = Ticket::factory()->nuovo()->create(['subject' => 'La stampante non risponde']);

    $this->get(TicketReceived::linkTo($ticket))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('support/ticket')
            ->where('ticket.reference', $ticket->reference)
            ->where('ticket.subject', 'La stampante non risponde')
            ->where('ticket.status', $ticket->status->value)
        );
});

/*
 * A note is written for the team and the requester never reads one (§3), not
 * even on their own ticket: a thread that lets one through has published it.
 */
test('the internal notes of the team never reach the requester', function () {
    $ticket = Ticket::factory()->create();

    TicketMessage::factory()->for($ticket)->create(['body' => 'La risposta pubblica']);
    TicketMessage::factory()->for($ticket)->interna()->create(['body' => 'La nota interna']);

    $this->get(TicketReceived::linkTo($ticket))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('ticket.messages', fn ($messages) => collect($messages)->pluck('body')->contains('La risposta pubblica')
                && ! collect($messages)->pluck('body')->contains('La nota interna'))
        );
});

test('the files sent with the request come back as signed links', function () {
    $ticket = Ticket::factory()->create();
    $message = TicketMessage::factory()->for($ticket)->create();
    $attachment = Attachment::factory()->for($message, 'message')->create();

    $this->get(TicketReceived::linkTo($ticket))
        ->assertInertia(fn ($page) => $page
            ->where('ticket.messages.0.attachments.0.name', $attachment->original_name)
            ->where('ticket.messages.0.attachments.0.url', fn ($url) => str_contains((string) $url, 'signature='))
        );
});

test('a link that has expired no longer opens the ticket', function () {
    $ticket = Ticket::factory()->create();

    $link = TicketReceived::linkTo($ticket);

    $this->travel(TicketReceived::LINK_DAYS + 1)->days();

    $this->get($link)->assertForbidden();
});

/*
 * The signature covers the id, so a link cannot be edited into another ticket:
 * a requester sees their own request and nobody else's (§3).
 */
test('a signature is worth for the ticket it was issued for', function () {
    $mine = Ticket::factory()->create();
    $someoneElse = Ticket::factory()->create();

    $link = str_replace(
        (string) $mine->getKey(),
        (string) $someoneElse->getKey(),
        TicketReceived::linkTo($mine),
    );

    $this->get($link)->assertForbidden();
});

test('the ticket carries nothing about who is working on it', function () {
    $ticket = Ticket::factory()->assegnato()->create();

    $this->get(TicketReceived::linkTo($ticket))
        ->assertInertia(fn ($page) => $page
            ->missing('ticket.assignee')
            ->missing('ticket.team')
            ->missing('ticket.category')
        );
});

/*
 * The link is the key and the session is not: a requester never logs in (§3),
 * so who happens to be authenticated says nothing about who may read this.
 */
test('the link is what opens the ticket, not a session', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(User::factory()->requester()->create());

    $this->get(TicketReceived::linkTo($ticket))->assertOk();
});
