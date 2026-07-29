<?php

use App\Enums\TicketStatus;

test('the ticket lifecycle is made of exactly the seven statuses of the domain', function () {
    expect(array_map(fn (TicketStatus $status): string => $status->value, TicketStatus::cases()))
        ->toBe([
            'nuovo',
            'assegnato',
            'in_lavorazione',
            'in_attesa',
            'risolto',
            'chiuso',
            'annullato',
        ]);
});

test('there is no reopened status: reopening is a transition, not a state', function () {
    expect(TicketStatus::tryFrom('riaperto'))->toBeNull();
});

test('cancelled exists so that spam never has to be assigned to a real agent', function () {
    expect(TicketStatus::tryFrom('annullato'))->toBe(TicketStatus::Annullato);
});
