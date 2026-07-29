<?php

use App\Models\User;
use App\Notifications\UserInvitation;
use Scrapkit\NotificationKit\Domain\Templates\Models\NotificationTemplate;

test('the invitation template is synced from code', function () {
    $this->artisan('notification-kit:sync')->assertSuccessful();

    $template = NotificationTemplate::query()->where('key', 'users.invitation')->first();

    expect($template)->not->toBeNull()
        ->and($template->type->value)->toBe('email')
        ->and($template->subject)->toBeNull()
        ->and($template->requires_confirmation)->toBeFalse()
        ->and(collect($template->placeholders)->pluck('key')->all())
        ->toBe(['user.name', 'app.name', 'action.url']);
});

test('the invitation uses the content edited in the database', function () {
    $this->artisan('notification-kit:sync')->assertSuccessful();

    NotificationTemplate::query()->where('key', 'users.invitation')->firstOrFail()->update([
        'subject' => 'Benvenuto in {{ app.name }}',
        'body' => "Ciao **{{ user.name }}**,\n\n[Attiva il tuo account]({{ action.url }})",
    ]);

    $user = User::factory()->create(['name' => 'Mario Rossi']);

    $message = (new UserInvitation('reset-token'))->toMail($user);
    $rendered = (string) $message->render();

    expect($message->subject)->toBe('Benvenuto in '.config('app.name'))
        ->and($rendered)->toContain('Mario Rossi')
        ->and($rendered)->toContain('Attiva il tuo account')
        ->and($rendered)->toContain('reset-token');
});

test('the invitation falls back to the default content when nothing was synced', function () {
    $user = User::factory()->create(['name' => 'Mario Rossi']);

    $message = (new UserInvitation('reset-token'))->toMail($user);

    expect($message->subject)->toBe('Your '.config('app.name').' account')
        ->and((string) $message->render())->toContain('reset-token');
});
