<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col items-center text-center">
            <div class="flex size-12 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/20 mb-3">
                <flux:icon name="lock-closed" class="size-6" />
            </div>
            <h1 class="text-[26px] font-extrabold tracking-tight leading-none text-zinc-900 dark:text-white">
                {{ __('Reset your password') }}
            </h1>
            <p class="mt-2 text-[13.5px] font-medium leading-relaxed text-zinc-500 dark:text-zinc-400 max-w-[38ch]">
                {{ __('Enter the 8-character code we emailed you, then choose a new password.') }}
            </p>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40 px-4 py-3 text-center text-[13px] font-medium text-red-700 dark:text-red-300">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.code.update') }}" class="flex flex-col gap-5" x-data="{ password: '' }">
            @csrf

            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-800/50 p-4 flex flex-col gap-4">
                <flux:input name="email" value="{{ old('email', $email) }}" :label="__('Email address')" type="email" required autocomplete="email" />

                <div class="flex flex-col gap-2">
                    <label for="code" class="block text-[11px] font-extrabold tracking-[0.14em] uppercase text-zinc-600 dark:text-zinc-400">{{ __('Reset code') }}</label>
                    <input id="code" name="code" type="text" maxlength="8" required value="{{ old('code') }}" inputmode="text" autocomplete="one-time-code" placeholder="AB12CD34"
                        class="w-full rounded-xl border-2 border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3.5 text-center text-[18px] font-extrabold tracking-[0.28em] uppercase text-zinc-900 dark:text-white placeholder:tracking-normal placeholder:text-sm placeholder:font-medium placeholder:normal-case outline-none transition focus:border-[var(--color-brand-purple)] focus:ring-4 focus:ring-[var(--color-brand-purple)]/10">
                    <p class="mt-1 text-center text-[11px] font-medium text-zinc-500 dark:text-zinc-400">{{ __('Code expires in 15 minutes. Check spam if you don’t see it.') }}</p>
                </div>

                <div class="flex flex-col gap-2">
                    <flux:input name="password" :label="__('New password')" type="password" required autocomplete="new-password" placeholder="At least 8 characters" viewable x-model="password" />
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" x-show="password.length > 0" x-cloak x-transition>
                        <div class="h-full rounded-full transition-all duration-500" :class="password.length < 8 ? 'bg-amber-500' : password.length < 12 ? 'bg-[var(--color-brand-purple)]' : 'bg-emerald-500'" :style="'width: ' + Math.min(password.length * 8.5, 100) + '%'"></div>
                    </div>
                </div>

                <flux:input name="password_confirmation" :label="__('Confirm new password')" type="password" required autocomplete="new-password" placeholder="Repeat your new password" viewable />
            </div>

            <flux:button type="submit" variant="primary" class="w-full h-[48px] rounded-xl bg-[var(--color-brand-purple)] hover:bg-[#5a0eb0] text-[15px] font-extrabold tracking-tight shadow-lg shadow-[var(--color-brand-purple)]/20 hover:shadow-xl hover:-translate-y-[1px] active:translate-y-0 transition-all" data-test="reset-password-button">
                <span class="flex items-center justify-center gap-2">
                    {{ __('Reset password') }}
                    <flux:icon name="check" class="size-4" />
                </span>
            </flux:button>

            <p class="text-center text-[13px] font-medium text-zinc-600 dark:text-zinc-400">
                {{ __('Didn’t get the code?') }}
                <flux:link :href="route('password.request')" class="font-bold text-zinc-700 dark:text-zinc-300 hover:text-[var(--color-brand-purple)] hover:underline underline-offset-4" wire:navigate>{{ __('Request a new one') }}</flux:link>
            </p>

            <p class="text-center text-[13px] font-medium text-zinc-600 dark:text-zinc-400 -mt-2">
                <flux:link :href="route('login')" class="font-bold text-zinc-700 dark:text-zinc-300 hover:text-[var(--color-brand-purple)] hover:underline underline-offset-4" wire:navigate>{{ __('Back to log in') }}</flux:link>
            </p>
        </form>
    </div>
</x-layouts.auth>
