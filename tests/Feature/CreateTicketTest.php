<?php

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\NewTicket;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;

use function Pest\Laravel\assertDatabaseCount;

/**
 * A requester with a company behind them, which is what the intake copies onto
 * the ticket.
 */
function requester(): User
{
    return User::factory()->requester()->for(Organization::factory())->create();
}

/**
 * Run the use case the way every channel will: resolved from the container and
 * handed a DTO.
 */
function createTicket(NewTicket $request): Ticket
{
    return app(CreateTicket::class)($request);
}

test('the intake opens the ticket in the status the lifecycle starts from', function () {
    $ticket = createTicket(new NewTicket(
        requester: requester(),
        subject: 'La stampante non risponde',
        body: 'Da stamattina la stampante del secondo piano non stampa.',
        channel: TicketChannel::Email,
        category: Category::factory()->create(),
    ));

    expect($ticket->exists)->toBeTrue()
        ->and($ticket->subject)->toBe('La stampante non risponde')
        ->and($ticket->status)->toBe(TicketStatus::Nuovo)
        ->and($ticket->channel)->toBe(TicketChannel::Email)
        ->and($ticket->assignee_id)->toBeNull()
        ->and($ticket->reference)->toStartWith(Ticket::REFERENCE_PREFIX);
});

test('the ticket belongs to the requester and to the company they write for', function () {
    $requester = requester();

    $ticket = createTicket(new NewTicket(
        requester: $requester,
        subject: 'Accesso negato al gestionale',
        body: 'Da ieri il gestionale mi rifiuta le credenziali.',
        channel: TicketChannel::Web,
    ));

    expect($ticket->requester_id)->toBe($requester->id)
        ->and($ticket->organization_id)->toBe($requester->organization_id);
});

/*
 * The routing of pillar #3: the category carries the team, and the ticket is
 * born already on the desk that answers for it. Deterministic and with no AI in
 * the way — the triage of phase 6 is a layer above a system that works without
 * it.
 */
test('the category routes the ticket to its team', function () {
    $category = Category::factory()->create();

    $ticket = createTicket(new NewTicket(
        requester: requester(),
        subject: 'Il badge non apre il tornello',
        body: 'Il badge è stato rifiutato tre volte stamattina.',
        channel: TicketChannel::Web,
        category: $category,
    ));

    expect($ticket->category_id)->toBe($category->id)
        ->and($ticket->team_id)->toBe($category->team_id);
});

/*
 * An inbound email carries no category, and refusing it would mean losing the
 * request. It lands unclassified in the pool instead, which is where the console
 * of phase 4 goes looking.
 */
test('a request that comes in unclassified lands with no category and no team', function () {
    $ticket = createTicket(new NewTicket(
        requester: requester(),
        subject: 'Richiesta generica',
        body: 'Buongiorno, avrei bisogno di assistenza.',
        channel: TicketChannel::Email,
    ));

    expect($ticket->category_id)->toBeNull()
        ->and($ticket->team_id)->toBeNull();
});

/*
 * The routing is read once, at the intake: re-routing a category later must not
 * rewrite where the tickets already handled went.
 */
test('the team is written on the ticket and not read back through the category', function () {
    $category = Category::factory()->create();

    $ticket = createTicket(new NewTicket(
        requester: requester(),
        subject: 'Monitor sfarfallante',
        body: 'Il monitor lampeggia a intermittenza.',
        channel: TicketChannel::Web,
        category: $category,
    ));

    $teamAtIntake = $category->team_id;

    $category->update(['team_id' => Team::factory()->create()->id]);

    expect($ticket->fresh()->team_id)->toBe($teamAtIntake);
});

test('a request nobody prioritised starts at normale', function () {
    $ticket = createTicket(new NewTicket(
        requester: requester(),
        subject: 'Carta esaurita',
        body: 'La stampante del primo piano è senza carta.',
        channel: TicketChannel::Web,
    ));

    expect($ticket->priority)->toBe(TicketPriority::Normale);
});

/*
 * The public intake never exposes priority, but the agent opening a ticket by
 * phone at step 39 does — so the DTO carries it, with `normale` as the default
 * every channel that does not ask gets.
 */
test('an agent opening a ticket by phone may prioritise it', function () {
    $ticket = createTicket(new NewTicket(
        requester: requester(),
        subject: 'Server di produzione irraggiungibile',
        body: 'Nessuno riesce a collegarsi da mezz’ora.',
        channel: TicketChannel::Telefono,
        priority: TicketPriority::Urgente,
    ));

    expect($ticket->priority)->toBe(TicketPriority::Urgente);
});

/*
 * The ticket carries no `body`: the description is the first message of the
 * thread, public and written by the requester, so that the thread is uniform to
 * render and the attachments of step 24 have something to hang from.
 */
test('the description becomes the first public message of the thread', function () {
    $requester = requester();

    $ticket = createTicket(new NewTicket(
        requester: $requester,
        subject: 'La stampante non risponde',
        body: 'Da stamattina la stampante del secondo piano non stampa.',
        channel: TicketChannel::Web,
    ));

    expect($ticket->messages)->toHaveCount(1);

    $message = $ticket->messages->first();

    expect($message->body)->toBe('Da stamattina la stampante del secondo piano non stampa.')
        ->and($message->author_id)->toBe($requester->id)
        ->and($message->is_internal)->toBeFalse();
});

/*
 * A ticket whose thread is missing has lost the request it was opened for: the
 * two rows are one fact, and they land together or not at all.
 */
test('the ticket and its first message stand or fall together', function () {
    TicketMessage::creating(function (): void {
        throw new RuntimeException('the thread could not be written');
    });

    $request = new NewTicket(
        requester: requester(),
        subject: 'Richiesta che non deve sopravvivere',
        body: 'Il messaggio non riesce a essere scritto.',
        channel: TicketChannel::Web,
    );

    expect(fn () => createTicket($request))->toThrow(RuntimeException::class);

    assertDatabaseCount('tickets', 0);
    assertDatabaseCount('ticket_messages', 0);
});

test('two requests opened one after the other get their own reference', function () {
    $requester = requester();

    $first = createTicket(new NewTicket(
        requester: $requester,
        subject: 'Prima richiesta',
        body: 'Il primo problema.',
        channel: TicketChannel::Web,
    ));

    $second = createTicket(new NewTicket(
        requester: $requester,
        subject: 'Seconda richiesta',
        body: 'Il secondo problema.',
        channel: TicketChannel::Web,
    ));

    expect($first->reference)->not->toBe($second->reference);
});
