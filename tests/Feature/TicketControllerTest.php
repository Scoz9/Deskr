<?php

use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\TicketController;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

test('guests are redirected to the login page', function () {
    $this->get(route('tickets.index'))->assertRedirect(route('login'));
});

test('tickets index is displayed to users with ticket:viewAny', function () {
    Ticket::factory()->count(3)->create();

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tickets/index')
            ->has('tickets.data', 3)
            ->has('tickets.meta')
        );
});

test('tickets index is refused without the permission', function () {
    $this->actingAs(userWithPermissions([]))
        ->get(route('tickets.index'))
        ->assertForbidden();
});

/*
 * The three roles the roadmap asks for (§ step 21), exercised through the
 * real role → permission pipeline and not a raw grant.
 */
test('an admin opens the console', function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tickets.index'))
        ->assertOk();
});

test('an agent opens the console', function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('tickets.index'))
        ->assertOk();
});

/*
 * A requester is seeded with no console permission at all (§5): a portal
 * session wandering onto the console finds the same door closed.
 */
test('a requester does not open the console', function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $this->actingAs(User::factory()->requester()->create())
        ->get(route('tickets.index'))
        ->assertForbidden();
});

test('the backlog is paginated on the server', function () {
    Ticket::factory()->count(TicketController::PER_PAGE + 5)->create();

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index'))
        ->assertInertia(fn ($page) => $page
            ->has('tickets.data', TicketController::PER_PAGE)
            ->where('tickets.meta.total', TicketController::PER_PAGE + 5)
            ->where('tickets.meta.currentPage', 1)
            ->where('tickets.meta.perPage', TicketController::PER_PAGE)
        );
});

test('a second page carries the tickets the first one did not', function () {
    Ticket::factory()->count(TicketController::PER_PAGE + 5)->create();

    $orderedReferences = Ticket::query()->orderByDesc('created_at')->orderByDesc('id')->pluck('reference');
    $firstPageExpected = $orderedReferences->take(TicketController::PER_PAGE)->all();
    $secondPageExpected = $orderedReferences->skip(TicketController::PER_PAGE)->values()->all();

    $this->actingAs(userWithPermissions(['ticket:viewAny']));

    $this->get(route('tickets.index'))
        ->assertInertia(fn ($page) => $page
            ->where(
                'tickets.data',
                fn ($data) => collect($data)->pluck('reference')->all() === $firstPageExpected,
            )
        );

    $this->get(route('tickets.index', ['page' => 2]))
        ->assertInertia(fn ($page) => $page
            ->has('tickets.data', 5)
            ->where(
                'tickets.data',
                fn ($data) => collect($data)->pluck('reference')->all() === $secondPageExpected,
            )
        );
});

test('the list carries what an operator needs to triage a ticket', function () {
    $category = Category::factory()->create();
    $team = Team::factory()->create();
    $requester = User::factory()->requester()->for(Organization::factory()->create(['name' => 'Acme SRL']))->create(['name' => 'Anna Rossi']);
    $assignee = User::factory()->agent()->create(['name' => 'Mario Bianchi']);

    $ticket = Ticket::factory()->assegnato()->create([
        'subject' => 'La stampante non risponde',
        'category_id' => $category->id,
        'team_id' => $team->id,
        'requester_id' => $requester->id,
        'organization_id' => $requester->organization_id,
        'assignee_id' => $assignee->id,
    ]);

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index'))
        ->assertInertia(fn ($page) => $page
            ->where('tickets.data.0.reference', $ticket->reference)
            ->where('tickets.data.0.subject', 'La stampante non risponde')
            ->where('tickets.data.0.status', 'assegnato')
            ->where('tickets.data.0.requester', 'Anna Rossi')
            ->where('tickets.data.0.organization', 'Acme SRL')
            ->where('tickets.data.0.team', $team->name)
            ->where('tickets.data.0.assignee', 'Mario Bianchi')
        );
});

