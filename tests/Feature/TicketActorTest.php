<?php

use App\Enums\TicketActorType;
use App\Models\User;
use App\Tickets\TicketActor;

test('a person carries both the record and the kind', function () {
    $user = new User;

    $actor = TicketActor::user($user);

    expect($actor->kind)->toBe(TicketActorType::Utente)
        ->and($actor->record)->toBe($user);
});

/**
 * §4: the system and the AI have no record to point at, and the kind is the
 * only thing left saying who acted.
 */
test('the system and the AI act without a record but never without a kind', function (TicketActor $actor, TicketActorType $kind) {
    expect($actor->record)->toBeNull()
        ->and($actor->kind)->toBe($kind);
})->with([
    'system' => fn () => [TicketActor::system(), TicketActorType::Sistema],
    'ai' => fn () => [TicketActor::ai(), TicketActorType::Ai],
]);

/**
 * The kind and the record are set together and only together: they are two
 * columns of the same row, and an actor that says `utente` with nobody behind
 * it is the ambiguity the polimorphic actor was meant to remove.
 */
test('kind and record cannot be made to disagree', function () {
    expect(fn () => new TicketActor(TicketActorType::Utente, null))
        ->toThrow(Error::class);
});
