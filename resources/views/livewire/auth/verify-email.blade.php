<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col items-center text-center">
            <div class="flex size-12 items-center justify-center rounded-2xl bg-[var(--color-brand-purple)] text-white shadow-lg shadow-[var(--color-brand-purple)]/20 mb-3">
                <flux:icon name="envelope-open" class="size-6" />
            </div>
            <h1 class="text-[26px] font-extrabold tracking-tight leading-none text-zinc-900 dark:text-white">
                {{ __('Verify your email') }}
            </h1>
            <p class="mt-2 text-[13.5px] font-medium leading-relaxed text-zinc-500 dark:text-zinc-400 max-w-[34ch]">
                {{ __('Enter the 8-character code we sent to') }} <span class="font-bold text-zinc-700 dark:text-zinc-200">{{ auth()->user()->email }}</span>
            </p>
        </div>

        @if (session('status') == 'verification-code-sent')
            <div class="rounded-2xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-center">
                <p class="text-[13px] font-bold text-emerald-700 dark:text-emerald-300">{{ __('A new verification code has been sent to your email address.') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40 px-4 py-3 text-center text-[13px] font-medium text-red-700 dark:text-red-300">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="/email/verify" class="flex flex-col gap-4">
            @csrf
            <input type="hidden" name="email" value="{{ auth()->user()->email }}">

            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-800/50 p-4">
                <label for="code" class="mb-2 block text-[11px] font-extrabold tracking-[0.14em] uppercase text-zinc-600 dark:text-zinc-400">{{ __('Verification code') }}</label>
                <input id="code" name="code" type="text" maxlength="8" required value="{{ old('code') }}" inputmode="text" autocomplete="one-time-code" placeholder="AB12CD34"
                    class="w-full rounded-xl border-2 border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3.5 text-center text-[18px] font-extrabold tracking-[0.28em] uppercase text-zinc-900 dark:text-white placeholder:tracking-normal placeholder:text-sm placeholder:font-medium placeholder:normal-case outline-none transition focus:border-[var(--color-brand-purple)] focus:ring-4 focus:ring-[var(--color-brand-purple)]/10">
                <p class="mt-2 text-center text-[11px] font-medium text-zinc-500 dark:text-zinc-400">{{ __('Code expires in a few minutes. Check spam if you don’t see it.') }}</p>
            </div>

            <flux:button type="submit" variant="primary" class="w-full h-[48px] rounded-xl bg-[var(--color-brand-purple)] hover:bg-[#5a0eb0] text-[15px] font-extrabold tracking-tight shadow-lg shadow-[var(--color-brand-purple)]/20 hover:shadow-xl hover:-translate-y-[1px] active:translate-y-0 transition-all">
                <span class="flex items-center justify-center gap-2">
                    {{ __('Verify email') }}
                    <flux:icon name="check-circle" class="size-4" />
                </span>
            </flux:button>
        </form>

        <div class="flex flex-col gap-3">
            <form method="POST" action="/email/verification-notification">
                @csrf
                <button type="submit" class="w-full rounded-xl border-2 border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3 text-sm font-bold text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors flex items-center justify-center gap-2">
                    <flux:icon name="arrow-path" class="size-4" />
                    {{ __('Resend verification code') }}
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-center text-[13px] font-bold text-zinc-500 hover:text-[var(--color-brand-purple)] hover:underline underline-offset-4 transition-colors" data-test="logout-button">
                    {{ __('Log out') }}
                </button>
            </form>
        </div>
    </div>
</x-layouts.auth>
