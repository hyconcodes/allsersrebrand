<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<x-settings.layout :active="'appearance'">
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 p-4 space-y-4">
        <div>
            <h3 class="font-bold text-zinc-900 dark:text-zinc-100">{{ __('Appearance') }}</h3>
            <p class="text-sm text-zinc-500">{{ __('Choose how Allsers looks to you.') }}</p>
        </div>

        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" class="w-full">
            <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
        </flux:radio.group>
    </div>
</x-settings.layout>
