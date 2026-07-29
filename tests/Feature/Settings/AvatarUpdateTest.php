<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(User::AVATAR_DISK);
});

test('avatar can be uploaded', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 300, 300),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->avatar_path)->toStartWith(User::AVATAR_DIRECTORY.'/');

    Storage::disk(User::AVATAR_DISK)->assertExists($user->avatar_path);
});

test('uploaded avatars are scaled down proportionally', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 1200, 900),
        ])
        ->assertSessionHasNoErrors();

    $stored = Storage::disk(User::AVATAR_DISK)->get($user->refresh()->avatar_path);
    $dimensions = getimagesizefromstring($stored);

    expect($dimensions)->not->toBeFalse()
        ->and($dimensions[0])->toBe(512)
        ->and($dimensions[1])->toBe(384);
});

test('avatars smaller than the bounding box are left untouched', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 120, 80),
        ])
        ->assertSessionHasNoErrors();

    $stored = Storage::disk(User::AVATAR_DISK)->get($user->refresh()->avatar_path);
    $dimensions = getimagesizefromstring($stored);

    expect($dimensions[0])->toBe(120)
        ->and($dimensions[1])->toBe(80);
});

test('the avatar attribute exposes a public url and hides the stored path', function () {
    $user = User::factory()->create();

    expect($user->avatar)->toBeNull();

    $this
        ->actingAs($user)
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

    $user->refresh();

    expect($user->avatar)->toBe(Storage::disk(User::AVATAR_DISK)->url($user->avatar_path))
        ->and($user->toArray())->toHaveKey('avatar')
        ->and($user->toArray())->not->toHaveKey('avatar_path');
});

test('replacing an avatar deletes the previous file', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->image('first.jpg'),
        ]);

    $first = $user->refresh()->avatar_path;

    $this
        ->actingAs($user)
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->image('second.jpg'),
        ]);

    $second = $user->refresh()->avatar_path;

    expect($second)->not->toBe($first);

    Storage::disk(User::AVATAR_DISK)->assertMissing($first);
    Storage::disk(User::AVATAR_DISK)->assertExists($second);
});

test('avatar can be removed', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

    $path = $user->refresh()->avatar_path;

    $this
        ->actingAs($user)
        ->delete(route('avatar.destroy'))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->avatar_path)->toBeNull();

    Storage::disk(User::AVATAR_DISK)->assertMissing($path);
});

test('removing a missing avatar is a no-op', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->delete(route('avatar.destroy'))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->avatar_path)->toBeNull();
});

test('non image uploads are rejected', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'),
        ])
        ->assertSessionHasErrors('avatar');

    expect($user->refresh()->avatar_path)->toBeNull();
});

/**
 * The validation rules trust the client-supplied extension and MIME type, so
 * a payload that merely claims to be a PNG has to be caught by detecting the
 * category from the file contents.
 */
test('uploads that only claim to be an image are rejected', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->createWithContent('avatar.png', 'not an image at all'),
        ])
        ->assertSessionHasErrors(['avatar' => 'The avatar contents are not a valid image.']);

    expect($user->refresh()->avatar_path)->toBeNull();
    Storage::disk(User::AVATAR_DISK)->assertDirectoryEmpty(User::AVATAR_DIRECTORY);
});

test('avatars larger than the size limit are rejected', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->image('huge.jpg')->size(6144),
        ])
        ->assertSessionHasErrors('avatar');

    expect($user->refresh()->avatar_path)->toBeNull();
});

test('guests cannot manage avatars', function () {
    $this->post(route('avatar.update'))->assertRedirect(route('login'));
    $this->delete(route('avatar.destroy'))->assertRedirect(route('login'));
});
