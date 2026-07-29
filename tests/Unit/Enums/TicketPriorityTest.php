<?php

use App\Enums\TicketPriority;

test('a ticket can carry exactly the four priorities of the domain', function () {
    expect(array_map(fn (TicketPriority $priority): string => $priority->value, TicketPriority::cases()))
        ->toBe(['bassa', 'normale', 'alta', 'urgente']);
});
