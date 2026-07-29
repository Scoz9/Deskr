<?php

use App\Models\User;

test('guests can switch the language from the login page', function () {
    // The headless browser sends Accept-Language: en-US, so the page
    // starts in English and the select shows the current autonym.
    $page = visit('/');

    $page->assertSee('English')
        ->click('@language-select')
        ->click('Italiano')
        ->assertSee('Italiano')
        ->assertDontSee('English')
        ->assertNoJavascriptErrors();
});

test('authenticated users can switch the language from the user menu', function () {
    $this->actingAs(User::factory()->create());

    $page = visit('/dashboard');

    $page->click('@sidebar-menu-button')
        ->assertSee('Language')
        ->click('@language-menu')
        ->click('Italiano')
        ->waitForEvent('networkidle')
        ->assertNoJavascriptErrors();

    // A fresh page load proves the choice survived in the session.
    $fresh = visit('/dashboard');

    $fresh->click('@sidebar-menu-button')
        ->assertSee('Lingua')
        ->assertDontSee('Language');
});