test('the console offers who a ticket may be filtered by', function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $team = Team::factory()->create();
    $agent = User::factory()->agent()->create();
    $admin = User::factory()->admin()->create();
    $requester = User::factory()->requester()->create();

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index'))
        ->assertInertia(fn ($page) => $page
            ->where(
                'filterOptions.teams',
                fn ($teams) => collect($teams)->pluck('id')->contains($team->id),
            )
            ->where(
                'filterOptions.assignees',
                fn ($assignees) => collect($assignees)->pluck('id')->contains($agent->id)
                    && collect($assignees)->pluck('id')->contains($admin->id)
                    && ! collect($assignees)->pluck('id')->contains($requester->id),
            )
        );
});

test('a status filter narrows the backlog to that status', function () {
    Ticket::factory()->nuovo()->count(2)->create();
    Ticket::factory()->risolto()->count(3)->create();

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', ['status' => 'risolto']))
        ->assertInertia(fn ($page) => $page
            ->has('tickets.data', 3)
            ->where('filters.status', 'risolto')
        );
});

test('a priority filter narrows the backlog to that priority', function () {
    Ticket::factory()->create(['priority' => TicketPriority::Urgente]);
    Ticket::factory()->count(2)->create(['priority' => TicketPriority::Bassa]);

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', ['priority' => 'urgente']))
        ->assertInertia(fn ($page) => $page->has('tickets.data', 1));
});

test('a channel filter narrows the backlog to that channel', function () {
    Ticket::factory()->create(['channel' => TicketChannel::Email]);
    Ticket::factory()->count(2)->create(['channel' => TicketChannel::Web]);

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', ['channel' => 'email']))
        ->assertInertia(fn ($page) => $page->has('tickets.data', 1));
});

test('a team filter narrows the backlog to that team', function () {
    $team = Team::factory()->create();
    Ticket::factory()->create(['team_id' => $team->id]);
    Ticket::factory()->count(2)->create();

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', ['team_id' => $team->id]))
        ->assertInertia(fn ($page) => $page
            ->has('tickets.data', 1)
            ->where('filters.teamId', $team->id)
        );
});

test('an assignee filter narrows the backlog to that agent', function () {
    $agent = User::factory()->agent()->create();
    Ticket::factory()->assegnato()->create(['assignee_id' => $agent->id]);
    Ticket::factory()->assegnato()->count(2)->create();

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', ['assignee' => $agent->id]))
        ->assertInertia(fn ($page) => $page->has('tickets.data', 1));
});

/*
 * The pool nobody has picked up is not a person to look up by id — it needs
 * its own value rather than being unreachable through the filter.
 */
test('the "unassigned" filter narrows the backlog to the pool', function () {
    Ticket::factory()->nuovo()->count(2)->create();
    Ticket::factory()->assegnato()->count(3)->create();

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', ['assignee' => 'unassigned']))
        ->assertInertia(fn ($page) => $page
            ->has('tickets.data', 2)
            ->where('filters.assignee', 'unassigned')
        );
});

test('filters combine instead of overriding one another', function () {
    $team = Team::factory()->create();
    Ticket::factory()->nuovo()->create(['team_id' => $team->id]);
    Ticket::factory()->nuovo()->create();
    Ticket::factory()->risolto()->create(['team_id' => $team->id]);

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', ['status' => 'nuovo', 'team_id' => $team->id]))
        ->assertInertia(fn ($page) => $page->has('tickets.data', 1));
});

test('an unknown status value is refused', function () {
    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', ['status' => 'non-esiste']))
        ->assertSessionHasErrors('status');
});

test('an assignee that is not an agent or admin is refused', function () {
    $requester = User::factory()->requester()->create();

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', ['assignee' => $requester->id]))
        ->assertSessionHasErrors('assignee');
});

/*
 * The reason the project runs on Postgres rather than MySQL in the first
 * place (§3): full-text on Postgres' own dictionary, no search engine of
 * its own.
 */
