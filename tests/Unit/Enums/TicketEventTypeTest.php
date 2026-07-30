<?php

use App\Enums\TicketEventType;

/**
 * The vocabulary grows at every step that adds a fact worth recording, and it
 * grows here first: a name added without a line in this list is a name nobody
 * decided on.
 */
test('the vocabulary of the audit trail is exactly the one the domain emits', function () {
    expect(array_map(fn (TicketEventType $type): string => $type->value, TicketEventType::cases()))
        ->toBe([
            'ticket.assegnato',
            'ticket.riassegnato',
            'ticket.preso_in_carico',
            'ticket.rimesso_nel_pool',
            'ticket.messo_in_attesa',
            'ticket.ripreso',
            'ticket.risolto',
            'ticket.chiuso',
            'ticket.annullato',
            'ticket.riaperto',
        ]);
});

/**
 * The trail is read by people and by the metrics of phase 5, and both need to
 * tell a reopening from a first assignment years after the fact.
 */
test('every type is namespaced and distinct', function () {
    $values = array_map(fn (TicketEventType $type): string => $type->value, TicketEventType::cases());

    expect($values)->toBe(array_unique($values))
        ->and($values)->each->toStartWith('ticket.');
});

/**
 * Reopening is a transition and not a status (§4), so it has to be legible in
 * the trail: it is the only place left where a reopening exists as a fact.
 */
test('reopening has a type of its own', function () {
    expect(TicketEventType::Riaperto->value)->toBe('ticket.riaperto');
});

/**
 * A handover is not a passage: the ticket stays where it is in the lifecycle,
 * and the trail still has to remember whose desk it moved to.
 */
test('a reassignment is told apart from a first assignment', function () {
    expect(TicketEventType::Riassegnato->value)->toBe('ticket.riassegnato')
        ->and(TicketEventType::Riassegnato)->not->toBe(TicketEventType::Assegnato);
});
