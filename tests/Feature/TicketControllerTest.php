<?php

use App\Http\Controllers\TicketController;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Team;
use App\Models\Ticket;
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
