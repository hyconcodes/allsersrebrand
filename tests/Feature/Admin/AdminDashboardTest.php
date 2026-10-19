<?php

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->create([
        'is_admin' => true,
        'created_at' => now()->subDay(),
    ]);

    $this->actingAs($this->adminUser);
});

test('admin control center renders', function () {
    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Control Center');
});

test('users tab always shows the newest 10 users with pagination', function () {
    foreach (range(1, 12) as $i) {
        User::factory()->create([
            'username' => sprintf('member%02d', $i),
            'created_at' => now()->subSeconds(30 - $i),
        ]);
    }

    $component = Volt::test('admin.dashboard');

    $managedUsers = $component->viewData('managedUsers');

    expect($managedUsers)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($managedUsers->total())->toBe(13)
        ->and($managedUsers->perPage())->toBe(10)
        ->and($managedUsers)->toHaveCount(10)
        ->and($managedUsers->hasPages())->toBeTrue();

    $component
        ->assertSee('member12')
        ->assertSee('member03')
        ->assertDontSee('member02')
        ->assertDontSee('member01')
        ->assertDontSee('Enter at least 3 characters to search users');

    // Page 2 shows the rest (oldest members + the admin)
    $component->call('gotoPage', 2);

    $component
        ->assertSee('member02')
        ->assertSee('member01')
        ->assertDontSee('member12');
});

test('searching users filters the list and resets back to page 1', function () {
    foreach (range(1, 15) as $i) {
        User::factory()->create([
            'username' => sprintf('target%02d', $i),
            'created_at' => now()->subSeconds(40 - $i),
        ]);
    }

    $component = Volt::test('admin.dashboard')->call('gotoPage', 2);

    $component->set('userSearchQuery', 'target');

    // 15 matches -> 2 pages; only a page reset puts the newest match on screen
    $component
        ->assertSee('target15')
        ->assertDontSee('target01')
        ->assertSet('paginators', ['page' => 1]);
});

test('filtering by year updates chart data and dispatches the chart refresh', function () {
    User::factory()->create([
        'role' => 'artisan',
        'username' => 'chartartizan',
        'created_at' => '2025-03-10 10:00:00',
    ]);
    User::factory()->create(['created_at' => '2025-03-20 10:00:00']);
    User::factory()->create(['created_at' => '2024-06-01 10:00:00']);

    $component = Volt::test('admin.dashboard')
        ->set('filterYear', '2025')
        ->assertDispatched('admin-charts-updated');

    $growth = $component->get('userGrowthChartData');
    expect($growth['labels'])->toHaveCount(12)
        ->and(array_sum($growth['data']))->toBe(2);

    $roles = $component->get('roleDistributionData');
    expect($roles['labels'])->toBe(['Artisans', 'Guests'])
        ->and($roles['data'])->toBe([1, 1]);

    // Adding the month filter switches the growth chart to daily buckets
    $component->set('filterMonth', '3')
        ->assertDispatched('admin-charts-updated');

    $growth = $component->get('userGrowthChartData');
    expect($growth['labels'])->toHaveCount(31)
        ->and($growth['data'])->toHaveCount(31)
        ->and($growth['data'][9])->toBe(1)   // Mar 10 (artisan)
        ->and($growth['data'][19])->toBe(1);  // Mar 20 (guest)
});

test('the chart data element the JavaScript reads is rendered inside the component', function () {
    Volt::test('admin.dashboard')
        ->assertSeeHtml('id="admin-chart-data"')
        ->assertSeeHtml('data-growth=')
        ->assertSeeHtml('data-roles=');
});
