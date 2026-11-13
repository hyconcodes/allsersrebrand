<?php

use App\Support\VoiceConsent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $showModal = false;

    public string $voiceLanguage = 'en';

    public ?string $voiceConsent = null;

    /**
     * Shown to artisans on every authenticated page (i.e. from login
     * onwards) until they make a choice in Settings → Profile. Mirrors
     * the Voice Call Notices section there; both save through
     * VoiceConsent::save() so the behaviour is identical.
     */
    public function mount(): void
    {
        $user = Auth::user();

        if ($user !== null && $user->isArtisan() && $user->voice_call_consent === null) {
            $this->showModal = true;
        }
    }

    public function dismiss(): void
    {
        $this->showModal = false;
    }

    public function saveVoiceCallConsent(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->isArtisan()) {
            return;
        }

        $this->validate([
            'voiceLanguage' => ['required', 'string', Rule::in(['en'])],
            'voiceConsent' => ['required', 'string', Rule::in(['accepted', 'declined'])],
        ], [
            'voiceConsent.required' => __('Choose Accept or Decline, then save.'),
            'voiceLanguage.in' => __('Only English is available for now — more languages are coming soon.'),
        ]);

        VoiceConsent::save($user, $this->voiceConsent, $this->voiceLanguage);

        $this->showModal = false;

        $this->dispatch(
            'toast',
            type: 'success',
            title: __('Voice call notices updated'),
            message: $this->voiceConsent === 'accepted'
                ? __('You will be called when someone requests your services on Allsers.')
                : __('You will not receive voice call notices.'),
        );
    }
}; ?>

<div>
    @if ($showModal)
        <flux:modal wire:model="showModal" class="w-full sm:max-w-xl">
        <div class="space-y-5">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ __('Voice Call Notices') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ __('When a customer requests your services, we place a short automated call to your phone telling you who asked, for what job, and to log in and accept it — so you never miss work while away from the app.') }}
                </p>
            </div>

            <!-- Default language -->
            <div>
                <flux:label>{{ __('Default language for calls') }}</flux:label>
                <flux:select wire:model="voiceLanguage" class="mt-1">
                    <option value="en">{{ __('English') }}</option>
                    <option value="ha" disabled>{{ __('Hausa — coming soon') }}</option>
                    <option value="yo" disabled>{{ __('Yoruba — coming soon') }}</option>
                    <option value="ig" disabled>{{ __('Igbo — coming soon') }}</option>
                    <option value="pcm" disabled>{{ __('Nigerian Pidgin — coming soon') }}</option>
                </flux:select>
                <p class="mt-1.5 text-xs italic text-zinc-500">{{ __('Other African languages are coming soon.') }}</p>
                @error('voiceLanguage')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <!-- Accept / Decline -->
            <div>
                <flux:label>{{ __('Do you agree to receive voice call notices?') }}</flux:label>
                <flux:radio.group wire:model="voiceConsent" variant="segmented" class="mt-1">
                    <flux:radio value="accepted" icon="phone">{{ __('Accept') }}</flux:radio>
                    <flux:radio value="declined" icon="phone-x-mark">{{ __('Decline') }}</flux:radio>
                </flux:radio.group>
                @error('voiceConsent')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <flux:button wire:click="saveVoiceCallConsent" wire:loading.attr="disabled" variant="primary"
                    icon="check" class="w-full sm:w-auto justify-center">
                    <span wire:loading.remove wire:target="saveVoiceCallConsent">{{ __('Save') }}</span>
                    <span wire:loading wire:target="saveVoiceCallConsent">{{ __('Saving...') }}</span>
                </flux:button>
                <p class="text-xs text-zinc-500 sm:mr-auto">
                    {{ __('Calls are placed 8:00 AM – 8:00 PM (WAT) only. You can change your choice anytime in Settings → Profile.') }}
                </p>
            </div>
        </div>
        </flux:modal>
    @endif
</div>
