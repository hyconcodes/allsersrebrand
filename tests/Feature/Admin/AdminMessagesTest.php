<?php

use App\Mail\AdminMessageMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

test('admin message recipient search can be changed repeatedly and combined with role filters', function () {
    $guest = User::factory()->create([
        'name' => 'First Guest Recipient',
        'username' => 'first_guest',
        'role' => 'guest',
    ]);
    $artisan = User::factory()->create([
        'name' => 'Second Artisan Recipient',
        'username' => 'second_artisan',
        'role' => 'artisan',
    ]);

    $component = Volt::test('admin.messages')
        ->set('searchQuery', 'First')
        ->assertSee($guest->name)
        ->assertDontSee($artisan->name)
        ->set('searchQuery', 'Second')
        ->assertSee($artisan->name)
        ->assertDontSee($guest->name)
        ->set('searchQuery', '')
        ->set('roleFilter', 'artisan')
        ->assertSee($artisan->name)
        ->assertDontSee($guest->name);

    $component->call('toggleRecipient', $artisan->id)
        ->assertSet('selected', [$artisan->id])
        ->call('toggleRecipient', $artisan->id)
        ->assertSet('selected', []);
});

test('admin message send button delivers selected recipient emails and clears successful sends', function () {
    Mail::fake();

    $recipient = User::factory()->create();

    Volt::test('admin.messages')
        ->call('toggleRecipient', $recipient->id)
        ->set('title', 'Service update')
        ->set('body', 'A message for our users.')
        ->call('sendMessages')
        ->assertSet('selected', [])
        ->assertSet('title', '')
        ->assertSet('body', '')
        ->assertDispatched('toast', type: 'success');

    Mail::assertSent(AdminMessageMail::class, function (AdminMessageMail $mail) use ($recipient) {
        return $mail->hasTo($recipient->email);
    });
});
