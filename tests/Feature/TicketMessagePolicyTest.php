<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

/**
 * A requester with a ticket of their own, which is what the thread hangs from.
 */
function requesterWithTicket(): array
{
    $requester = User::factory()->requester()->for(Organization::factory())->create();

    return [$requester, Ticket::factory()->for($requester, 'requester')->create()];
}

/**
 * A message of the thread the requester is meant to read.
 */
function publicMessageOn(Ticket $ticket): TicketMessage
{
    return TicketMessage::factory()->for($ticket)->pubblica()->create();
}

/**
 * A note the team keeps to itself.
 */
function internalNoteOn(Ticket $ticket): TicketMessage
{
    return TicketMessage::factory()->for($ticket)->interna()->create();
}

test('an agent reads the thread and the notes the team keeps to itself', function () {
    [, $ticket] = requesterWithTicket();
    $agent = User::factory()->agent()->create();

    expect($agent->can('viewAny', TicketMessage::class))->toBeTrue()
        ->and($agent->can('view', publicMessageOn($ticket)))->toBeTrue()
        ->and($agent->can('view', internalNoteOn($ticket)))->toBeTrue();
});

test('an admin reads the internal notes too', function () {
    [, $ticket] = requesterWithTicket();
    $admin = User::factory()->admin()->create();

    expect($admin->can('view', internalNoteOn($ticket)))->toBeTrue();
});

test('a requester reads the thread of their own ticket', function () {
    [$requester, $ticket] = requesterWithTicket();

    expect($requester->can('view', publicMessageOn($ticket)))->toBeTrue();
});

/*
 * The internal note is the one thing the thread hides: it is written for the
 * team, and a portal that lets it through has published it.
 */
test('a requester never reads an internal note, not even on their own ticket', function () {
    [$requester, $ticket] = requesterWithTicket();

    expect($requester->can('view', internalNoteOn($ticket)))->toBeFalse();
});

test('a requester does not read the thread of another requester', function () {
    [$requester] = requesterWithTicket();
    [, $otherTicket] = requesterWithTicket();

    expect($requester->can('view', publicMessageOn($otherTicket)))->toBeFalse();
});

test('a requester has no thread to browse outside a ticket', function () {
    [$requester] = requesterWithTicket();

    expect($requester->can('viewAny', TicketMessage::class))->toBeFalse();
});

/*
 * Answering from the portal is the flow of step 27, and it will bring the
 * ability that lets it through. From the console, writing is the agent's.
 */
test('a requester does not write from the console', function () {
    [$requester] = requesterWithTicket();

    expect($requester->can('create', TicketMessage::class))->toBeFalse();
});

test('an agent writes replies and notes', function () {
    expect(User::factory()->agent()->create()->can('create', TicketMessage::class))->toBeTrue();
});
