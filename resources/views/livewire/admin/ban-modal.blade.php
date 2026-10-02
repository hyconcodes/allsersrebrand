<?php

use App\Models\User;
use App\Mail\UserBannedMail;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;

new class extends Component {
    public bool $showBanModal = false;
    public ?int $banUserId = null;
    public string $banUserName = '';
    public string $bannedUntil = '';
    public string $banReason = '';

    #[On('open-ban-modal')]
    public function openBanModal($userId, $userName = '')
    {
        $this->banUserId = (int) $userId;
        // Resolve name if not provided
        if ($userName) {
            $this->banUserName = $userName;
        } else {
            $u = User::find($this->banUserId);
            $this->banUserName = $u?->name ?? 'User #'.$this->banUserId;
        }
        $this->bannedUntil = '';
        $this->banReason = '';
        $this->showBanModal = true;
    }

    public function setPreset(int $days)
    {
        $this->bannedUntil = now()->addDays($days)->format('Y-m-d');
    }

    public function banUser()
    {
        $maxDate = now()->addDays(365)->format('Y-m-d');
        $this->validate([
            'bannedUntil' => 'required|date|after:today|before_or_equal:'.$maxDate,
            'banReason' => 'required|string|min:10|max:500',
        ]);

        $user = User::find($this->banUserId);
        if (! $user) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'User not found.');
            return;
        }
        if ($user->isAdmin()) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Cannot ban an admin.');
            return;
        }

        $bannedUntil = Carbon::parse($this->bannedUntil)->endOfDay();

        $user->update([
            'banned_until' => $bannedUntil,
            'banned_reason' => $this->banReason,
            'banned_by' => auth()->id(),
            'banned_at' => now(),
        ]);

        try {
            Mail::to($user->email)->send(new UserBannedMail($user, $bannedUntil, $this->banReason));
        } catch (\Throwable $e) {
            \Log::error('Failed to send ban email: '.$e->getMessage());
        }

        $this->showBanModal = false;
        $this->bannedUntil = '';
        $this->banReason = '';
        $this->dispatch('toast', type: 'success', title: 'User Banned', message: "Banned until {$bannedUntil->format('M d, Y')}");
        $this->dispatch('user-banned');
    }
}; ?>

<div>
    <flux:modal wire:model="showBanModal" class="sm:max-w-lg" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Ban User') }}</flux:heading>
                <flux:subheading>
                    @if($banUserName) {{ __('Banning: :name', ['name' => $banUserName]) }} @else {{ __('Temporarily suspend account') }} @endif
                </flux:subheading>
            </div>

            <div class="space-y-4">
                <!-- Presets -->
                <div>
                    <flux:label>{{ __('Quick select') }}</flux:label>
                    <div class="flex flex-wrap gap-2 mt-1.5">
                        <button type="button" wire:click="setPreset(7)" class="px-3 py-1.5 rounded-full border text-xs font-semibold transition-colors {{ $bannedUntil === now()->addDays(7)->format('Y-m-d') ? 'bg-purple-600 text-white border-purple-600' : 'bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}">7 {{ __('days') }}</button>
                        <button type="button" wire:click="setPreset(14)" class="px-3 py-1.5 rounded-full border text-xs font-semibold transition-colors {{ $bannedUntil === now()->addDays(14)->format('Y-m-d') ? 'bg-purple-600 text-white border-purple-600' : 'bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}">14 {{ __('days') }}</button>
                        <button type="button" wire:click="setPreset(30)" class="px-3 py-1.5 rounded-full border text-xs font-semibold transition-colors {{ $bannedUntil === now()->addDays(30)->format('Y-m-d') ? 'bg-purple-600 text-white border-purple-600' : 'bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}">30 {{ __('days') }}</button>
                        <button type="button" wire:click="setPreset(90)" class="px-3 py-1.5 rounded-full border text-xs font-semibold transition-colors {{ $bannedUntil === now()->addDays(90)->format('Y-m-d') ? 'bg-purple-600 text-white border-purple-600' : 'bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}">90 {{ __('days') }}</button>
                        <button type="button" wire:click="setPreset(365)" class="px-3 py-1.5 rounded-full border text-xs font-semibold transition-colors {{ $bannedUntil === now()->addDays(365)->format('Y-m-d') ? 'bg-purple-600 text-white border-purple-600' : 'bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}">365 {{ __('days') }}</button>
                    </div>
                </div>

                <!-- Calendar picker: flatpickr date-only -->
                <div x-data="{
                    fp: null,
                    init() {
                        const el = this.$refs.banDate;
                        const maxDate = new Date(); maxDate.setDate(maxDate.getDate()+365);
                        this.fp = flatpickr(el, {
                            dateFormat: 'Y-m-d',
                            altInput: true,
                            altFormat: 'F j, Y',
                            minDate: 'today',
                            maxDate: maxDate,
                            allowInput: true,
                            disableMobile: true,
                            onChange: (sel, str) => { $wire.set('bannedUntil', str); }
                        });
                        this.$watch('$wire.bannedUntil', (v) => {
                            if (v && this.fp && this.fp.input.value !== v) this.fp.setDate(v, true);
                            if (!v && this.fp) this.fp.clear();
                        });
                        // sync preset changes
                        Livewire.hook('commit', ({component, succeed}) => {
                            succeed(() => {
                                if (component.name === $wire.__instance?.effects?.name || true) {
                                    const val = $wire.get('bannedUntil');
                                    if (val && this.fp && this.fp.input.value !== val) this.fp.setDate(val, true);
                                }
                            });
                        });
                    }
                }">
                    <flux:label>{{ __('Banned until (date)') }} *</flux:label>
                    <flux:input x-ref="banDate" wire:model="bannedUntil" placeholder="{{ __('Select end date — max 365 days') }}" icon="calendar-days" readonly />
                    @error('bannedUntil') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-zinc-400 mt-1">{{ __('Max 365 days from today. Time set to end of day.') }}</p>
                </div>

                <div>
                    <flux:label>{{ __('Reason') }} *</flux:label>
                    <flux:textarea wire:model="banReason" rows="3" placeholder="{{ __('Explain why — user will receive this by email') }}" />
                    @error('banReason') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-2 justify-end">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="banUser">{{ __('Ban User') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
