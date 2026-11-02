<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('logged out visitors can view a public artisan profile', function () {
    $artisan = User::factory()->create(['role' => 'artisan']);

    $this->get(route('artisan.profile', $artisan))
        ->assertOk()
        ->assertSee($artisan->name);
});
