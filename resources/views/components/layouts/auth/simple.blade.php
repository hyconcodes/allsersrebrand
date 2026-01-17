<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-[var(--color-brand-light)] antialiased">
    <!-- Background ambient shapes -->
    <div class="fixed inset-0 z-[-1] overflow-hidden pointer-events-none">
        <div
            class="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[600px] bg-[var(--color-brand-purple)]/5 rounded-[100%] blur-3xl opacity-50">
        </div>
        <div
            class="absolute bottom-0 right-0 w-[500px] h-[500px] bg-[var(--color-brand-purple)]/10 rounded-[100%] blur-3xl">
        </div>
    </div>

    <div class="flex min-h-svh flex-col items-center justify-center gap-8 p-6 md:p-10 relative">
        <!-- Logo Header -->
        <a href="{{ route('home') }}" class="flex items-center gap-4" wire:navigate>
            <div
                class="flex h-12 w-12 items-center justify-center rounded-xl bg-white shadow-sm ring-1 ring-black/5 text-[var(--color-brand-purple)] transition-transform hover:scale-105 active:scale-95 duration-200">
                <x-app-logo-icon class="size-4 fill-current" />
            </div>
            <div class="flex flex-col">
                <span class="text-lg font-bold tracking-tight text-black">{{ config('app.name', 'Allsers') }}</span>
                <span class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Your World of
                    Services</span>
            </div>
        </a>

        <!-- Card -->
        <div
            class="auth-card flex w-full max-w-[420px] flex-col gap-6 bg-white p-8 md:p-10 rounded-[2rem] shadow-2xl shadow-[var(--color-brand-purple)]/10 ring-1 ring-black/5">
            {{ $slot }}
        </div>
    </div>
    @fluxScripts
    <x-pwa-scripts />
</body>

</html>
