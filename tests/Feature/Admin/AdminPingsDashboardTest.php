<?php

use App\Mail\AdminPingAlertMail;
use App\Models\Conversation;
use App\Models\Engagement;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Create a ping (engagement) between two users with a conversation.
 */
function createTestPing(User $pinger, User $artisan, array $overrides = []): Engagement
{
    $conversation = Conversation::create(['last_message_at' => now()]);
    $conversation->users()->attach([$pinger->id, $artisan->id]);

    $createdAt = $overrides['created_at'] ?? null;
    unset($overrides['created_at']);

    $engagement = Engagement::create(array_merge([
        'user_id' => $pinger->id,
        'artisan_id' => $artisan->id,
        'conversation_id' => $conversation->id,
        'status' => 'pending',
        'title' => 'Fix my leaky kitchen sink',
        'urgency_level' => 'high',
    ], $overrides));

    if ($createdAt) {
        Engagement::whereKey($engagement->id)->update(['created_at' => $createdAt]);
        $engagement->refresh();
    }

    return $engagement;
}

test('the pings dashboard is forbidden for non-admin users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.pings'))
        ->assertStatus(403);
});

test('admins can render the pings dashboard', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.pings'))
        ->assertStatus(200);
});

test('the dashboard defaults to the last 24 hours', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $pinger = User::factory()->create();
    $artisan = User::factory()->create(['role' => 'artisan']);

    createTestPing($pinger, $artisan, ['created_at' => now()->subHours(2)]);
    createTestPing($pinger, $artisan, ['created_at' => now()->subDays(3), 'title' => 'Old ping outside window']);

    $this->actingAs($admin);

    Volt::test('admin.pings')
        ->assertSet('scope', '24h')
        ->assertSet('stats.total', 1);
});

test('the 7 day range includes a ping from 3 days ago', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $pinger = User::factory()->create();
    $artisan = User::factory()->create(['role' => 'artisan']);

    createTestPing($pinger, $artisan, ['created_at' => now()->subDays(3)]);
    createTestPing($pinger, $artisan, ['created_at' => now()->subDays(10), 'title' => 'Too old for range']);

    $this->actingAs($admin);

    Volt::test('admin.pings')
        ->call('setScope', '7d')
        ->assertSet('stats.total', 1);
});

test('the year filter narrows results to that year', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $pinger = User::factory()->create();
    $artisan = User::factory()->create(['role' => 'artisan']);

    createTestPing($pinger, $artisan, ['created_at' => now()->subYear()]);
    createTestPing($pinger, $artisan, ['created_at' => now()]);

    $this->actingAs($admin);

    Volt::test('admin.pings')
        ->set('filterYear', (string) now()->year)
        ->assertSet('scope', 'custom')
        ->assertSet('stats.total', 1);
});

test('the month filter narrows results to that month', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $pinger = User::factory()->create();
    $artisan = User::factory()->create(['role' => 'artisan']);

    createTestPing($pinger, $artisan, ['created_at' => now()->startOfMonth()->subMonth()]);
    createTestPing($pinger, $artisan, ['created_at' => now()]);

    $this->actingAs($admin);

    Volt::test('admin.pings')
        ->set('filterMonth', (string) now()->month)
        ->assertSet('stats.total', 1);
});

test('the from/to hour filter narrows results by hour of day', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $pinger = User::factory()->create();
    $artisan = User::factory()->create(['role' => 'artisan']);

    createTestPing($pinger, $artisan, ['created_at' => now()->setTime(3, 0)]);
    createTestPing($pinger, $artisan, ['created_at' => now()->setTime(14, 0)]);

    $this->actingAs($admin);

    Volt::test('admin.pings')
        ->call('setScope', 'all')
        ->set('fromHour', '10')
        ->set('toHour', '20')
        ->assertSet('stats.total', 1);
});

test('search matches pinger or artisan names', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $pinger = User::factory()->create(['name' => 'Zebra Finder']);
    $otherPinger = User::factory()->create(['name' => 'Someone Else']);
    $artisan = User::factory()->create(['role' => 'artisan', 'name' => 'Quiet Artisan']);

    createTestPing($pinger, $artisan);
    createTestPing($otherPinger, $artisan);

    $this->actingAs($admin);

    Volt::test('admin.pings')
        ->call('setScope', 'all')
        ->set('search', 'Zebra')
        ->assertSet('stats.total', 1);
});

