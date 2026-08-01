<?php

use App\Enums\TicketChannel;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    config([
        'services.postmark.inbound.username' => 'postmark',
        'services.postmark.inbound.password' => 'secret',
    ]);
});

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
