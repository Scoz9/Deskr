<?php

use App\Enums\TicketActorType;

test('an action on a ticket comes from exactly the three kinds of actor in scope', function () {
    expect(array_map(fn (TicketActorType $actor): string => $actor->value, TicketActorType::cases()))
        ->toBe(['utente', 'sistema', 'ai']);
});

/**
 * The system and the AI have no row to point at, which is the whole reason the
 * kind is written next to the polymorphic actor instead of being derived from
 * it.
 */
test('only a user is an actor with a record behind it', function () {
    expect(TicketActorType::Utente->isPerson())->toBeTrue()
        ->and(TicketActorType::Sistema->isPerson())->toBeFalse()
        ->and(TicketActorType::Ai->isPerson())->toBeFalse();
});
