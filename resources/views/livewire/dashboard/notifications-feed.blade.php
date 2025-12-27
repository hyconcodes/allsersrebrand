<?php

use Livewire\Volt\Component;

new class extends Component {
    public $notifications;

    public function mount()
    {
        $this->loadNotifications();
    }

    public function loadNotifications()
    {
        $this->notifications = auth()->user()->notifications()->latest()->take(50)->get();
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

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ __('Notifications') }}</h2>
        @if(auth()->user()->unreadNotifications->count() > 0)
            <button wire:click="markAllAsRead" class="text-xs font-bold text-[var(--color-brand-purple)] hover:underline">
                {{ __('Mark all as read') }}
            </button>
        @endif
    </div>

    <div class="space-y-3">
        @forelse($notifications as $notification)
            @php
                $data = $notification->data;
                $isRead = $notification->read_at !== null;
                $type = $data['type'] ?? '';
            @endphp
            <div 
                class="group relative flex items-start gap-4 p-4 rounded-2xl border transition-all @if($isRead) bg-white dark:bg-zinc-900 border-zinc-100 dark:border-zinc-800 @else bg-purple-50/30 dark:bg-purple-900/10 border-purple-100 dark:border-purple-800/50 @endif"
            >
                <!-- Notification Icon/Avatar -->
                <div class="shrink-0 relative">
                    <div class="size-10 rounded-full bg-zinc-100 flex items-center justify-center text-zinc-600 font-bold text-sm">
                        {{ strtoupper(substr($data['liker_name'] ?? $data['commenter_name'] ?? $data['replier_name'] ?? $data['sender_name'] ?? 'A', 0, 1)) }}
                    </div>
                    <div class="absolute -bottom-1 -right-1 size-5 rounded-full border-2 border-white dark:border-zinc-900 flex items-center justify-center text-white
                        @if($type === 'like') bg-red-500 @elseif($type === 'comment') bg-blue-500 @elseif($type === 'inquiry') bg-green-500 @else bg-purple-500 @endif
                    ">
                        @if($type === 'like')
                            <flux:icon name="heart" class="size-3 fill-current" />
                        @elseif($type === 'comment')
                            <flux:icon name="chat-bubble-left" class="size-3" />
                        @elseif($type === 'reply')
                             <flux:icon name="arrow-uturn-left" class="size-3" />
                        @elseif($type === 'inquiry')
                            <flux:icon name="paper-airplane" class="size-3" />
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
                                {{ $data['liker_name'] ?? $data['commenter_name'] ?? $data['replier_name'] ?? $data['sender_name'] ?? __('Someone') }}
                            </span>
                            {{ $data['message'] ?? __('interacted with you') }}
                        </p>
                        <span class="text-[10px] text-zinc-500 mt-1">{{ $notification->created_at->diffForHumans() }}</span>
                    </div>

                    <!-- Action Link -->
                    @if(isset($data['post_id']))
                        <button 
                            @click="$dispatch('open-post-detail', { postId: {{ $data['post_id'] }} }); @if(!$isRead) $wire.markAsRead('{{ $notification->id }}') @endif"
                            class="mt-2 text-xs font-bold text-[var(--color-brand-purple)] hover:underline text-left"
                        >
                            {{ __('View post') }}
                        </button>
                    @elseif(isset($data['sender_id']))
                         <a 
                            href="{{ route('user.profile', $data['sender_id']) }}"
                            wire:navigate
                            @if(!$isRead) wire:click="markAsRead('{{ $notification->id }}')" @endif
                            class="mt-2 inline-block text-xs font-bold text-[var(--color-brand-purple)] hover:underline"
                        >
                            {{ __('View profile') }}
                        </a>
                    @endif
                </div>

                <!-- Delete Button -->
                <button 
                    wire:click="deleteNotification('{{ $notification->id }}')"
                    class="opacity-0 group-hover:opacity-100 transition-opacity p-1 text-zinc-400 hover:text-red-500"
                >
                    <flux:icon name="x-mark" class="size-4" />
                </button>

                @if(!$isRead)
                    <div class="absolute top-4 right-4 size-2 rounded-full bg-[var(--color-brand-purple)]"></div>
                @endif
            </div>
        @empty
            <div class="bg-white dark:bg-zinc-900 rounded-2xl p-12 shadow-sm border border-zinc-200 dark:border-zinc-800 text-center">
                <div class="size-16 bg-zinc-50 dark:bg-zinc-800 rounded-full flex items-center justify-center mx-auto mb-4">
                    <flux:icon name="bell" class="size-8 text-zinc-300" />
                </div>
                <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 mb-2">{{ __('Quiet for now') }}</h3>
                <p class="text-zinc-500 max-w-xs mx-auto text-sm">{{ __('When people like, comment, or reply to you, we\'ll let you know here.') }}</p>
            </div>
        @endforelse
    </div>

    <!-- Integrate Post Detail for viewing -->
    <livewire:dashboard.post-detail />
</div>