<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col items-center text-center">
            <div class="flex size-12 items-center justify-center rounded-2xl bg-amber-500 text-white shadow-lg shadow-amber-500/20 mb-3">
                <flux:icon name="key" class="size-6" />
            </div>
            <h1 class="text-[26px] font-extrabold tracking-tight leading-none text-zinc-900 dark:text-white">
                {{ __('Forgot password?') }}
            </h1>
            <p class="mt-2 text-[13.5px] font-medium leading-relaxed text-zinc-500 dark:text-zinc-400 max-w-[34ch]">
                {{ __('No worries — enter your email and we’ll send you a reset link.') }}
            </p>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
            @csrf

            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-800/50 p-4">
                <flux:input name="email" :label="__('Email address')" type="email" required autofocus placeholder="you@example.com" />
                <p class="mt-2 text-[11px] font-medium text-zinc-500 dark:text-zinc-400">{{ __('We’ll send the reset link to this address if it exists in our system.') }}</p>
            </div>

            <flux:button variant="primary" type="submit" class="w-full h-[48px] rounded-xl bg-[var(--color-brand-purple)] hover:bg-[#5a0eb0] text-[15px] font-extrabold tracking-tight shadow-lg shadow-[var(--color-brand-purple)]/20 hover:shadow-xl hover:-translate-y-[1px] active:translate-y-0 transition-all" data-test="email-password-reset-link-button">
                <span class="flex items-center justify-center gap-2">
                    <flux:icon name="paper-airplane" class="size-4" />
                    {{ __('Send reset link') }}
                </span>
            </flux:button>

            <p class="text-center text-[13px] font-medium text-zinc-600 dark:text-zinc-400">
                {{ __('Remembered it?') }}
                <flux:link :href="route('login')" class="font-extrabold text-[var(--color-brand-purple)] hover:underline underline-offset-4" wire:navigate>{{ __('Back to log in') }}</flux:link>
            </p>
        </form>
    </div>
</x-layouts.auth>
