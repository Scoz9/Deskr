<?php

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

test('an admin views any message, internal notes included', function () {
    $admin = User::factory()->admin()->create();
    $note = TicketMessage::factory()->interna()->create();

    expect($admin->can('view', $note))->toBeTrue();
});

test('an agent views any message, internal notes included', function () {
    $agent = User::factory()->agent()->create();
    $note = TicketMessage::factory()->interna()->create();

    expect($agent->can('view', $note))->toBeTrue();
});

test('a requester views a public message on their own ticket', function () {
    $requester = User::factory()->requester()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    $message = TicketMessage::factory()->for($ticket)->create();

    expect($requester->can('view', $message))->toBeTrue();
});

/*
 * An internal note is written for the team and the requester never reads it
 * (§3), not even on their own ticket.
 */
test('a requester does not view an internal note on their own ticket', function () {
    $requester = User::factory()->requester()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    $note = TicketMessage::factory()->for($ticket)->interna()->create();

    expect($requester->can('view', $note))->toBeFalse();
});

/*
 * The one filter between two customers (§3), same as TicketPolicy.
 */
test('a requester does not view a message on somebody else\'s ticket', function () {
    $requester = User::factory()->requester()->create();
    $somebodyElse = User::factory()->requester()->create();
    $ticket = Ticket::factory()->for($somebodyElse, 'requester')->create();
    $message = TicketMessage::factory()->for($ticket)->create();

    expect($requester->can('view', $message))->toBeFalse();
});
