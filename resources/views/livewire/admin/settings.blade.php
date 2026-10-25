<?php

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $mailDriver = 'smtp';

    public string $pingAlertEmail = '';

    /** @var array<int, array{email: string, admin_id: int}> */
    public array $pingAlertEmails = [];

    public function mount(): void
    {
        if (!auth()->user()?->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        $this->mailDriver = SystemSetting::get('mail_driver', config('mail.default')) ?? 'smtp';

        if (!in_array($this->mailDriver, SystemSetting::MAIL_DRIVERS, true)) {
            $this->mailDriver = 'smtp';
        }

        $this->pingAlertEmails = SystemSetting::getPingAlertEmails();
    }

    public function save(): void
    {
        if (!auth()->user()?->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        if (!in_array($this->mailDriver, SystemSetting::MAIL_DRIVERS, true)) {
            $this->dispatch('toast', type: 'error', title: 'Invalid option', message: 'Unknown mail driver.');

            return;
        }

        if ($this->mailDriver === 'mailtrap' && blank(config('services.mailtrap.token'))) {
            $this->dispatch(
                'toast',
                type: 'warning',
                title: 'Mailtrap not configured',
                message: 'Set MAILTRAP_API_TOKEN in .env before switching to Mailtrap.',
            );

            return;
        }

        try {
            SystemSetting::set('mail_driver', $this->mailDriver);
            SystemSetting::applyMailDriver();

            $this->dispatch(
                'toast',
                type: 'success',
                title: 'Mail settings saved',
                message: $this->mailDriver === 'mailtrap'
                    ? 'All emails are now sent through the Mailtrap API.'
                    : 'All emails are now sent through the server SMTP mailer.',
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Could not save mail settings.', ['exception' => $e->getMessage()]);

            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Could not save mail settings.');
        }
    }

    public function with(): array
    {
        $settingsTableExists = false;

        try {
            $settingsTableExists = Schema::hasTable('settings');
        } catch (\Throwable) {
            $settingsTableExists = false;
        }

        return [
            'settingsTableExists' => $settingsTableExists,
            'mailtrapTokenConfigured' => filled(config('services.mailtrap.token')),
            'smtpHost' => config('mail.mailers.smtp.host'),
            'smtpUser' => config('mail.mailers.smtp.username'),
            'mailtrapEndpoint' => config('services.mailtrap.endpoint'),
            'activeDriver' => config('mail.default'),
        ];
    }

    public function addPingAlertEmail(): void
    {
        if (!auth()->user()?->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        $result = SystemSetting::addPingAlertEmail($this->pingAlertEmail, auth()->id());

        if ($result['ok']) {
            $this->pingAlertEmail = '';
            $this->pingAlertEmails = SystemSetting::getPingAlertEmails();
        }

        $this->dispatch(
            'toast',
            type: $result['ok'] ? 'success' : 'error',
            title: $result['ok'] ? 'Email added' : 'Could not add email',
            message: $result['message'],
        );
    }

    public function removePingAlertEmail(string $email): void
    {
        if (!auth()->user()?->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        $result = SystemSetting::removePingAlertEmail($email);

        if ($result['ok']) {
            $this->pingAlertEmails = SystemSetting::getPingAlertEmails();
        }

        $this->dispatch(
            'toast',
            type: $result['ok'] ? 'success' : 'error',
            title: $result['ok'] ? 'Email removed' : 'Could not remove email',
            message: $result['message'],
        );
    }
}
?>
<div class="max-w-3xl mx-auto">
    <x-top-bar>
        <x-slot:left>@include('partials.admin-top-tabs')</x-slot:left>
    </x-top-bar>

    <div class="px-4 py-8 space-y-6">
    <!-- Header -->
    <div class="min-w-0">
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 italic uppercase tracking-tighter">
            {{ __('System Settings') }}
        </h1>
        <p class="text-sm text-zinc-500">{{ __('Platform-wide configuration') }}</p>
    </div>

    <!-- Mail delivery -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-5 sm:p-6 space-y-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-900 dark:text-zinc-100">
                    {{ __('Email delivery') }}
                </h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ __('Choose where ALL outgoing emails are sent. Applies to every email: verification codes, password resets, service inquiries, moderation notices, welcome emails and more.') }}
                </p>
            </div>
            <span class="shrink-0 inline-flex items-center gap-1.5 text-xs font-bold uppercase px-2.5 py-1 rounded-full {{ $activeDriver === 'mailtrap' ? 'bg-purple-100 text-purple-700 dark:bg-purple-500/15 dark:text-purple-300' : 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300' }}">
                <span class="size-1.5 rounded-full {{ $activeDriver === 'mailtrap' ? 'bg-purple-500' : 'bg-green-500' }}"></span>
                {{ $activeDriver === 'mailtrap' ? __('Mailtrap API') : __('Server SMTP') }}
            </span>
        </div>

        @if (! $settingsTableExists)
            <div class="rounded-xl bg-yellow-50 dark:bg-yellow-500/10 border border-yellow-200 dark:border-yellow-500/20 p-4 text-sm text-yellow-800 dark:text-yellow-300">
                {{ __('The settings table does not exist yet. Run php artisan migrate first.') }}
            </div>
        @else
            <flux:radio.group wire:model.live="mailDriver" variant="segmented" name="mail-driver">
                <flux:radio value="smtp" icon="envelope">{{ __('Server email (SMTP)') }}</flux:radio>
                <flux:radio value="mailtrap" icon="cloud">{{ __('Mailtrap API') }}</flux:radio>
            </flux:radio.group>

            <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 p-4 space-y-2 text-sm">
                @if ($mailDriver === 'smtp')
                    <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Server email (SMTP)') }}</p>
                    <p class="text-zinc-600 dark:text-zinc-400">
                        {{ __('Sends through your configured mail server') }}:
                        <span class="font-mono text-xs">{{ $smtpHost }} @if ($smtpUser) &middot; {{ $smtpUser }} @endif</span>
                    </p>
                @else
                    <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Mailtrap API') }}</p>
                    <p class="text-zinc-600 dark:text-zinc-400">
                        {{ __('Sends via HTTPS') }}: <span class="font-mono text-xs">{{ $mailtrapEndpoint }}</span>
                    </p>
                    <p class="text-zinc-600 dark:text-zinc-400">
                        {{ __('API token') }}:
                        @if ($mailtrapTokenConfigured)
                            <span class="inline-flex items-center gap-1 font-semibold text-green-600 dark:text-green-400">
                                <flux:icon name="check-circle" class="size-3.5" /> {{ __('configured') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 font-semibold text-red-600 dark:text-red-400">
                                <flux:icon name="exclamation-triangle" class="size-3.5" /> {{ __('MAILTRAP_API_TOKEN is empty') }}
                            </span>
                        @endif
                    </p>
                @endif
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:button wire:click="save" wire:loading.attr="disabled" variant="primary" icon="check">
                    <span wire:loading.remove wire:target="save">{{ __('Save changes') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving...') }}</span>
                </flux:button>
            </div>
        @endif
    </div>

    <!-- Ping alert recipients -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-5 sm:p-6 space-y-5">
        <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-900 dark:text-zinc-100">
                {{ __('Ping alerts') }}
            </h2>
            <p class="mt-1 text-sm text-zinc-500">
                {{ __('Every admin here gets an instant email copy of each ping (service inquiry), including the pinger’s and the artisan’s business and contact info. Each admin can register one email. Leave the list empty to disable admin copies.') }}
            </p>
        </div>

        @if (! $settingsTableExists)
            <div class="rounded-xl bg-yellow-50 dark:bg-yellow-500/10 border border-yellow-200 dark:border-yellow-500/20 p-4 text-sm text-yellow-800 dark:text-yellow-300">
                {{ __('The settings table does not exist yet. Run php artisan migrate first.') }}
            </div>
        @else
            @if (count($pingAlertEmails) > 0)
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                    @foreach ($pingAlertEmails as $entry)
                        <li class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0 flex items-center gap-2.5">
                                <flux:icon name="envelope" class="size-4 shrink-0 text-zinc-400" />
                                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ $entry['email'] }}</span>
                                @if ((int) ($entry['admin_id'] ?? 0) === (int) auth()->id())
                                    <span class="shrink-0 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 dark:bg-purple-500/15 dark:text-purple-300">{{ __('you') }}</span>
                                @endif
                            </div>
                            <flux:button wire:click="removePingAlertEmail('{{ $entry['email'] }}')" wire:loading.attr="disabled" variant="ghost" size="sm" icon="trash" class="text-red-600 dark:text-red-400">
                                {{ __('Remove') }}
                            </flux:button>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-dashed border-zinc-300 dark:border-zinc-700 p-4 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No ping alert emails registered yet.') }}
                </div>
            @endif

            <form wire:submit="addPingAlertEmail" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                <div class="flex-1 min-w-0">
                    <flux:input wire:model="pingAlertEmail" type="email" :label="__('Add your email')" placeholder="you@example.com" />
                </div>
                <flux:button type="submit" wire:loading.attr="disabled" variant="primary" icon="plus" class="shrink-0">
                    <span wire:loading.remove wire:target="addPingAlertEmail">{{ __('Add email') }}</span>
                    <span wire:loading wire:target="addPingAlertEmail">{{ __('Adding...') }}</span>
                </flux:button>
            </form>
        @endif
    </div>
    </div>
</div>
