<?php

use App\Http\Controllers\PortalController;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\PortalLink;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Scrapkit\NotificationKit\Domain\Templates\Models\NotificationTemplate;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/**
 * Somebody who has already written to the helpdesk, which is the only way an
 * account exists at all (§3).
 */
function requesterWithTickets(int $tickets = 1, string $email = 'anna.rossi@example.com'): User
{
    $requester = User::factory()->requester()->create(['email' => $email]);

    Ticket::factory()->count($tickets)->for($requester, 'requester')->create();

    return $requester;
}

test('anybody can ask for the link to their tickets', function () {
    $this->get(route('portal.request'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('portal/access'));
});

test('a known address is sent the link', function () {
    Notification::fake();

    $requester = requesterWithTickets();

    $this->post(route('portal.link'), ['email' => $requester->email])
        ->assertRedirect(route('portal.request'));

    Notification::assertSentTo($requester, PortalLink::class);
});

test('the link is queued like every other notification', function () {
    expect(new PortalLink)->toBeInstanceOf(ShouldQueue::class);
});

/*
 * The answer never says whether the address is one the helpdesk knows: a form
 * that tells them apart is a way to find out who is a customer, and it is open
 * to the internet.
 */
test('an address nobody has is answered exactly like a known one', function () {
    Notification::fake();

    $requester = requesterWithTickets();

    $known = $this->post(route('portal.link'), ['email' => $requester->email]);
    $unknown = $this->post(route('portal.link'), ['email' => 'nessuno@example.com']);

    expect($unknown->status())->toBe($known->status())
        ->and($unknown->headers->get('Location'))->toBe($known->headers->get('Location'))
        ->and(session('linkRequested'))->toBeTrue();

    Notification::assertSentTimes(PortalLink::class, 1);
});

test('an address that is not one is refused', function () {
    $this->post(route('portal.link'), ['email' => 'anna'])
        ->assertSessionHasErrors('email');
});

test('the same address cannot ask for link after link', function () {
    Notification::fake();

    $requester = requesterWithTickets();

    foreach (range(1, PortalController::LINKS_PER_EMAIL_PER_HOUR) as $attempt) {
        $this->post(route('portal.link'), ['email' => $requester->email])->assertRedirect();
    }

    $this->post(route('portal.link'), ['email' => $requester->email])->assertStatus(429);
});

test('the link opens the portal and keeps it open', function () {
    $requester = requesterWithTickets();

    $this->get(PortalLink::linkTo($requester))
        ->assertRedirect(route('portal.index'));

    expect(auth()->id())->toBe($requester->id);

    $this->get(route('portal.index'))->assertOk();
});

/*
 * A link that has run out is not a wall: whoever clicked it is somebody the
 * helpdesk wants to hear from, and the page they land on is the one that hands
 * out a fresh link (§5).
 */
test('an expired link lands on the page that gives out a new one', function () {
    $requester = requesterWithTickets();

    $link = PortalLink::linkTo($requester);

    $this->travel(PortalLink::LINK_DAYS + 1)->days();

    $this->get($link)
        ->assertRedirect(route('portal.request'))
        ->assertSessionHas('linkExpired', true);

    expect(auth()->check())->toBeFalse();
});

test('a link nobody signed opens nothing', function () {
    $requester = requesterWithTickets();

    $this->get(route('portal.enter', $requester))
        ->assertRedirect(route('portal.request'));

    expect(auth()->check())->toBeFalse();
});

test('the link stays good until it expires', function () {
    $requester = requesterWithTickets();

    $link = PortalLink::linkTo($requester);

    $this->get($link)->assertRedirect(route('portal.index'));
    $this->post(route('portal.leave'));

    $this->get($link)->assertRedirect(route('portal.index'));
});

test('the portal is closed to whoever has not come through a link', function () {
    $this->get(route('portal.index'))->assertRedirect(route('portal.request'));
});

/*
 * The one filter between two customers: without global scoping underneath,
 * every forgotten one is a leak (§3). The colleague at the same company is
 * somebody else, and so is everybody else.
 */
test('the portal lists the tickets of whoever opened it and nobody else', function () {
    $requester = requesterWithTickets(2);
    $somebodyElse = requesterWithTickets(3, 'mario.bianchi@example.com');

    $this->get(PortalLink::linkTo($requester));

    $this->get(route('portal.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/index')
            ->has('tickets', 2)
            ->where('tickets', fn ($tickets) => collect($tickets)->pluck('reference')->diff(
                $requester->tickets()->pluck('reference'),
            )->isEmpty())
        );

    expect($somebodyElse->tickets()->count())->toBe(3);
});

test('the list carries what a person needs to tell one request from another', function () {
    $requester = User::factory()->requester()->create();
    $ticket = Ticket::factory()->nuovo()->for($requester, 'requester')->create([
        'subject' => 'La stampante non risponde',
    ]);

    $this->get(PortalLink::linkTo($requester));

    $this->get(route('portal.index'))
        ->assertInertia(fn ($page) => $page
            ->where('tickets.0.reference', $ticket->reference)
            ->where('tickets.0.subject', 'La stampante non risponde')
            ->where('tickets.0.status', $ticket->status->value)
        );
});

test('from the portal a ticket opens without a signature', function () {
    $requester = requesterWithTickets();
    $ticket = $requester->tickets()->sole();

    $this->get(PortalLink::linkTo($requester));

    $this->get(route('support.ticket.show', $ticket))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('support/ticket'));
});

test('the portal of one requester does not open the ticket of another', function () {
    $requester = requesterWithTickets();
    $somebodyElse = requesterWithTickets(1, 'mario.bianchi@example.com');

    $this->get(PortalLink::linkTo($requester));

    $this->get(route('support.ticket.show', $somebodyElse->tickets()->sole()))
        ->assertForbidden();
});

test('leaving the portal closes it', function () {
    $requester = requesterWithTickets();

    $this->get(PortalLink::linkTo($requester));

    $this->post(route('portal.leave'))->assertRedirect(route('portal.request'));

    expect(auth()->check())->toBeFalse();

    $this->get(route('portal.index'))->assertRedirect(route('portal.request'));
});

test('the template of the link is synced from code', function () {
    $this->artisan('notification-kit:sync')->assertSuccessful();

    expect(
        NotificationTemplate::query()
            ->where('key', 'portal.link')
            ->exists(),
    )->toBeTrue();
});