test('ping alert mail is queued to every registered admin email', function () {
    Mail::fake();

    $adminOne = User::factory()->create(['is_admin' => true]);
    $adminTwo = User::factory()->create(['is_admin' => true]);
    $pinger = User::factory()->create(['email' => 'pinger@example.com']);
    $artisan = User::factory()->create(['role' => 'artisan', 'email' => 'artisan@example.com']);

    SystemSetting::addPingAlertEmail('alerts-one@example.com', $adminOne->id);
    SystemSetting::addPingAlertEmail('alerts-two@example.com', $adminTwo->id);

    $engagement = createTestPing($pinger, $artisan);

    \App\Support\PingAlert::send($pinger, $artisan, $engagement);

    Mail::assertQueued(AdminPingAlertMail::class, 2);

    $recipients = [];
    Mail::queued(AdminPingAlertMail::class, function ($mail) use (&$recipients) {
        $recipients[] = strtolower(collect($mail->to)->pluck('address')->first());

        return true;
    });

    expect($recipients)->toEqualCanonicalizing(['alerts-one@example.com', 'alerts-two@example.com']);
});

test('ping alert mail is skipped when no emails are registered', function () {
    Mail::fake();

    $pinger = User::factory()->create();
    $artisan = User::factory()->create(['role' => 'artisan']);
    $engagement = createTestPing($pinger, $artisan);

    \App\Support\PingAlert::send($pinger, $artisan, $engagement);

    Mail::assertNothingQueued();
});

test('each admin can only register one ping alert email', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $first = SystemSetting::addPingAlertEmail('first@example.com', $admin->id);
    $second = SystemSetting::addPingAlertEmail('second@example.com', $admin->id);

    expect($first['ok'])->toBeTrue()
        ->and($second['ok'])->toBeFalse()
        ->and(SystemSetting::pingAlertEmailAddresses())->toBe(['first@example.com']);
});

test('duplicate ping alert emails are rejected across admins', function () {
    $adminOne = User::factory()->create(['is_admin' => true]);
    $adminTwo = User::factory()->create(['is_admin' => true]);

    SystemSetting::addPingAlertEmail('shared@example.com', $adminOne->id);
    $result = SystemSetting::addPingAlertEmail('shared@example.com', $adminTwo->id);

    expect($result['ok'])->toBeFalse()
        ->and(SystemSetting::pingAlertEmailAddresses())->toBe(['shared@example.com']);
});

test('ping alert emails can be removed', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    SystemSetting::addPingAlertEmail('temp@example.com', $admin->id);
    $result = SystemSetting::removePingAlertEmail('temp@example.com');

    expect($result['ok'])->toBeTrue()
        ->and(SystemSetting::pingAlertEmailAddresses())->toBe([]);
});

test('invalid emails are rejected', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $result = SystemSetting::addPingAlertEmail('not-an-email', $admin->id);

    expect($result['ok'])->toBeFalse();
});

test('admins can add and remove ping alert emails from the settings screen', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Volt::test('admin.settings')
        ->set('pingAlertEmail', 'me@example.com')
        ->call('addPingAlertEmail')
        ->assertDispatched('toast')
        ->assertSet('pingAlertEmail', '')
        ->assertSet('pingAlertEmails.0.email', 'me@example.com');

    expect(SystemSetting::pingAlertEmailAddresses())->toBe(['me@example.com']);

    Volt::test('admin.settings')
        ->call('removePingAlertEmail', 'me@example.com')
        ->assertDispatched('toast')
        ->assertSet('pingAlertEmails', []);

    expect(SystemSetting::pingAlertEmailAddresses())->toBe([]);
});

test('the admin ping alert email template shows both parties contact info', function () {
    $pinger = User::factory()->create([
        'email' => 'party-a@example.com',
        'phone_number' => '555-0101',
        'address' => '12 Guest Lane',
    ]);
    $artisan = User::factory()->create([
        'role' => 'artisan',
        'email' => 'party-b@example.com',
        'phone_number' => '555-0202',
        'work' => 'Plumber',
        'address' => '45 Workshop Road',
    ]);
    $engagement = createTestPing($pinger, $artisan, ['title' => 'Emergency pipe repair']);

    $html = (new AdminPingAlertMail($pinger, $artisan, $engagement))->render();

    expect($html)
        ->toContain('party-a@example.com')
        ->toContain('party-b@example.com')
        ->toContain('555-0101')
        ->toContain('555-0202')
        ->toContain('Plumber')
        ->toContain('Emergency pipe repair');
});

test('the ping alert subject includes both names', function () {
    $pinger = User::factory()->create(['name' => 'Alice Ping']);
    $artisan = User::factory()->create(['role' => 'artisan', 'name' => 'Bob Builder']);
    $engagement = createTestPing($pinger, $artisan);

    $subject = (new AdminPingAlertMail($pinger, $artisan, $engagement))->envelope()->subject;

    expect($subject)->toContain('Alice Ping')->toContain('Bob Builder');
});
