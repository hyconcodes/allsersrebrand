<?php

use Livewire\Volt\Component;

new class extends Component {
    public $notifications;
    public $perPage = 10;
    public $hasMore = false;

    public function mount()
    {
        $this->loadNotifications();
    }

    public function loadNotifications()
    {
        $query = auth()->user()->notifications()->latest();
        $this->notifications = $query->take($this->perPage)->get();
        $this->hasMore = auth()->user()->notifications()->count() > $this->perPage;
    }

    public function loadMore()
    {
        $this->perPage += 10;
        $this->loadNotifications();
    }

    public function markAsRead($id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $this->loadNotifications();
        $this->dispatch('notifications-updated');
    }

    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        $this->loadNotifications();
        $this->dispatch('notifications-updated');
    }

    public function deleteNotification($id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->delete();
        $this->loadNotifications();
        $this->dispatch('notifications-updated');
    }
}; ?>

<div class="space-y-0">
    <x-top-bar title="{{ __('Notifications') }}" />

    @if (auth()->user()->unreadNotifications->count() > 0)
        <div class="flex justify-end px-4 py-2">
            <button wire:click="markAllAsRead" class="text-xs font-bold text-[var(--color-brand-purple)] hover:underline">
                {{ __('Mark all as read') }}
            </button>
        </div>
    @endif

    <div class="space-y-3">
        @forelse($notifications as $notification)
            @php
                $data = $notification->data;
                $isRead = $notification->read_at !== null;
                $type = $data['type'] ?? '';
            @endphp
            <div
                class="group relative flex items-start gap-4 px-4 py-3 border-b border-zinc-200 dark:border-zinc-800 @if (!$isRead) bg-zinc-50 dark:bg-zinc-900/50 @endif">
                <!-- Notification Icon/Avatar -->
                <div class="shrink-0 relative">
                    <div
                        class="size-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 font-bold text-sm">
                        {{ strtoupper(substr($data['liker_name'] ?? ($data['commenter_name'] ?? ($data['replier_name'] ?? ($data['sender_name'] ?? ($data['tagger_name'] ?? 'A')))), 0, 1)) }}
                    </div>
                    <div
                        class="absolute -bottom-1 -right-1 size-5 rounded-full border-2 border-white dark:border-zinc-900 flex items-center justify-center text-white
                        @if ($type === 'like') bg-red-500 @elseif($type === 'comment') bg-blue-500 @elseif($type === 'inquiry') bg-green-500 @elseif($type === 'message') bg-purple-500 @elseif($type === 'user_tagged') bg-pink-500 @else bg-zinc-500 @endif
                    ">
                        @if ($type === 'like')
                            <flux:icon name="heart" class="size-3 fill-current" />
                        @elseif($type === 'comment')
                            <flux:icon name="chat-bubble-left" class="size-3" />
                        @elseif($type === 'reply')
                            <flux:icon name="arrow-uturn-left" class="size-3" />
                        @elseif($type === 'inquiry')
                            <flux:icon name="paper-airplane" class="size-3" />
                        @elseif($type === 'message')
                            <flux:icon name="chat-bubble-left-right" class="size-3" />
                        @elseif($type === 'user_tagged')
                            <flux:icon name="at-symbol" class="size-3" />
                        @else
                            <flux:icon name="bell" class="size-3" />
                        @endif
                    </div>
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <div class="flex flex-col">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            <span class="font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $data['liker_name'] ?? ($data['commenter_name'] ?? ($data['replier_name'] ?? ($data['sender_name'] ?? ($data['tagger_name'] ?? __('Someone'))))) }}
                            </span>
                            {{ $data['message'] ?? __('interacted with you') }}
                        </p>
                        <span
                            class="text-xs text-zinc-500 mt-1">{{ $notification->created_at->diffForHumans() }}</span>
                    </div>

                    <!-- Action Link -->
                    @if (isset($data['post_id']))
                        <button
                            @click="$dispatch('open-post-detail', { postId: {{ $data['post_id'] }} }); @if (!$isRead) $wire.markAsRead('{{ $notification->id }}') @endif"
                            class="mt-2 text-xs font-bold text-[var(--color-brand-purple)] hover:underline text-left">
                            {{ __('View post') }}
                        </button>
                    @elseif($type === 'message' && isset($data['conversation_id']))
                        <a href="{{ route('chat', $data['conversation_id']) }}" wire:navigate
                            @if (!$isRead) wire:click="markAsRead('{{ $notification->id }}')" @endif
                            class="mt-2 inline-block text-xs font-bold text-[var(--color-brand-purple)] hover:underline">
                            {{ __('Reply now') }}
                        </a>
                    @elseif(isset($data['sender_id']))
                        <a href="{{ route('user.profile', \App\Models\User::find($data['sender_id']) ?? $data['sender_id']) }}"
                            wire:navigate
                            @if (!$isRead) wire:click="markAsRead('{{ $notification->id }}')" @endif
                            class="mt-2 inline-block text-xs font-bold text-[var(--color-brand-purple)] hover:underline">
                            {{ __('View profile') }}
                        </a>
                    @endif
                </div>

                <!-- Delete Button -->
                <button wire:click="deleteNotification('{{ $notification->id }}')"
                    class="opacity-0 group-hover:opacity-100 transition-opacity p-1 text-zinc-400 hover:text-red-500">
                    <flux:icon name="x-mark" class="size-4" />
                </button>

                @if (!$isRead)
                    <div class="absolute top-4 right-4 size-2 rounded-full bg-[var(--color-brand-purple)]"></div>
                @endif
            </div>
        @empty
            <div class="px-4 py-12 text-center border-b border-zinc-200 dark:border-zinc-800">
                <div class="size-16 bg-zinc-100 dark:bg-zinc-900 rounded-full flex items-center justify-center mx-auto mb-4">
                    <flux:icon name="bell" class="size-8 text-zinc-600" />
                </div>
                <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 mb-2">{{ __('Quiet for now') }}</h3>
                <p class="text-zinc-500 max-w-xs mx-auto text-sm">
                    {{ __('When people like, comment, or reply to you, we\'ll let you know here.') }}</p>
            </div>
        @endforelse

        @if ($hasMore)
            <div class="pt-4 flex justify-center">
                <button wire:click="loadMore" wire:loading.attr="disabled"
                    class="px-6 py-2 rounded-full border border-zinc-200 dark:border-zinc-800 text-xs font-bold text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors flex items-center gap-2">
                    <span wire:loading.remove wire:target="loadMore">{{ __('Load More') }}</span>
                    <span wire:loading wire:target="loadMore">{{ __('Loading...') }}</span>
                    <flux:icon wire:loading.remove wire:target="loadMore" name="chevron-down" class="size-3" />
                </button>
            </div>
        @endif
    </div>

    <!-- Integrate Post Detail for viewing -->
    <livewire:dashboard.post-detail />
</div>
