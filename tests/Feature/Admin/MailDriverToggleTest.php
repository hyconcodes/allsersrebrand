<?php

use App\Models\SystemSetting;
use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the mail settings screen is forbidden for non-admin users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.settings'))
        ->assertStatus(403);
});

test('admins can render the mail settings screen', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.settings'))
        ->assertStatus(200);
});

test('admins can switch the mail driver to mailtrap', function () {
    config(['services.mailtrap.token' => 'test-token']);

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Volt::test('admin.settings')
        ->set('mailDriver', 'mailtrap')
        ->call('save')
        ->assertDispatched('toast');

    expect(SystemSetting::get('mail_driver', 'smtp'))->toBe('mailtrap')
        ->and(config('mail.default'))->toBe('mailtrap');
});

test('switching to mailtrap without an API token is blocked', function () {
    config(['services.mailtrap.token' => '']);

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $before = config('mail.default');

    Volt::test('admin.settings')
        ->set('mailDriver', 'mailtrap')
        ->call('save')
        ->assertDispatched('toast');

    expect(SystemSetting::get('mail_driver', 'smtp'))->toBe('smtp')
        ->and(config('mail.default'))->toBe($before);
});

test('admins can switch the mail driver back to smtp', function () {
    SystemSetting::set('mail_driver', 'mailtrap');
    SystemSetting::applyMailDriver();

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Volt::test('admin.settings')
        ->set('mailDriver', 'smtp')
        ->call('save');

    expect(SystemSetting::get('mail_driver', 'mailtrap'))->toBe('smtp')
        ->and(config('mail.default'))->toBe('smtp');
});

test('the persisted toggle is applied to config at application boot', function () {
    SystemSetting::set('mail_driver', 'mailtrap');
    SystemSetting::flushResolved();
    SystemSetting::applyMailDriver();

    expect(config('mail.default'))->toBe('mailtrap');
});

test('config default is left untouched when no toggle has been saved', function () {
    SystemSetting::flushResolved();
    $before = config('mail.default');

    SystemSetting::applyMailDriver();

    expect(config('mail.default'))->toBe($before);
});
