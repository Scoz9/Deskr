<?php

use App\Enums\TicketStatus;
use App\Tickets\InvalidTicketTransition;
use App\Tickets\TicketTransitions;

/*
 * The table of §4, written out by hand and not derived from the class under
 * test: a table computed from the same source it verifies would only prove
 * that the class agrees with itself. Every one of the seven statuses is a key,
 * including the two terminal ones, so a status added without a rule fails here
 * before it can reach a ticket.
 */
dataset('every possible pair of statuses', function () {
    $allowed = [
        'nuovo' => ['assegnato', 'annullato'],
        'assegnato' => ['in_lavorazione', 'in_attesa', 'nuovo', 'annullato'],
        'in_lavorazione' => ['in_attesa', 'risolto', 'annullato'],
        'in_attesa' => ['in_lavorazione', 'risolto'],
        'risolto' => ['chiuso', 'in_lavorazione'],
        'chiuso' => [],
        'annullato' => [],
    ];

    foreach (TicketStatus::cases() as $from) {
        foreach (TicketStatus::cases() as $to) {
            yield sprintf('%s → %s', $from->value, $to->value) => [
                $from,
                $to,
                in_array($to->value, $allowed[$from->value], true),
            ];
        }
    }
});

test('every pair of statuses is allowed or refused exactly as the brief says', function (TicketStatus $from, TicketStatus $to, bool $isAllowed) {
    expect(TicketTransitions::allows($from, $to))->toBe($isAllowed);
})->with('every possible pair of statuses');

test('a valid transition passes and an invalid one raises', function (TicketStatus $from, TicketStatus $to, bool $isAllowed) {
    $ensure = fn () => TicketTransitions::ensureAllowed($from, $to);

    $isAllowed
        ? expect($ensure)->not->toThrow(InvalidTicketTransition::class)
        : expect($ensure)->toThrow(InvalidTicketTransition::class);
})->with('every possible pair of statuses');

test('a status is never a transition to itself: standing still is not a passage', function (TicketStatus $status) {
    expect(TicketTransitions::allows($status, $status))->toBeFalse();
})->with(TicketStatus::cases());

test('the exception names both ends of the refused passage', function () {
    expect(fn () => TicketTransitions::ensureAllowed(TicketStatus::Chiuso, TicketStatus::InLavorazione))
        ->toThrow(InvalidTicketTransition::class, 'chiuso')
        ->and(fn () => TicketTransitions::ensureAllowed(TicketStatus::Chiuso, TicketStatus::InLavorazione))
        ->toThrow(InvalidTicketTransition::class, 'in_lavorazione');
});

test('closed and cancelled are terminal: nothing leaves them', function (TicketStatus $status) {
    expect(TicketTransitions::allowedFrom($status))->toBe([]);
})->with([TicketStatus::Chiuso, TicketStatus::Annullato]);

test('reopening is the passage from resolved to in progress, since there is no reopened status', function () {
    expect(TicketTransitions::allows(TicketStatus::Risolto, TicketStatus::InLavorazione))->toBeTrue();
});

test('an assigned ticket can go back to the pool', function () {
    expect(TicketTransitions::allows(TicketStatus::Assegnato, TicketStatus::Nuovo))->toBeTrue();
});
