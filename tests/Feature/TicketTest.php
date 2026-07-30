<?php

use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('the factory persists a new ticket with the defaults of the intake', function () {
    $ticket = Ticket::factory()->create();

    expect($ticket->subject)->toBeString()->not->toBeEmpty()
        ->and($ticket->status)->toBe(TicketStatus::Nuovo)
        ->and($ticket->priority)->toBe(TicketPriority::Normale)
        ->and($ticket->channel)->toBeInstanceOf(TicketChannel::class)
        ->and($ticket->assignee_id)->toBeNull()
        ->and($ticket->reopen_count)->toBe(0)
        ->and($ticket->first_response_at)->toBeNull()
        ->and($ticket->resolved_at)->toBeNull()
        ->and($ticket->closed_at)->toBeNull()
        ->and($ticket->due_at)->toBeNull()
        ->and($ticket->parent_ticket_id)->toBeNull();

    $this->assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'reference' => $ticket->reference,
        'status' => TicketStatus::Nuovo->value,
        'priority' => TicketPriority::Normale->value,
    ]);
});

test('a ticket gets a reference in the public format when it is created', function () {
    $ticket = Ticket::factory()->create();

    expect($ticket->reference)->toMatch('/^DSK-\d{6}$/');
});

test('the reference does not come from the auto-incrementing id and never repeats', function () {
    $references = Ticket::factory()->count(3)->create()->pluck('reference');

    expect($references->unique())->toHaveCount(3);

    DB::statement('ALTER SEQUENCE tickets_reference_seq RESTART WITH 900');

    expect(Ticket::factory()->create()->reference)->toBe('DSK-000900');
});

/**
 * The sequence is a standalone database object: without this link, dropping the
 * tickets table would leave it behind and the next `migrate:fresh` would collide
 * with it.
 */
test('the reference sequence belongs to the column it feeds', function () {
    $dependencies = DB::scalar(
        'select count(*) from pg_depend d
            join pg_class s on s.oid = d.objid
            where s.relname = ? and d.deptype = \'a\'',
        [Ticket::REFERENCE_SEQUENCE],
    );

    expect((int) $dependencies)->toBe(1);
});

test('a reference given explicitly is kept, so an import can carry its own', function () {
    $ticket = Ticket::factory()->create(['reference' => 'DSK-004242']);

    expect($ticket->reference)->toBe('DSK-004242');
});

test('two tickets cannot share the same reference', function () {
    $ticket = Ticket::factory()->create();

    expect(fn () => Ticket::factory()->create(['reference' => $ticket->reference]))
        ->toThrow(QueryException::class);
});

test('a ticket cannot exist without a subject', function () {
    expect(fn () => Ticket::factory()->create(['subject' => null]))->toThrow(QueryException::class);
});

test('a ticket cannot exist without the requester who asked for it', function () {
    expect(fn () => Ticket::factory()->create(['requester_id' => null]))->toThrow(QueryException::class);
});

test('a ticket carries the requester, the company, the category and the team it was routed to', function () {
    $ticket = Ticket::factory()->create();

    expect($ticket->requester)->toBeInstanceOf(User::class)
        ->and($ticket->organization)->toBeInstanceOf(Organization::class)
        ->and($ticket->category)->toBeInstanceOf(Category::class)
        ->and($ticket->team)->toBeInstanceOf(Team::class);
});

test('the factory routes the ticket to the team of its category and to the company of its requester', function () {
    $ticket = Ticket::factory()->create();

    expect($ticket->team_id)->toBe($ticket->category?->team_id)
        ->and($ticket->organization_id)->toBe($ticket->requester->organization_id);
});

test('a ticket keeps the requester it was opened for, so the history stays readable', function () {
    $ticket = Ticket::factory()->create();

    expect(fn () => $ticket->requester->delete())->toThrow(QueryException::class);
});

test('deleting the assignee leaves the ticket unassigned instead of deleting it', function () {
    $ticket = Ticket::factory()->assegnato()->create();

    $ticket->assignee?->delete();

    expect($ticket->refresh()->assignee_id)->toBeNull();
});

test('a reply on a closed ticket becomes a follow-up that points back at it', function () {
    $closed = Ticket::factory()->chiuso()->create();
    $followUp = Ticket::factory()->for($closed, 'parentTicket')->create();

    expect($followUp->parentTicket?->id)->toBe($closed->id)
        ->and($closed->followUps->pluck('id')->all())->toBe([$followUp->id]);
});

test('a ticket cannot point at a parent that does not exist', function () {
    expect(fn () => Ticket::factory()->create(['parent_ticket_id' => 9999]))
        ->toThrow(QueryException::class);
});

test('status, priority and channel are enums on the model and strings in the database', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::InAttesa,
        'priority' => TicketPriority::Urgente,
        'channel' => TicketChannel::Email,
    ]);

    expect($ticket->status)->toBe(TicketStatus::InAttesa)
        ->and($ticket->priority)->toBe(TicketPriority::Urgente)
        ->and($ticket->channel)->toBe(TicketChannel::Email);

    $row = DB::table('tickets')->where('id', $ticket->id)->first();

    expect($row?->status)->toBe('in_attesa')
        ->and($row?->priority)->toBe('urgente')
        ->and($row?->channel)->toBe('email');
});

test('the factory has a state for every status, with the metric timestamps it implies', function (
    string $state,
    TicketStatus $status,
    bool $assigned,
    bool $answered,
    bool $resolved,
    bool $closed,
) {
    $ticket = Ticket::factory()->{$state}()->create();

    expect($ticket->status)->toBe($status)
        ->and($ticket->assignee_id !== null)->toBe($assigned)
        ->and($ticket->first_response_at !== null)->toBe($answered)
        ->and($ticket->resolved_at !== null)->toBe($resolved)
        ->and($ticket->closed_at !== null)->toBe($closed);
})->with([
    'nuovo' => ['nuovo', TicketStatus::Nuovo, false, false, false, false],
    'assegnato' => ['assegnato', TicketStatus::Assegnato, true, false, false, false],
    'in lavorazione' => ['inLavorazione', TicketStatus::InLavorazione, true, false, false, false],
    'in attesa' => ['inAttesa', TicketStatus::InAttesa, true, true, false, false],
    'risolto' => ['risolto', TicketStatus::Risolto, true, true, true, false],
    'chiuso' => ['chiuso', TicketStatus::Chiuso, true, true, true, true],
    'annullato' => ['annullato', TicketStatus::Annullato, false, false, false, false],
]);

test('the metric timestamps of a closed ticket follow the order they happened in', function () {
    $ticket = Ticket::factory()->chiuso()->create();

    expect($ticket->created_at?->lessThanOrEqualTo($ticket->first_response_at))->toBeTrue()
        ->and($ticket->first_response_at?->lessThanOrEqualTo($ticket->resolved_at))->toBeTrue()
        ->and($ticket->resolved_at?->lessThanOrEqualTo($ticket->closed_at))->toBeTrue();
});
