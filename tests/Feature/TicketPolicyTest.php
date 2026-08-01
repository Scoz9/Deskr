<?php

use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

test('an admin views any ticket', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    expect($admin->can('view', $ticket))->toBeTrue();
});

test('an agent views any ticket, the team is a filter and not a boundary', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create();

    expect($agent->can('view', $ticket))->toBeTrue();
});

test('a requester views their own ticket', function () {
    $requester = User::factory()->requester()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    expect($requester->can('view', $ticket))->toBeTrue();
});

/*
 * The one filter between two customers (§3): a requester is seeded with no
 * console permission at all, so ownership is the only thing that can ever
 * grant this.
 */
test('a requester does not view somebody else\'s ticket', function () {
    $requester = User::factory()->requester()->create();
    $somebodyElse = User::factory()->requester()->create();
    $ticket = Ticket::factory()->for($somebodyElse, 'requester')->create();

    expect($requester->can('view', $ticket))->toBeFalse();
});

test('a requester with no ticket at all still cannot list them', function () {
    $requester = User::factory()->requester()->create();

    expect($requester->can('viewAny', Ticket::class))->toBeFalse();
});

test('an agent may list tickets', function () {
    $agent = User::factory()->agent()->create();

    expect($agent->can('viewAny', Ticket::class))->toBeTrue();
});
