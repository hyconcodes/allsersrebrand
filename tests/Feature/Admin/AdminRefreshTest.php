<?php

use App\Models\SystemSetting;
use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->create([
        'is_admin' => true,
        'created_at' => now()->subDay(),
    ]);

    $this->actingAs($this->adminUser);
});

test('every admin page renders its refresh button', function () {
    $routes = [
        'admin.dashboard' => 'refreshDashboard',
        'admin.pings' => 'refreshPings',
        'admin.reports' => 'refreshReports',
        'admin.settings' => 'refreshSettings',
    ];

    foreach ($routes as $route => $action) {
        $this->get(route($route))
            ->assertOk()
            ->assertSeeHtml('wire:click="'.$action.'"');
    }
});

test('refreshDashboard reloads data, re-inits charts and toasts', function () {
    Volt::test('admin.dashboard')
        ->call('refreshDashboard')
        ->assertDispatched('admin-charts-updated')
        ->assertDispatched('toast', type: 'success', title: 'Refreshed');
});

test('refreshPings re-queries, re-inits the chart and toasts', function () {
    Volt::test('admin.pings')
        ->call('refreshPings')
        ->assertDispatched('ping-charts-updated')
        ->assertDispatched('toast', type: 'success', title: 'Pings Refreshed');
});

test('refreshReports re-renders counts and lists and toasts', function () {
    Volt::test('admin.reports')
        ->call('refreshReports')
        ->assertDispatched('toast', type: 'success', title: 'Reports Refreshed');
});

test('refreshSettings re-reads settings from the database and toasts', function () {
    // Another admin changed the driver while this page was open
    SystemSetting::set('mail_driver', 'mailtrap');

    Volt::test('admin.settings')
        ->call('refreshSettings')
        ->assertSet('mailDriver', 'mailtrap')
        ->assertDispatched('toast', type: 'success', title: 'Settings Refreshed');
});
