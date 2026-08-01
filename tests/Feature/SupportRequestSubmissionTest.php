<?php

use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Controllers\SupportRequestController;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    // The requester role is seeded, not invented here: a user who lands in the
    // application without it would be a user no policy of step 21 can place.
    $this->seed(RoleSeeder::class);
});

/**
 * The form as a person fills it in, with only the field under test moved.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function supportRequest(array $overrides = []): array
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

/*
 * Nobody registers to ask for help (§3): the address typed in the form is the
 * identity, and the account is a consequence of the request.
 */
test('an address nobody has seen before becomes a requester', function () {
    $this->post(route('support.store'), supportRequest([
        'name' => 'Anna Rossi',
        'email' => 'anna.rossi@example.com',
    ]));

    $requester = User::query()->where('email', 'anna.rossi@example.com')->sole();

    expect($requester->name)->toBe('Anna Rossi')
        ->and($requester->hasRole(UserRole::Requester->value))->toBeTrue()
        ->and($requester->organization_id)->toBeNull()
        ->and($requester->email_verified_at)->toBeNull();
});

test('an address already known opens the ticket on the account it already has', function () {
    $existing = User::factory()->requester()->create([
        'email' => 'anna.rossi@example.com',
        'name' => 'Anna Rossi',
    ]);

    $this->post(route('support.store'), supportRequest([
        'email' => 'anna.rossi@example.com',
    ]));

    assertDatabaseCount('users', 1);
    expect(Ticket::sole()->requester_id)->toBe($existing->id);
});

/*
 * The name is what the account already says, not what the form last typed:
 * whoever writes to the helpdesk gets to open a ticket, not to rename somebody
 * else's account.
 */
test('a known address does not get renamed by whoever types it', function () {
    $existing = User::factory()->requester()->create([
        'email' => 'anna.rossi@example.com',
        'name' => 'Anna Rossi',
    ]);

    $this->post(route('support.store'), supportRequest([
        'email' => 'anna.rossi@example.com',
        'name' => 'Qualcun Altro',
    ]));

    expect($existing->fresh()->name)->toBe('Anna Rossi');
});

test('the request becomes a ticket that came in from the web', function () {
    $category = Category::factory()->create();

    $this->post(route('support.store'), supportRequest([
        'categoryId' => $category->id,
        'subject' => 'La stampante non risponde',
    ]));

    $ticket = Ticket::sole();

    expect($ticket->subject)->toBe('La stampante non risponde')
        ->and($ticket->status)->toBe(TicketStatus::Nuovo)
        ->and($ticket->channel)->toBe(TicketChannel::Web)
        ->and($ticket->category_id)->toBe($category->id)
        ->and($ticket->team_id)->toBe($category->team_id)
        ->and($ticket->assignee_id)->toBeNull();
});

test('the description typed in the form is the first message of the thread', function () {
    $this->post(route('support.store'), supportRequest([
        'body' => 'Da stamattina la stampante del secondo piano non stampa.',
    ]));

    $ticket = Ticket::sole();

    expect($ticket->messages)->toHaveCount(1);

    $message = $ticket->messages->first();

    expect($message->body)->toBe('Da stamattina la stampante del secondo piano non stampa.')
        ->and($message->author_id)->toBe($ticket->requester_id)
        ->and($message->is_internal)->toBeFalse();
});

/*
 * The reference is the only thing the requester can hold on to until the
 * confirmation email of step 25 and the portal of step 26 exist.
 */
test('the person is sent back to the form with the reference of their ticket', function () {
    $this->post(route('support.store'), supportRequest())
        ->assertRedirect(route('support.create'))
        ->assertSessionHas('reference', fn (string $reference): bool => $reference === Ticket::sole()->reference);
});

test('the form shows the reference once and forgets it', function () {
    $this->post(route('support.store'), supportRequest());

    $this->get(route('support.create'))
        ->assertInertia(fn ($page) => $page->where('reference', Ticket::sole()->reference));

    $this->get(route('support.create'))
        ->assertInertia(fn ($page) => $page->where('reference', null));
});

/*
 * The bait has to answer exactly like the real thing: a script that can tell
 * the refusal from the success has learnt how to get around it.
 */
test('a filled honeypot is answered like every other request and creates nothing', function () {
    $this->post(route('support.store'), supportRequest([
        'website' => 'https://spam.example',
    ]))
        ->assertRedirect(route('support.create'))
        ->assertSessionHasNoErrors();

    assertDatabaseCount('tickets', 0);
    assertDatabaseCount('users', 0);
});

test('an empty request is refused field by field', function () {
    $this->post(route('support.store'), [])
        ->assertSessionHasErrors(['name', 'email', 'categoryId', 'subject', 'body']);

    assertDatabaseCount('tickets', 0);
});

test('an address that is not one is refused', function () {
    $this->post(route('support.store'), supportRequest(['email' => 'anna']))
        ->assertSessionHasErrors('email');

    assertDatabaseCount('tickets', 0);
});

/*
 * The category is what routes the ticket to a team, and the form is a public
 * one: the id it sends is untrusted input until the database says it exists.
 */
test('a category nobody has is refused', function () {
    $this->post(route('support.store'), supportRequest(['categoryId' => 9999]))
        ->assertSessionHasErrors('categoryId');

    assertDatabaseCount('tickets', 0);
});

/*
 * The two defences the §5 asks for next to the honeypot, and that only now have
 * an address to count on.
 */
test('the same address cannot fire request after request', function () {
    foreach (range(1, SupportRequestController::SUBMISSIONS_PER_EMAIL_PER_HOUR) as $attempt) {
        $this->post(route('support.store'), supportRequest())->assertRedirect();
    }

    $this->post(route('support.store'), supportRequest())->assertStatus(429);
});

test('the limit follows the address and not whoever is at the keyboard', function () {
    foreach (range(1, SupportRequestController::SUBMISSIONS_PER_EMAIL_PER_HOUR) as $attempt) {
        $this->post(route('support.store'), supportRequest())->assertRedirect();
    }

    $this->post(route('support.store'), supportRequest([
        'email' => 'mario.bianchi@example.com',
    ]))->assertRedirect();
});

test('an address with too many tickets already open is told so instead of opening another', function () {
    $requester = User::factory()->requester()->create(['email' => 'anna.rossi@example.com']);

    Ticket::factory()
        ->count(SupportRequestController::OPEN_TICKETS_PER_EMAIL)
        ->nuovo()
        ->for($requester, 'requester')
        ->create();

    $this->post(route('support.store'), supportRequest(['email' => 'anna.rossi@example.com']))
        ->assertSessionHasErrors('email');

    expect(Ticket::query()->count())->toBe(SupportRequestController::OPEN_TICKETS_PER_EMAIL);
});

/*
 * What is closed is not open: a requester who has been helped a hundred times
 * must not be locked out of asking again.
 */
test('tickets already put to rest do not count against the limit', function () {
    $requester = User::factory()->requester()->create(['email' => 'anna.rossi@example.com']);

    Ticket::factory()
        ->count(SupportRequestController::OPEN_TICKETS_PER_EMAIL)
        ->chiuso()
        ->for($requester, 'requester')
        ->create();

    Ticket::factory()
        ->count(SupportRequestController::OPEN_TICKETS_PER_EMAIL)
        ->annullato()
        ->for($requester, 'requester')
        ->create();

    $this->post(route('support.store'), supportRequest(['email' => 'anna.rossi@example.com']))
        ->assertSessionHasNoErrors();

    assertDatabaseHas('tickets', [
        'requester_id' => $requester->id,
        'status' => TicketStatus::Nuovo->value,
    ]);
});
