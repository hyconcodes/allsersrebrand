<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <!-- Header -->
        <div class="flex flex-col items-center text-center">
            <div class="flex size-12 items-center justify-center rounded-2xl bg-[var(--color-brand-purple)] text-white shadow-lg shadow-[var(--color-brand-purple)]/20 mb-3">
                <flux:icon name="user-plus" class="size-6" />
            </div>
            <h1 class="text-[26px] font-extrabold tracking-tight leading-none text-zinc-900 dark:text-white">
                {{ __('Create your account') }}
            </h1>
            <p class="mt-2 text-[13.5px] font-medium leading-relaxed text-zinc-500 dark:text-zinc-400 max-w-[32ch]">
                {{ __('Join Allsers — find trusted pros or offer your services in seconds.') }}
            </p>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form id="register-form" method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-5"
            x-data="{ password: '', showPw: false, agree: false }">
            @csrf

            <!-- Personal details -->
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-800/50 p-4">
                <div class="flex items-center gap-2.5 mb-3.5">
                    <span class="flex size-7 items-center justify-center rounded-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">
                        <flux:icon name="user" class="size-3.5" />
                    </span>
                    <h2 class="text-[11px] font-extrabold tracking-[0.14em] uppercase text-zinc-700 dark:text-zinc-300">{{ __('Personal details') }}</h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input name="name" :label="__('Full name')" :value="old('name')" type="text" required autofocus autocomplete="name" placeholder="John Doe" />
                    <flux:input name="username" :label="__('Username')" :value="old('username')" type="text" required autocomplete="username" placeholder="johndoe" />
                </div>

                <div class="mt-4">
                    <flux:input name="email" :label="__('Email address')" :value="old('email')" type="email" required autocomplete="email" placeholder="you@example.com" />
                    <p class="mt-1.5 text-[11px] font-medium text-zinc-500 dark:text-zinc-400">{{ __('We’ll send your verification code here.') }}</p>
                </div>
            </div>

            <!-- Role selection -->
            <div>
                <p class="mb-2.5 text-[11px] font-extrabold tracking-[0.14em] uppercase text-zinc-600 dark:text-zinc-400">{{ __('I want to') }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <label class="group relative flex cursor-pointer flex-col gap-2 rounded-2xl border-2 bg-white dark:bg-zinc-900 p-4 transition-all hover:border-zinc-300 dark:hover:border-zinc-700 has-[:checked]:border-[var(--color-brand-purple)] has-[:checked]:bg-[#f7f1fe] dark:has-[:checked]:bg-[var(--color-brand-purple)]/10 has-[:checked]:shadow-sm">
                        <input type="radio" name="role" value="guest" class="sr-only peer" checked>
                        <span class="flex size-9 items-center justify-center rounded-xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 group-has-[:checked]:bg-[var(--color-brand-purple)] group-has-[:checked]:text-white transition-colors">
                            <flux:icon name="magnifying-glass" class="size-4.5" />
                        </span>
                        <span class="flex flex-col">
                            <span class="text-sm font-extrabold leading-none text-zinc-900 dark:text-white">{{ __('Hire a pro') }}</span>
                            <span class="mt-1 text-xs font-medium leading-snug text-zinc-500 dark:text-zinc-400">{{ __('Find & book services') }}</span>
                        </span>
                        <span class="absolute right-3 top-3 size-5 rounded-full border-2 border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 flex items-center justify-center peer-checked:border-[var(--color-brand-purple)] peer-checked:bg-[var(--color-brand-purple)] transition-colors">
                            <span class="size-2 rounded-full bg-white scale-0 peer-checked:scale-100 transition-transform"></span>
                        </span>
                    </label>

                    <label class="group relative flex cursor-pointer flex-col gap-2 rounded-2xl border-2 bg-white dark:bg-zinc-900 p-4 transition-all hover:border-zinc-300 dark:hover:border-zinc-700 has-[:checked]:border-[var(--color-brand-purple)] has-[:checked]:bg-[#f7f1fe] dark:has-[:checked]:bg-[var(--color-brand-purple)]/10 has-[:checked]:shadow-sm">
                        <input type="radio" name="role" value="artisan" class="sr-only peer">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 group-has-[:checked]:bg-[var(--color-brand-purple)] group-has-[:checked]:text-white transition-colors">
                            <flux:icon name="briefcase" class="size-4.5" />
                        </span>
                        <span class="flex flex-col">
                            <span class="text-sm font-extrabold leading-none text-zinc-900 dark:text-white">{{ __('Offer services') }}</span>
                            <span class="mt-1 text-xs font-medium leading-snug text-zinc-500 dark:text-zinc-400">{{ __('Get hired by clients') }}</span>
                        </span>
                        <span class="absolute right-3 top-3 size-5 rounded-full border-2 border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 flex items-center justify-center peer-checked:border-[var(--color-brand-purple)] peer-checked:bg-[var(--color-brand-purple)] transition-colors">
                            <span class="size-2 rounded-full bg-white scale-0 peer-checked:scale-100 transition-transform"></span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Security -->
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4">
                <div class="flex items-center gap-2.5 mb-3.5">
                    <span class="flex size-7 items-center justify-center rounded-full bg-zinc-900 dark:bg-white text-white dark:text-zinc-900">
                        <flux:icon name="lock-closed" class="size-3.5" />
                    </span>
                    <h2 class="text-[11px] font-extrabold tracking-[0.14em] uppercase text-zinc-700 dark:text-zinc-300">{{ __('Security') }}</h2>
                </div>

                <flux:input name="password" :label="__('Password')" type="password" required autocomplete="new-password" viewable x-model="password" placeholder="At least 8 characters" />

                <!-- Strength meter -->
                <div class="mt-3" x-show="password.length > 0" x-cloak>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[11px] font-bold tracking-wide uppercase" :class="password.length < 8 ? 'text-amber-600' : password.length < 12 ? 'text-[var(--color-brand-purple)]' : 'text-emerald-600'" x-text="password.length < 8 ? '{{ __('Weak') }}' : password.length < 12 ? '{{ __('Good') }}' : '{{ __('Strong') }}'"></span>
                        <span class="text-[11px] font-medium text-zinc-500" x-text="password.length + ' / 12+'"></span>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full transition-all duration-500" :class="password.length < 8 ? 'bg-amber-500' : password.length < 12 ? 'bg-[var(--color-brand-purple)]' : 'bg-emerald-500'" :style="'width: ' + Math.min(password.length * 8.5, 100) + '%'"></div>
                    </div>
                    <p class="mt-1.5 text-[11px] font-medium text-zinc-500 dark:text-zinc-400" x-show="password.length > 0 && password.length < 8">{{ __('Use 8+ characters with a mix of letters and numbers.') }}</p>
                </div>
            </div>

            <!-- Terms checkbox — high visibility -->
            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border-2 border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/60 px-4 py-3.5 transition-colors hover:border-zinc-300 dark:hover:border-zinc-700 has-[:checked]:border-[var(--color-brand-purple)]/30 has-[:checked]:bg-[var(--color-brand-purple)]/[0.06]">
                <input type="checkbox" required name="terms" class="mt-0.5 size-[18px] shrink-0 rounded-[6px] border-2 border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 text-[var(--color-brand-purple)] focus:ring-2 focus:ring-[var(--color-brand-purple)]/20 focus:ring-offset-0">
                <span class="text-[12.5px] font-medium leading-relaxed text-zinc-700 dark:text-zinc-300">
                    {{ __('I agree to the') }}
                    <a href="{{ route('terms') }}" target="_blank" class="font-bold text-zinc-900 dark:text-white underline decoration-zinc-300 dark:decoration-zinc-600 underline-offset-2 hover:decoration-[var(--color-brand-purple)] hover:text-[var(--color-brand-purple)]">{{ __('Terms of Service') }}</a>
                    {{ __('and') }}
                    <a href="{{ route('privacy') }}" target="_blank" class="font-bold text-zinc-900 dark:text-white underline decoration-zinc-300 dark:decoration-zinc-600 underline-offset-2 hover:decoration-[var(--color-brand-purple)] hover:text-[var(--color-brand-purple)]">{{ __('Privacy Policy') }}</a>.
                </span>
            </label>

            <flux:button type="button" variant="primary"
                class="g-recaptcha w-full h-[48px] rounded-xl bg-[var(--color-brand-purple)] hover:bg-[#5a0eb0] text-[15px] font-extrabold tracking-tight shadow-lg shadow-[var(--color-brand-purple)]/20 hover:shadow-xl hover:-translate-y-[1px] active:translate-y-0 transition-all"
                data-sitekey="{{ env('RECAPTCHA_SITE_KEY', '6Lf00Z0sAAAAADaG78Ja2OCCqzx9FXCOAsOVivcq') }}"
                data-callback="onSubmit" data-action="register" data-test="register-user-button">
                <span class="flex items-center justify-center gap-2">
                    {{ __('Create account') }}
                    <flux:icon name="arrow-right" class="size-4" />
                </span>
            </flux:button>

            <p class="text-center text-[13px] font-medium text-zinc-600 dark:text-zinc-400">
                {{ __('Already have an account?') }}
                <flux:link :href="route('login')" class="font-extrabold text-[var(--color-brand-purple)] hover:underline underline-offset-4" wire:navigate>{{ __('Log in') }}</flux:link>
            </p>

            <p class="text-center text-[11px] leading-relaxed font-medium text-zinc-400 dark:text-zinc-500">
                {{ __('You can enable location after signing in to discover pros near you — no location needed to create your account.') }}
            </p>
        </form>
    </div>

    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script>
        window.onSubmit = function(token) {
            document.getElementById('register-form').submit();
        }
    </script>
</x-layouts.auth>
