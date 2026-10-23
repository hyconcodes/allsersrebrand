<?php

use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.9,
            'action' => 'register',
        ]),
    ]);
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'username' => 'johndoe',
        'email' => 'test@example.com',
        'role' => 'guest',
        'password' => 'password',
        'password_confirmation' => 'password',
        'g-recaptcha-response' => 'valid-token',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('verification.notice', absolute: false));

    $this->assertAuthenticated();
});

test('registration lowercases the username before storing it', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'username' => 'John_Doe1',
        'email' => 'upper@example.com',
        'role' => 'guest',
        'password' => 'password',
        'password_confirmation' => 'password',
        'g-recaptcha-response' => 'valid-token',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('verification.notice', absolute: false));

    $this->assertDatabaseHas('users', [
        'email' => 'upper@example.com',
        'username' => 'john_doe1',
    ]);
});

test('registration lowercases the email before storing it', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'username' => 'johndoe',
        'email' => 'MixedCase@Example.COM',
        'role' => 'guest',
        'password' => 'password',
        'password_confirmation' => 'password',
        'g-recaptcha-response' => 'valid-token',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('verification.notice', absolute: false));

    $this->assertDatabaseHas('users', [
        'email' => 'mixedcase@example.com',
    ]);
});

test('registration accepts usernames with numbers and underscores', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'username' => 'john_doe1',
        'email' => 'underscore@example.com',
        'role' => 'guest',
        'password' => 'password',
        'password_confirmation' => 'password',
        'g-recaptcha-response' => 'valid-token',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('verification.notice', absolute: false));

    $this->assertDatabaseHas('users', [
        'email' => 'underscore@example.com',
        'username' => 'john_doe1',
    ]);
});

test('registration rejects usernames with special characters', function (string $username) {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'username' => $username,
        'email' => 'special'.md5($username).'@example.com',
        'role' => 'guest',
        'password' => 'password',
        'password_confirmation' => 'password',
        'g-recaptcha-response' => 'valid-token',
    ]);

    $response->assertSessionHasErrors('username');
    $this->assertGuest();
})->with([
    'hyphen' => 'john-doe',
    'at sign' => 'john@doe',
    'dot' => 'john.doe',
    'space' => 'john doe',
]);
