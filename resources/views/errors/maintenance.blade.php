<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Allsers') }} - Under Maintenance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }
    </style>
</head>

<body
    class="bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 flex items-center justify-center min-h-screen p-6">

    <div class="max-w-xl w-full text-center space-y-8">

        <!-- Animated Icon -->
        <div class="relative mx-auto size-24 flex items-center justify-center">
            <div class="absolute inset-0 bg-purple-500/20 rounded-full animate-ping"></div>
            <div
                class="relative bg-white dark:bg-zinc-800 rounded-full p-6 shadow-xl border border-zinc-200 dark:border-zinc-700">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-10 text-purple-600 dark:text-purple-400">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" />
                </svg>
            </div>
        </div>

        <div class="space-y-4">
            <h1 class="text-4xl font-black tracking-tight text-zinc-900 dark:text-white">
                We're currently under maintenance
            </h1>
            <p class="text-lg text-zinc-600 dark:text-zinc-400 max-w-md mx-auto">
                We're upgrading our systems to bring you a better experience. We'll be back online shortly.
            </p>
        </div>

        <div
            class="inline-flex items-center gap-2 px-4 py-2 bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300 rounded-full text-sm font-bold animate-pulse">
            <span class="relative flex h-2 w-2">
                <span
                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-purple-500"></span>
            </span>
            <span>Working on updates</span>
        </div>

        <div class="pt-8 border-t border-zinc-200 dark:border-zinc-800">
            <p class="text-sm text-zinc-400">
                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
            </p>
        </div>
    </div>

</body>

</html>
