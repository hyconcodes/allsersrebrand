<?php

use App\Mail\AdminPingAlertMail;
use App\Mail\ServiceInquiryMail;
use App\Models\Conversation;
use App\Models\Engagement;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * The finder page orders artisans by haversine distance (acos/cos/sin/radians),
 * which SQLite lacks. Register PHP equivalents so the component can render.
 */
function registerSqLiteTrigFunctionsForFinder(): void
{
    if (DB::getDriverName() !== 'sqlite') {
        return;
    }

    $pdo = DB::connection()->getPdo();
    $pdo->sqliteCreateFunction('acos', fn ($v) => acos($v), 1);
    $pdo->sqliteCreateFunction('cos', fn ($v) => cos($v), 1);
    $pdo->sqliteCreateFunction('sin', fn ($v) => sin($v), 1);
    $pdo->sqliteCreateFunction('radians', fn ($v) => deg2rad($v), 1);
}

test('finder ping sends the artisan email synchronously along with the admin alert', function () {
    Mail::fake();
    registerSqLiteTrigFunctionsForFinder();

    $client = User::factory()->create();
    $artisan = User::factory()->create(['role' => 'artisan']);
    $admin = User::factory()->create(['is_admin' => true]);

    SystemSetting::addPingAlertEmail('admin-alerts@example.com', $admin->id);

    $this->actingAs($client);

    Volt::test('pages.finder')
        ->call('startInquiry', $artisan->id)
        ->set('inquiryTask', 'Fix my leaky kitchen sink faucet ASAP')
        ->set('inquiryLocation', 'Lagos, Nigeria')
        ->set('inquiryUrgency', 'high')
        ->call('submitStructuredInquiry')
        ->assertHasNoErrors();

    Mail::assertSent(ServiceInquiryMail::class, function ($mail) use ($artisan) {
        return collect($mail->to)->pluck('address')->contains($artisan->email);
    });

    Mail::assertSent(AdminPingAlertMail::class, function ($mail) {
        return collect($mail->to)->pluck('address')->contains('admin-alerts@example.com');
    });

    expect(Engagement::where('artisan_id', $artisan->id)->where('status', 'pending')->exists())->toBeTrue();
});

test('conversation ping emails the artisan synchronously along with the admin alert', function () {
    Mail::fake();

    $client = User::factory()->create();
    $artisan = User::factory()->create(['role' => 'artisan']);
    $admin = User::factory()->create(['is_admin' => true]);

    SystemSetting::addPingAlertEmail('admin-alerts@example.com', $admin->id);

    $conversation = Conversation::create(['last_message_at' => now()]);
    $conversation->users()->attach([$client->id, $artisan->id]);

    $this->actingAs($client);

    Volt::test('pages.chat', ['conversation' => $conversation])
        ->call('sendInquiry', 'Fix my leaky kitchen sink faucet')
        ->assertHasNoErrors();

    Mail::assertSent(ServiceInquiryMail::class, function ($mail) use ($artisan) {
        return collect($mail->to)->pluck('address')->contains($artisan->email);
    });

    Mail::assertSent(AdminPingAlertMail::class, function ($mail) {
        return collect($mail->to)->pluck('address')->contains('admin-alerts@example.com');
    });

    expect(Engagement::where('artisan_id', $artisan->id)->where('title', 'Fix my leaky kitchen sink faucet')->exists())->toBeTrue();
});

test('ping mails are never queued', function () {
    Mail::fake();

    $client = User::factory()->create();
    $artisan = User::factory()->create(['role' => 'artisan']);
    $admin = User::factory()->create(['is_admin' => true]);

    SystemSetting::addPingAlertEmail('admin-alerts@example.com', $admin->id);

    $conversation = Conversation::create(['last_message_at' => now()]);
    $conversation->users()->attach([$client->id, $artisan->id]);

    $this->actingAs($client);

    Volt::test('pages.chat', ['conversation' => $conversation])
        ->call('sendInquiry', 'Fix my leaky kitchen sink faucet');

    Mail::assertNothingQueued();
});
