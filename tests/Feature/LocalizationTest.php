<?php

test('the default locale is italian when the browser language is unsupported', function () {
    $response = $this->get(route('home'), ['Accept-Language' => 'de-DE,de;q=0.9']);

    $response->assertOk();
    expect(app()->getLocale())->toBe('it');
});

test('the locale can be switched through the query parameter and is remembered', function () {
    $response = $this->get(route('home').'?locale=en');

    $response->assertOk();
    $response->assertSessionHas('app_locale', 'en');
    expect(app()->getLocale())->toBe('en');
});

test('the browser language is used when no explicit locale is set', function () {
    $response = $this->get(route('home'), ['Accept-Language' => 'en-GB,en;q=0.9']);

    $response->assertOk();
    expect(app()->getLocale())->toBe('en');
});

test('the locale can be switched through the dedicated endpoint', function () {
    $response = $this->from(route('home'))->put('/locale', ['locale' => 'en']);

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('app_locale', 'en');
});

test('an unsupported locale is rejected by the switch endpoint', function () {
    $response = $this->from(route('home'))->put('/locale', ['locale' => 'de']);

    $response->assertSessionHasErrors('locale');
});

test('the localization data is shared with inertia pages', function () {
    $response = $this->get(route('home').'?locale=en');

    $response->assertInertia(
        fn ($page) => $page
            ->where('localization.locale', 'en')
            ->where('localization.locales', ['it', 'en']),
    );
});
