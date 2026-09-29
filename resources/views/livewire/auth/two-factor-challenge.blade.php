<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div x-cloak x-data="{
                showRecoveryInput: @js($errors->has('recovery_code')),
                code: '',
                recovery_code: '',
                toggleInput() {
                    this.showRecoveryInput = !this.showRecoveryInput;
                    this.code = '';
                    this.recovery_code = '';
                    $dispatch('clear-2fa-auth-code');
                    $nextTick(() => {
                        this.showRecoveryInput
                            ? this.$refs.recovery_code?.focus()
                            : $dispatch('focus-2fa-auth-code');
                    });
                },
            }">

            <div x-show="!showRecoveryInput" class="flex flex-col items-center text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/20 mb-3">
                    <flux:icon name="device-phone-mobile" class="size-6" />
                </div>
                <h1 class="text-[26px] font-extrabold tracking-tight leading-none text-zinc-900 dark:text-white">{{ __('Authentication code') }}</h1>
                <p class="mt-2 text-[13.5px] font-medium leading-relaxed text-zinc-500 dark:text-zinc-400 max-w-[34ch]">{{ __('Enter the 6-digit code from your authenticator app.') }}</p>
            </div>

            <div x-show="showRecoveryInput" class="flex flex-col items-center text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-amber-500 text-white shadow-lg shadow-amber-500/20 mb-3">
                    <flux:icon name="key" class="size-6" />
                </div>
                <h1 class="text-[26px] font-extrabold tracking-tight leading-none text-zinc-900 dark:text-white">{{ __('Recovery code') }}</h1>
                <p class="mt-2 text-[13.5px] font-medium leading-relaxed text-zinc-500 dark:text-zinc-400 max-w-[36ch]">{{ __('Enter one of your emergency recovery codes to access your account.') }}</p>
            </div>

            <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-6">
                @csrf

                <div class="flex flex-col gap-4">
                    <div x-show="!showRecoveryInput">
                        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-800/50 p-4">
                            <p class="mb-3 text-center text-[11px] font-extrabold tracking-[0.14em] uppercase text-zinc-600 dark:text-zinc-400">{{ __('6-digit code') }}</p>
                            <div class="flex items-center justify-center">
                                <flux:otp x-model="code" length="6" name="code" label="OTP Code" label:sr-only class="mx-auto" />
                            </div>
                            <p class="mt-3 text-center text-[11px] font-medium text-zinc-500 dark:text-zinc-400">{{ __('Open your authenticator app to find the code.') }}</p>
                        </div>
                    </div>

                    <div x-show="showRecoveryInput">
                        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-800/50 p-4">
                            <flux:input type="text" name="recovery_code" x-ref="recovery_code" x-bind:required="showRecoveryInput" autocomplete="one-time-code" x-model="recovery_code" placeholder="xxxx-xxxx-xxxx" :label="__('Recovery code')" />
                            <p class="mt-2 text-[11px] font-medium text-zinc-500 dark:text-zinc-400">{{ __('Recovery codes were shown when you enabled two-factor authentication.') }}</p>
                        </div>
                        @error('recovery_code')
                            <p class="mt-2 text-center text-sm font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <flux:button variant="primary" type="submit" class="w-full h-[48px] rounded-xl bg-[var(--color-brand-purple)] hover:bg-[#5a0eb0] text-[15px] font-extrabold tracking-tight shadow-lg shadow-[var(--color-brand-purple)]/20 hover:shadow-xl hover:-translate-y-[1px] active:translate-y-0 transition-all">
                        <span class="flex items-center justify-center gap-2">
                            {{ __('Continue') }}
                            <flux:icon name="arrow-right" class="size-4" />
                        </span>
                    </flux:button>

                    <p class="text-center text-[13px] font-medium text-zinc-600 dark:text-zinc-400">
                        <span class="text-zinc-500">{{ __('Or you can') }}</span>
                        <button type="button" class="font-extrabold text-[var(--color-brand-purple)] hover:underline underline-offset-4" @click="toggleInput()">
                            <span x-show="!showRecoveryInput">{{ __('log in using a recovery code') }}</span>
                            <span x-show="showRecoveryInput">{{ __('log in using an authentication code') }}</span>
                        </button>
                    </p>
                </div>
            </form>
        </div>
    </div>
</x-layouts.auth>
