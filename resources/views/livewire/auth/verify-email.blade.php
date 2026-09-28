<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2 text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-black">
                {{ __('Verify your email') }}
            </h1>
            <p class="text-sm text-zinc-500">
                {{ __('Enter the 8-character verification code we sent to your email address.') }}
            </p>
        </div>

        @if (session('status') == 'verification-code-sent')
            <div class="text-center text-sm font-medium text-[var(--color-brand-purple)]">
                {{ __('A new verification code has been sent to your email address.') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="/email/verify" class="w-full space-y-4">
            @csrf

            <input type="hidden" name="email" value="{{ auth()->user()->email }}">

            <div class="space-y-2">
                <label for="code" class="text-sm font-medium text-zinc-700">{{ __('Verification code') }}</label>
                <input id="code" name="code" type="text" maxlength="8" required
                    value="{{ old('code') }}"
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base text-zinc-900 outline-none ring-0 transition focus:border-[var(--color-brand-purple)]"
                    placeholder="AB12CD34" autocomplete="one-time-code">
            </div>

            <flux:button type="submit" variant="primary" class="w-full bg-[var(--color-brand-purple)] hover:bg-[var(--color-brand-purple)]/90">
                {{ __('Verify email') }}
            </flux:button>
        </form>

        <div class="flex flex-col items-center justify-between space-y-4">
            <form method="POST" action="/email/verification-notification" class="w-full">
                @csrf
                <button type="submit"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50">
                    {{ __('Resend verification code') }}
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <button type="submit"
                    class="w-full text-sm text-zinc-500 hover:text-[var(--color-brand-purple)] hover:underline cursor-pointer"
                    data-test="logout-button">
                    {{ __('Log out') }}
                </button>
            </form>
        </div>
    </div>
</x-layouts.auth>