test('a search matches the subject', function () {
    $ticket = Ticket::factory()->create(['subject' => 'La stampante non risponde']);
    Ticket::factory()->create(['subject' => 'Accesso negato al gestionale']);

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', ['search' => 'stampante']))
        ->assertInertia(fn ($page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.reference', $ticket->reference)
            ->where('filters.search', 'stampante')
        );
});

test('a search matches a message in the thread', function () {
    $ticket = Ticket::factory()->create();
    TicketMessage::factory()->for($ticket)->create(['body' => 'Il problema riguarda la stampante laser dell\'ufficio.']);
    Ticket::factory()->create();

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', ['search' => 'laser']))
        ->assertInertia(fn ($page) => $page->has('tickets.data', 1));
});

test('a search matches the requester\'s name', function () {
    $requester = User::factory()->requester()->create(['name' => 'Anna Rossi']);
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    Ticket::factory()->create();

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', ['search' => 'Rossi']))
        ->assertInertia(fn ($page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.reference', $ticket->reference)
        );
});

test('a search matches the requester\'s organization', function () {
    $organization = Organization::factory()->create(['name' => 'Acme SRL']);
    $requester = User::factory()->requester()->for($organization)->create();
    $ticket = Ticket::factory()->create([
        'requester_id' => $requester->id,
        'organization_id' => $organization->id,
    ]);
    Ticket::factory()->create();

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', ['search' => 'Acme']))
        ->assertInertia(fn ($page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.reference', $ticket->reference)
        );
});

test('a search combines with the other filters', function () {
    $team = Team::factory()->create();
    Ticket::factory()->nuovo()->create(['subject' => 'La stampante non risponde', 'team_id' => $team->id]);
    Ticket::factory()->risolto()->create(['subject' => 'La stampante non risponde', 'team_id' => $team->id]);
    Ticket::factory()->nuovo()->create(['subject' => 'La stampante non risponde']);

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', [
            'search' => 'stampante',
            'status' => 'nuovo',
            'team_id' => $team->id,
        ]))
        ->assertInertia(fn ($page) => $page->has('tickets.data', 1));
});

test('a search with no match empties the backlog instead of erroring', function () {
    Ticket::factory()->count(3)->create();

    $this->actingAs(userWithPermissions(['ticket:viewAny']))
        ->get(route('tickets.index', ['search' => 'inesistente']))
        ->assertInertia(fn ($page) => $page->has('tickets.data', 0));
});

test('the detail page is refused without ticket:view', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(userWithPermissions([]))
        ->get(route('tickets.show', $ticket))
        ->assertForbidden();
});

/*
 * The requester's own ticket is reachable through the policy's ownership
 * clause (§ step 21), same as `view` already grants outside the console.
 */
test('a requester opens the detail of their own ticket', function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    $requester = User::factory()->requester()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($requester)
        ->get(route('tickets.show', $ticket))
        ->assertOk();
});

test('the thread shows every reply, oldest first, notes marked as internal', function () {
    $ticket = Ticket::factory()->create();
    $description = TicketMessage::factory()->for($ticket)->create(['body' => 'Descrizione iniziale']);
    $reply = TicketMessage::factory()->for($ticket)->dellOperatore()->create(['body' => 'Risposta pubblica']);
    $note = TicketMessage::factory()->for($ticket)->interna()->create(['body' => 'Nota per il team']);

    $this->actingAs(userWithPermissions(['ticket:view']))
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tickets/show')
            ->where('ticket.messages.0.id', $description->id)
            ->where('ticket.messages.0.isInternal', false)
            ->where('ticket.messages.1.id', $reply->id)
            ->where('ticket.messages.1.isInternal', false)
            ->where('ticket.messages.2.id', $note->id)
            ->where('ticket.messages.2.isInternal', true)
        );
});

test('the detail carries what an operator needs to read a ticket', function () {
    $organization = Organization::factory()->create(['name' => 'Acme SRL']);
    $requester = User::factory()->requester()->for($organization)->create(['name' => 'Mario Rossi']);
    $team = Team::factory()->create(['name' => 'Rete']);
    $assignee = User::factory()->agent()->create(['name' => 'Luca Bianchi']);
    $ticket = Ticket::factory()
        ->for($requester, 'requester')
        ->for($team)
        ->create(['assignee_id' => $assignee->id, 'subject' => 'Stampante offline']);

    $this->actingAs(userWithPermissions(['ticket:view']))
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn ($page) => $page
            ->where('ticket.reference', $ticket->reference)
            ->where('ticket.subject', 'Stampante offline')
            ->where('ticket.requester', 'Mario Rossi')
            ->where('ticket.organization', 'Acme SRL')
            ->where('ticket.team', 'Rete')
            ->where('ticket.assignee', 'Luca Bianchi')
        );
});

