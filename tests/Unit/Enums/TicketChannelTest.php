<?php

use App\Enums\TicketChannel;

test('a ticket can enter from exactly the three channels in scope', function () {
    expect(array_map(fn (TicketChannel $channel): string => $channel->value, TicketChannel::cases()))
        ->toBe(['web', 'email', 'telefono']);
});
