<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $username = '';
    public string $profile_picture = '';
    public $photo; // New upload
    public string $gender = '';
    public string $work = '';
    public string $bio = '';
    public string $experience_year = '';
    public string $work_status = '';
    public string $phone_number = '';
    public string $address = '';
    public string $country_code = 'NG';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->username = $user->username ?? '';
        $this->profile_picture = $user->profile_picture ?? '';
        $this->gender = $user->gender ?? '';
        $this->work = $user->work ?? '';
        $this->bio = $user->bio ?? '';
        $this->experience_year = $user->experience_year ? (string) $user->experience_year : '';
        $this->work_status = $user->work_status ?? '';
        $this->phone_number = $user->phone_number ?? '';
        $this->address = $user->address ?? '';
        $this->country_code = $user->country_code ?? 'NG';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'photo' => ['nullable', 'image', 'max:1024'], // 1MB Max
            'gender' => ['nullable', 'string', 'max:50'],
            'work' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:500'],
            'experience_year' => ['nullable', 'integer', 'min:0'],
            'work_status' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
        ]);

        if ($this->photo) {
            $user->profile_picture = $this->photo->store('profilePics', 'public');
        }

        $user->fill([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'gender' => $validated['gender'],
            'work' => $validated['work'],
            'bio' => $validated['bio'],
            'experience_year' => $validated['experience_year'],
            'work_status' => $validated['work_status'],
            'phone_number' => $validated['phone_number'],
            'address' => $validated['address'],
            'country_code' => $validated['country_code'],
        ]);
        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your profile information')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">

            <!-- Profile Picture -->
            <div class="flex items-center gap-6">
                <div class="relative">
                    @if ($photo)
                        <div
                            class="w-20 h-20 rounded-full bg-gray-200 overflow-hidden ring-2 ring-[var(--color-brand-purple)] ring-offset-2">
                            <img src="{{ $photo->temporaryUrl() }}" alt="Profile Photo" class="w-full h-full object-cover">
                        </div>
                    @elseif ($profile_picture)
                        <div class="w-20 h-20 rounded-full bg-gray-200 overflow-hidden">
                            <img src="{{ route('images.show', ['path' => $profile_picture]) }}" alt="Profile Photo"
                                class="w-full h-full object-cover">
                        </div>
                    @else
                        <div
                            class="w-20 h-20 rounded-full bg-[var(--color-brand-purple)]/10 flex items-center justify-center text-[var(--color-brand-purple)] text-2xl font-bold">
                            {{ auth()->user()->initials() }}
                        </div>
                    @endif

                    <label for="photo-upload"
                        class="absolute bottom-0 right-0 p-1.5 bg-white rounded-full shadow-md cursor-pointer hover:bg-gray-50 border border-gray-200">
                        <flux:icon name="camera" class="w-4 h-4 text-gray-500" />
                        <input id="photo-upload" type="file" wire:model="photo" class="hidden" accept="image/*">
                    </label>
                </div>

                <div class="flex flex-col">
                    <h3 class="font-medium text-gray-900">{{ __('Profile Photo') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Update your profile picture.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus
                    autocomplete="name" />
                <flux:input wire:model="username" :label="__('Username')" type="text" autocomplete="username" />
            </div>

            <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" readonly
                disabled />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:input wire:model="phone_number" :label="__('Phone Number')" type="text" />
                <flux:input wire:model="address" :label="__('Address')" type="text" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:input wire:model="gender" :label="__('Gender')" type="text" />

                <flux:select wire:model="work_status" :label="__('Work Status')" placeholder="Select status">
                    <option value="employed">Employed</option>
                    <option value="unemployed">Unemployed</option>
                    <option value="student">Student</option>
                    <option value="freelancer">Freelancer</option>
                </flux:select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:input wire:model="work" :label="__('Work / Job Title')" type="text" />
                <flux:input wire:model="experience_year" :label="__('Experience (Years)')" type="number"
                    min="0" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:select wire:model="country_code" :label="__('Country / Currency Region')"
                    placeholder="Select country">
                    <option value="NG">Nigeria (₦)</option>
                    <option value="US">United States ($)</option>
                    <option value="GB">United Kingdom (£)</option>
                    <option value="EU">European Union (€)</option>
                    <option value="GH">Ghana (₵)</option>
                    <option value="KE">Kenya (KSh)</option>
                    <option value="ZA">South Africa (R)</option>
                    <option value="CA">Canada (C$)</option>
                    <option value="AU">Australia (A$)</option>
                    <option value="NL">Netherlands (€)</option>
                </flux:select>
                <div class="flex items-center pt-6">
                    <p class="text-xs text-zinc-500 italic">
                        {{ __('This determines the currency symbol shown on your posts.') }}
                    </p>
                </div>
            </div>

            <flux:textarea wire:model="bio" :label="__('Bio')" rows="4"
                placeholder="Tell us about yourself..." />


            <div>
                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !auth()->user()->hasVerifiedEmail())
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer"
                                wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <div class="mt-10 border-t border-gray-100 pt-10">
            <h3 class="text-lg font-medium text-gray-900">{{ __('Notifications') }}</h3>
            <p class="mt-1 text-sm text-gray-600">
                {{ __('Stay updated with real-time push notifications for messages and inquiries.') }}
            </p>

            <div class="mt-6">
                <div x-data="{
                    isSubscribed: false,
                    async checkSubscription() {
                        window.OneSignalDeferred = window.OneSignalDeferred || [];
                        OneSignalDeferred.push(async (OneSignal) => {
                            this.isSubscribed = OneSignal.Notifications.permission;
                        });
                    },
                    async subscribe() {
                        window.OneSignalDeferred = window.OneSignalDeferred || [];
                        OneSignalDeferred.push(async (OneSignal) => {
                            await OneSignal.Notifications.requestPermission();
                            this.isSubscribed = OneSignal.Notifications.permission;
                
                            if (this.isSubscribed && window.Flux) {
                                Flux.toast({
                                    variant: 'success',
                                    heading: 'Success',
                                    text: 'You will now receive real-time notifications!'
                                });
                            }
                        });
                    }
                }" x-init="checkSubscription()"
                    class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <flux:button @click="subscribe()" icon="bell" variant="outline"
                        x-text="isSubscribed ? '{{ __('Notifications are active') }}' : '{{ __('Enable Browser Notifications') }}'"
                        x-bind:class="isSubscribed ? '!bg-green-50 !text-green-700 !border-green-100 sm:w-auto w-full' :
                            'sm:w-auto w-full'"
                        x-bind:disabled="isSubscribed">
                    </flux:button>

                    <template x-if="isSubscribed">
                        <div
                            class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-green-50/50 border border-green-100 sm:border-0 sm:bg-transparent">
                            <flux:icon name="check-circle" variant="solid" class="size-4 text-green-600" />
                            <span class="text-sm text-green-700 font-semibold uppercase tracking-tight">
                                {{ __('Live') }}
                            </span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- <livewire:settings.delete-user-form /> --}}
    </x-settings.layout>
</section>