test('an attachment on a message carries a signed download link', function () {
    $ticket = Ticket::factory()->create();
    $message = TicketMessage::factory()->for($ticket)->create();
    $message->attachments()->save(Attachment::factory()->make(['original_name' => 'schermata.png']));

    $this->actingAs(userWithPermissions(['ticket:view']))
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn ($page) => $page
            ->where('ticket.messages.0.attachments.0.name', 'schermata.png')
            ->has('ticket.messages.0.attachments.0.url')
        );
});

test('the detail page carries the passages the ticket may take next', function () {
    $ticket = Ticket::factory()->assegnato()->create();

    $this->actingAs(userWithPermissions(['ticket:view', 'ticket:update']))
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn ($page) => $page
            ->where('canUpdate', true)
            ->where('nextStatuses', ['in_lavorazione', 'in_attesa', 'nuovo', 'annullato'])
        );
});

test('a requester viewing their own ticket cannot update it', function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    $requester = User::factory()->requester()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($requester)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn ($page) => $page->where('canUpdate', false));
});

test('assigning to me is refused without ticket:update', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(userWithPermissions(['ticket:view']))
        ->post(route('tickets.assign-to-me', $ticket))
        ->assertForbidden();
});

test('an agent assigns an unassigned ticket to themselves', function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->nuovo()->create();

    $this->actingAs($agent)
        ->post(route('tickets.assign-to-me', $ticket))
        ->assertRedirect();

    expect($ticket->fresh())
        ->assignee_id->toBe($agent->id)
        ->status->toBe(TicketStatus::Assegnato);
});

test('an unknown status is refused when changing status', function () {
    $ticket = Ticket::factory()->assegnato()->create();

    $this->actingAs(userWithPermissions(['ticket:view', 'ticket:update']))
        ->patch(route('tickets.update-status', $ticket), ['status' => 'non-esiste'])
        ->assertInvalid(['status']);
});

test('a status the lifecycle does not admit from here is refused', function () {
    $ticket = Ticket::factory()->nuovo()->create();

    $this->actingAs(userWithPermissions(['ticket:view', 'ticket:update']))
        ->patch(route('tickets.update-status', $ticket), ['status' => 'risolto'])
        ->assertInvalid(['status']);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Nuovo);
});

test('an admitted status change goes through the transition table', function () {
    $ticket = Ticket::factory()->assegnato()->create();

    $this->actingAs(userWithPermissions(['ticket:view', 'ticket:update']))
        ->patch(route('tickets.update-status', $ticket), ['status' => 'in_lavorazione'])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe(TicketStatus::InLavorazione);
});

test('cancelling from the detail page is the same transition as any other', function () {
    $ticket = Ticket::factory()->assegnato()->create();

    $this->actingAs(userWithPermissions(['ticket:view', 'ticket:update']))
        ->patch(route('tickets.update-status', $ticket), ['status' => 'annullato'])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Annullato);
});

test('changing status is refused without ticket:update', function () {
    $ticket = Ticket::factory()->assegnato()->create();

    $this->actingAs(userWithPermissions(['ticket:view']))
        ->patch(route('tickets.update-status', $ticket), ['status' => 'in_lavorazione'])
        ->assertForbidden();
});

test('an operator changes the priority', function () {
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::Normale]);

    $this->actingAs(userWithPermissions(['ticket:view', 'ticket:update']))
        ->patch(route('tickets.update-priority', $ticket), ['priority' => 'urgente'])
        ->assertRedirect();

    expect($ticket->fresh()->priority)->toBe(TicketPriority::Urgente);
});

test('an unknown priority value is refused', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(userWithPermissions(['ticket:view', 'ticket:update']))
        ->patch(route('tickets.update-priority', $ticket), ['priority' => 'catastrofica'])
        ->assertInvalid(['priority']);
});

test('changing priority is refused without ticket:update', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(userWithPermissions(['ticket:view']))
        ->patch(route('tickets.update-priority', $ticket), ['priority' => 'urgente'])
        ->assertForbidden();
});
