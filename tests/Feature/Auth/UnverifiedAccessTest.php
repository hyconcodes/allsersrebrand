<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('unverified users are redirected from protected pages to the verification notice', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($user)
        ->get(route('user-password.edit'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($user)
        ->get(route('challenges.index'))
        ->assertRedirect(route('verification.notice'));
});

test('verified users can access settings and challenges', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    $this->actingAs($user)->get(route('challenges.index'))->assertOk();
});
