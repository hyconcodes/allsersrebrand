{{-- Inline edit box for a comment/reply (uses editingCommentContent from HandlesComments) --}}
<div class="mt-1.5" @click.stop>
    <textarea wire:model="editingCommentContent" rows="2" maxlength="1000"
        class="w-full rounded-xl border border-purple-500/50 bg-transparent px-2.5 py-1.5 text-xs text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-purple-500"></textarea>
    <div class="mt-1 flex items-center gap-2">
        <button type="button" wire:click="updateComment"
            class="rounded-full bg-[var(--color-brand-purple)] px-3.5 py-1 text-[11px] font-bold text-white transition-opacity hover:opacity-90">
            {{ __('Save') }}
        </button>
        <button type="button" wire:click="cancelCommentEdit"
            class="rounded-full bg-zinc-200 dark:bg-zinc-800 px-3.5 py-1 text-[11px] font-bold text-zinc-600 dark:text-zinc-300 transition-colors hover:bg-zinc-300 dark:hover:bg-zinc-700">
            {{ __('Cancel') }}
        </button>
    </div>
</div>
