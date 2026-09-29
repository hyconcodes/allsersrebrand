@props([
    'title',
    'description',
])

<div class="flex w-full flex-col items-center gap-2 text-center">
    <h2 class="text-[20px] font-extrabold tracking-tight leading-none text-zinc-900 dark:text-white">{{ $title }}</h2>
    <p class="text-[13px] font-medium leading-relaxed text-zinc-500 dark:text-zinc-400 max-w-[36ch]">{{ $description }}</p>
</div>
