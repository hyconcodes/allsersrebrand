<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col items-center text-center">
            <div class="flex size-12 items-center justify-center rounded-2xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 shadow-lg shadow-zinc-900/10 mb-3">
                <flux:icon name="shield-check" class="size-6" />
            </div>
            <h1 class="text-[26px] font-extrabold tracking-tight leading-none text-zinc-900 dark:text-white">
                {{ __('Confirm your password') }}
            </h1>
            <p class="mt-2 text-[13.5px] font-medium leading-relaxed text-zinc-500 dark:text-zinc-400 max-w-[34ch]">
                {{ __('This is a secure area. Please confirm your password before continuing.') }}
            </p>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-5">
            @csrf

            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-800/50 p-4">
                <flux:input name="password" :label="__('Password')" type="password" required autocomplete="current-password" placeholder="••••••••" viewable />
                <p class="mt-2 text-[11px] font-medium text-zinc-500 dark:text-zinc-400">{{ __('We need to verify it’s really you.') }}</p>
            </div>

            <flux:button variant="primary" type="submit" class="w-full h-[48px] rounded-xl bg-[var(--color-brand-purple)] hover:bg-[#5a0eb0] text-[15px] font-extrabold tracking-tight shadow-lg shadow-[var(--color-brand-purple)]/20 hover:shadow-xl hover:-translate-y-[1px] active:translate-y-0 transition-all" data-test="confirm-password-button">
                <span class="flex items-center justify-center gap-2">
                    {{ __('Confirm & continue') }}
                    <flux:icon name="arrow-right" class="size-4" />
                </span>
            </flux:button>

            <p class="text-center">
                <a href="{{ route('dashboard') }}" wire:navigate class="text-[13px] font-bold text-zinc-500 hover:text-[var(--color-brand-purple)] hover:underline underline-offset-4 transition-colors">{{ __('Cancel & go back') }}</a>
            </p>
        </form>
    </div>
</x-layouts.auth>
