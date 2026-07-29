<?php

use Laravel\Fortify\Features;

/*
 * Deskr non ha registrazione: operatori e admin nascono da invito, i richiedenti
 * dal primo ticket (§3 di docs/PROJECT.md). Questi test guardano la decisione —
 * riattivare `Features::registration()` li fa fallire, che è esattamente il punto.
 */

test('the registration feature is disabled', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();
});

test('the registration routes do not exist', function () {
    $this->get('/register')->assertNotFound();

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
});
