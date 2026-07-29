<?php

use App\Models\User;

test('users table renders with a dark background in dark mode', function () {
    $this->actingAs(userWithPermissions(['user:viewAny']));

    $page = visit('/users')->inDarkMode();

    $page->assertPresent('.MuiPaper-root');

    /**
     * The table is server-rendered with the light theme and switches to
     * dark right after hydration, so poll instead of asserting once.
     */
    $tableBackgroundIsDark = false;

    foreach (range(1, 20) as $attempt) {
        $tableBackgroundIsDark = (bool) $page->script(
            "getComputedStyle(document.querySelector('.MuiPaper-root')).backgroundColor !== 'rgb(255, 255, 255)'",
        );

        if ($tableBackgroundIsDark) {
            break;
        }

        usleep(250_000);
    }

    expect($tableBackgroundIsDark)->toBeTrue();

    $page->assertNoJavascriptErrors();
});

test('row actions stay visible without horizontal scrolling', function () {
    $target = User::factory()->create();

    $this->actingAs(superAdminUser());

    $page = visit('/users');

    $page->assertSee('Azioni')
        ->assertVisible("@edit-user-button-{$target->id}")
        ->assertVisible("@suspend-user-button-{$target->id}")
        ->assertNoJavascriptErrors();
});
