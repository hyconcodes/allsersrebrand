<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuestProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_only_basic_profile_fields(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'guest']));

        Livewire::test('settings.profile')
            ->assertSee('Account')
            ->assertSee('Country / Currency Region')
            ->assertDontSee('Phone Number')
            ->assertDontSee('Work / Job Title')
            ->assertDontSee('Experience (Years)')
            ->assertDontSee('Work Status')
            ->assertDontSee('Gender')
            ->assertDontSee('About you');
    }

    public function test_artisan_still_sees_and_can_save_artisan_fields(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'artisan', 'work' => 'Plumber']));

        Livewire::test('settings.profile')
            ->assertSee('Phone Number')
            ->assertSee('Work / Job Title')
            ->assertSee('About you')
            ->set('work', 'Electrician')
            ->set('bio', 'New bio')
            ->set('phone_number', '08012345678')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user = auth()->user()->refresh();

        expect($user->work)->toBe('Electrician');
        expect($user->bio)->toBe('New bio');
        expect($user->phone_number)->toBe('08012345678');
    }

    public function test_guest_save_does_not_touch_artisan_fields(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => 'guest',
            'work' => 'Carpenter',
            'bio' => 'Old bio',
            'phone_number' => '08012345678',
            'address' => '12 Main St',
            'gender' => 'Male',
            'work_status' => 'freelancer',
            'experience_year' => 5,
        ]));

        Livewire::test('settings.profile')
            ->set('name', 'Updated Name')
            ->set('country_code', 'US')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user = auth()->user()->refresh();

        expect($user->name)->toBe('Updated Name');
        expect($user->country_code)->toBe('US');

        // Hidden artisan fields must never be validated or written for guests.
        expect($user->work)->toBe('Carpenter');
        expect($user->bio)->toBe('Old bio');
        expect($user->phone_number)->toBe('08012345678');
        expect($user->address)->toBe('12 Main St');
        expect($user->gender)->toBe('Male');
        expect($user->work_status)->toBe('freelancer');
        expect((int) $user->experience_year)->toBe(5);
    }
}
