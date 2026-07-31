<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

/*
 * The roles are the seeded ones, permissions included: a policy tested against
 * roles invented by the test would prove that the policy agrees with the test,
 * not that a requester of this application cannot read somebody else's ticket.
 */
beforeEach(function (): void {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

/**
 * A ticket somebody asked for, with the requester it belongs to.
 */
function ticketOf(User $requester): Ticket
{
    return Ticket::factory()->for($requester, 'requester')->create();
}

/**
 * Somebody who wrote to the helpdesk, with the company behind them.
 */
function someRequester(): User
{
    return User::factory()->requester()->for(Organization::factory())->create();
}

/*
 * A small helpdesk: the agents must be able to cover for each other, so the
 * team is a filter and not a boundary (§3).
 */
test('an agent reads every ticket, whoever asked for it', function () {
    $agent = User::factory()->agent()->create();

    expect($agent->can('viewAny', Ticket::class))->toBeTrue()
        ->and($agent->can('view', ticketOf(someRequester())))->toBeTrue();
});

test('an admin reads every ticket too', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('viewAny', Ticket::class))->toBeTrue()
        ->and($admin->can('view', ticketOf(someRequester())))->toBeTrue();
});

/*
 * The requester holds no permission at all (§5): the portal lets them in on
 * what is theirs, and on nothing else.
 */
test('a requester reads their own ticket', function () {
    $requester = someRequester();

    expect($requester->can('view', ticketOf($requester)))->toBeTrue();
});

/*
 * The one filter that has to hold: without global scoping, every filter
 * forgotten is a leak towards another customer.
 */
test('a requester does not read the ticket of another requester', function () {
    $requester = someRequester();

    expect($requester->can('view', ticketOf(someRequester())))->toBeFalse();
});

/*
 * Not even a colleague of the same company: the scope is the person, not the
 * organization (§3).
 */
test('a requester does not read the ticket of a colleague of the same company', function () {
    $requester = someRequester();
    $colleague = User::factory()->requester()->for($requester->organization)->create();

    expect($requester->can('view', ticketOf($colleague)))->toBeFalse();
});

test('a requester has no list of tickets to browse', function () {
    expect(someRequester()->can('viewAny', Ticket::class))->toBeFalse();
});

test('a requester changes nothing on the ticket, not even their own', function () {
    $requester = someRequester();
    $ticket = ticketOf($requester);

    expect($requester->can('update', $ticket))->toBeFalse()
        ->and($requester->can('delete', $ticket))->toBeFalse();
});

/*
 * The intake of the public form is unauthenticated and does not pass from
 * here; opening a ticket for somebody else from the console (step 39) does.
 */
test('an agent opens a ticket on behalf of the requester', function () {
    expect(User::factory()->agent()->create()->can('create', Ticket::class))->toBeTrue();
});

test('a requester does not open tickets from the console', function () {
    expect(someRequester()->can('create', Ticket::class))->toBeFalse();
});

test('the superAdmin bypass answers before the policy does', function () {
    expect(superAdminUser()->can('view', ticketOf(someRequester())))->toBeTrue();
});
