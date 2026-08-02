<?php

use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Http\Controllers\TicketController;
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
