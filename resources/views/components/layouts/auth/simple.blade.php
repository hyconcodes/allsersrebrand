<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-[#f7f1fe] dark:bg-zinc-950 antialiased">
    <!-- Ambient background -->
    <div class="fixed inset-0 z-[-1] overflow-hidden pointer-events-none">
        <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[1100px] h-[700px] bg-[var(--color-brand-purple)]/[0.07] rounded-[100%] blur-3xl"></div>
        <div class="absolute -bottom-32 -right-20 w-[680px] h-[680px] bg-[var(--color-brand-purple)]/[0.08] rounded-[100%] blur-3xl"></div>
        <div class="absolute top-1/2 left-0 w-[420px] h-[420px] bg-purple-300/10 rounded-full blur-3xl hidden lg:block"></div>
    </div>

    <div class="flex min-h-svh flex-col items-center justify-center gap-8 p-4 sm:p-6 md:p-10 relative">
        <!-- Logo Header -->
        <a href="{{ route('home') }}" class="flex items-center gap-3.5 group" wire:navigate>
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white dark:bg-zinc-900 shadow-md shadow-purple-500/10 ring-1 ring-black/[0.06] dark:ring-white/10 text-[var(--color-brand-purple)] group-hover:shadow-lg group-hover:scale-[1.02] transition-all duration-200">
                <x-app-logo-icon class="size-5 fill-current" />
            </div>
            <div class="flex flex-col text-left">
                <span class="text-[17px] font-extrabold tracking-tight leading-none text-zinc-900 dark:text-white">{{ config('app.name', 'Allsers') }}</span>
                <span class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 tracking-widest uppercase">Your World of Services</span>
            </div>
        </a>

        <!-- Card -->
        <div class="auth-card flex w-full max-w-[480px] flex-col bg-white dark:bg-zinc-900 p-6 sm:p-8 md:p-9 rounded-[28px] shadow-[0_20px_60px_-20px_rgba(106,17,203,0.22)] ring-1 ring-black/[0.06] dark:ring-white/10">
            {{ $slot }}
        </div>

        <p class="text-center text-xs text-zinc-500 dark:text-zinc-400 max-w-[480px] leading-relaxed">
            {{ __('Secure & trusted by 10,000+ users') }} &middot; <a href="{{ route('privacy') }}" class="underline decoration-zinc-300 hover:text-zinc-700 dark:hover:text-zinc-200">{{ __('Privacy') }}</a> &middot; <a href="{{ route('terms') }}" class="underline decoration-zinc-300 hover:text-zinc-700 dark:hover:text-zinc-200">{{ __('Terms') }}</a>
        </p>
    </div>
    @fluxScripts
    <x-pwa-scripts />
</body>

</html>
