<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessage;
use Livewire\Volt\Component;
use function Livewire\Volt\layout;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

layout('components.layouts.app');

new class extends Component {
    use WithFileUploads;

    public ?Conversation $activeConversation = null;
    public string $messageText = '';
    public $photo;
    public $document;
    public $conversations = [];
    public $messages = [];

    protected $listeners = [
        'refresh' => '$refresh',
        'message-received' => 'loadMessages',
    ];

    public function refreshChat()
    {
        $oldMessageCount = count($this->messages);
        $this->loadConversations();
        $this->loadMessages();

        if (count($this->messages) > $oldMessageCount) {
            $this->dispatch('message-sent'); // Reusing same event for scroll
            $this->markAsRead();
        }
    }

    public function mount(?Conversation $conversation = null)
    {
        $this->loadConversations();

        if ($conversation && $conversation->id) {
            $this->selectConversation($conversation->id);
        }
    }

    public function loadConversations()
    {
        $this->conversations = auth()
            ->user()
            ->conversations()
            ->with(['users', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get();
    }

    public function selectConversation($id)
    {
        $this->activeConversation = Conversation::with(['users', 'messages.user'])->findOrFail($id);
        $this->loadMessages();
        $this->markAsRead();
    }

    public function loadMessages()
    {
        if ($this->activeConversation) {
            $this->messages = $this->activeConversation->messages()->with('user')->oldest()->get();
        }
    }

    public function sendMessage()
    {
        if (empty(trim($this->messageText)) && !$this->photo && !$this->document) {
            return;
        }
        if (!$this->activeConversation) {
            return;
        }

        $imagePath = null;
        if ($this->photo) {
            $imagePath = $this->photo->store('chat/images', 'cloudinary');
        }

        $documentPath = null;
        $documentName = null;
        if ($this->document) {
            $documentName = $this->document->getClientOriginalName();
            $documentPath = $this->document->store('chat/documents', 'public');
        }

        $message = Message::create([
            'conversation_id' => $this->activeConversation->id,
            'user_id' => auth()->id(),
            'content' => $this->messageText,
            'image_path' => $imagePath,
            'document_path' => $documentPath,
            'document_name' => $documentName,
        ]);

        $this->activeConversation->update([
            'last_message_at' => now(),
        ]);

        $this->activeConversation->other_user->notify(new NewMessage($message));

        $this->reset(['messageText', 'photo', 'document']);
        $this->loadMessages();
        $this->loadConversations();

        // Dispatch event for auto-scroll
        $this->dispatch('message-sent');
    }

    public function markAsRead()
    {
        if ($this->activeConversation) {
            $this->activeConversation
                ->messages()
                ->where('user_id', '!=', auth()->id())
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }
    }

    public function comingSoon()
    {
        $this->dispatch(
            'toast',
            type: 'info',
            title: 'Feature Unavailable!',
            message: 'Upgrade to premium plan to use voice and video calls on Allsers. Stay tuned!',
            // title: 'Feature Coming Soon!',
            // message: 'We are working hard to bring voice and video calls to Allsers. Stay tuned!'
        );
    }

    public function rendering($view)
    {
        $view->title(__('Chat'));
    }
}; ?>

<div wire:poll.10s="refreshChat" x-data="{ mobileView: @if ($activeConversation) 'chat' @else 'list' @endif }"
    class="h-[calc(100dvh-4rem)] md:h-[calc(100vh-4rem)] flex overflow-hidden bg-white dark:bg-zinc-900 border-x-0 border-t-0 md:border border-zinc-200 dark:border-zinc-800 md:rounded-3xl shadow-sm md:mb-4 relative rounded-lg">
    <!-- Conversation List -->
    <div class="w-full md:w-80 border-e border-zinc-200 dark:border-zinc-800 flex flex-col transition-all duration-300"
        :class="mobileView === 'list' ? 'flex' : 'hidden md:flex'">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-800">
            <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ __('Messages') }}</h2>
        </div>
        <div class="flex-1 overflow-y-auto">
            @forelse($conversations as $conv)
                @php $otherUser = $conv->other_user; @endphp
                @if (!$otherUser)
                    @continue
                @endif
                <button wire:click="selectConversation({{ $conv->id }})" @click="mobileView = 'chat'"
                    class="w-full p-4 flex items-center gap-3 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50 text-left border-b border-zinc-50 dark:border-zinc-800/30 @if ($activeConversation && $activeConversation->id === $conv->id) bg-purple-50 dark:bg-purple-900/10 @endif">
                    <div
                        class="shrink-0 size-12 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-700 dark:text-purple-300 font-bold overflow-hidden">
                        @if ($otherUser->profile_picture_url)
                            <img src="{{ $otherUser->profile_picture_url }}" class="size-full object-cover">
                        @else
                            {{ $otherUser->initials() }}
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-baseline gap-2">
                            <h3 class="font-bold text-zinc-900 dark:text-zinc-100 truncate text-sm">
                                {{ $otherUser->name }}
                            </h3>
                            <span class="text-[10px] text-zinc-500 whitespace-nowrap">
                                {{ $conv->last_message_at ? $conv->last_message_at->diffForHumans(null, true) : '' }}
                            </span>
                        </div>
                        <p class="text-xs text-zinc-500 truncate mt-0.5">
                            {{ $conv->latestMessage?->content ?: ($conv->latestMessage?->image_path ? __('Sent an image') : ($conv->latestMessage?->document_path ? __('Sent a document') : __('No messages yet'))) }}
                        </p>
                    </div>
                    @if ($conv->messages()->where('user_id', '!=', auth()->id())->whereNull('read_at')->count() > 0)
                        <div
                            class="size-2 rounded-full bg-[var(--color-brand-purple)] shadow-[0_0_8px_var(--color-brand-purple)]">
                        </div>
                    @endif
                </button>
            @empty
                <div class="p-8 text-center">
                    <p class="text-sm text-zinc-500">{{ __('No conversations yet.') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Message View -->
    <div class="flex-1 flex flex-col bg-zinc-50/30 dark:bg-zinc-900/50 transition-all duration-300"
        :class="mobileView === 'chat' ? 'flex' : 'hidden md:flex'">
        @if ($activeConversation)
            @php $otherUser = $activeConversation->other_user; @endphp
            <!-- Header -->
            <div
                class="p-4 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                <div class="flex items-center gap-2 md:gap-3">
                    <button @click="mobileView = 'list'"
                        class="md:hidden p-1 -ms-2 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-full transition-colors">
                        <flux:icon name="chevron-left" class="size-6 text-zinc-500" />
                    </button>
                    <div
                        class="size-10 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-700 dark:text-purple-300 font-bold text-sm overflow-hidden">
                        @if ($otherUser && $otherUser->profile_picture_url)
                            <img src="{{ $otherUser->profile_picture_url }}" class="size-full object-cover">
                        @elseif($otherUser)
                            {{ $otherUser->initials() }}
                        @else
                            <flux:icon name="user" class="size-5" />
                        @endif
                    </div>
                    <div>
                        <h3 class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">
                            {{ $otherUser ? $otherUser->name : __('Deleted User') }}
                        </h3>
                        @if ($otherUser)
                            <p class="text-[10px] text-green-500 font-medium">{{ __('Online') }}</p>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-1 md:gap-2">
                    <flux:button wire:click="comingSoon" variant="ghost" icon="phone" size="sm"
                        class="hidden sm:inline-flex" />
                    <flux:button wire:click="comingSoon" variant="ghost" icon="video-camera" size="sm"
                        class="hidden sm:inline-flex" />
                    <flux:button variant="ghost" icon="information-circle" size="sm" />
                </div>
            </div>

            <!-- Messages Area -->
            <div id="message-container" class="flex-1 overflow-y-auto p-4 md:p-6 space-y-4 min-h-0"
                x-data="{
                    scrollToBottom() {
                        this.$el.scrollTo({ top: this.$el.scrollHeight, behavior: 'smooth' });
                    }
                }" x-init="scrollToBottom()" @message-sent.window="scrollToBottom()">
                @foreach ($messages as $msg)
                    @php $isMine = $msg->user_id === auth()->id(); @endphp
                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }} w-full">
                        <div class="max-w-[85%] md:max-w-[70%] space-y-1">
                            <div
                                class="rounded-2xl px-4 py-2 text-sm shadow-sm break-words overflow-hidden
                                                                                @if ($isMine) bg-[var(--color-brand-purple)] text-white rounded-tr-none 
                                                                                @else 
                                                                                    bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 border border-zinc-100 dark:border-zinc-700 rounded-tl-none @endif">

                                @if ($msg->image_path)
                                    <div
                                        class="mb-2 rounded-lg overflow-hidden border border-white/20 bg-zinc-100/10 -mx-1">
                                        <img src="{{ \App\Models\Setting::asset($msg->image_path) }}"
                                            class="max-w-full h-auto cursor-pointer hover:opacity-90 transition-opacity w-full object-cover"
                                            @click="window.open('{{ \App\Models\Setting::asset($msg->image_path) }}', '_blank')">
                                    </div>
                                @endif

                                @if ($msg->document_path)
                                    <div
                                        class="mb-2 flex items-center gap-2 p-2 rounded-lg @if ($isMine) bg-white/20 @else bg-zinc-100 dark:bg-zinc-700 @endif border border-white/10">
                                        <flux:icon name="document" class="size-5" />
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-bold truncate">
                                                {{ Str::limit($msg->document_name, 8) }}</p>
                                        </div>
                                        <a href="{{ \App\Models\Setting::asset($msg->document_path) }}" target="_blank"
                                            download="{{ $msg->document_name }}" title="{{ __('Download Document') }}"
                                            class="p-1 hover:bg-black/10 rounded transition-colors">
                                            <flux:icon name="arrow-down-tray" class="size-4" />
                                        </a>
                                    </div>
                                @endif

                                @if ($msg->content)
                                    <p class="leading-relaxed whitespace-pre-wrap">{{ $msg->content }}</p>
                                @endif
                            </div>
                            <div
                                class="flex items-center gap-1.5 px-1 {{ $isMine ? 'justify-end' : 'justify-start' }}">
                                <span
                                    class="text-[8px] text-zinc-500 uppercase font-medium">{{ $msg->created_at->format('h:i A') }}</span>
                                @if ($isMine)
                                    @if ($msg->read_at)
                                        <flux:icon name="check" class="size-2 text-blue-400" />
                                        <flux:icon name="check" class="size-2 -ms-1.5 text-blue-400" />
                                    @else
                                        <flux:icon name="check" class="size-2 text-zinc-300" />
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Input Area -->
            <div class="p-4 bg-white dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-800 shrink-0">
                <!-- Previews -->
                @if ($photo || $document)
                    <div class="mb-3 flex gap-2 overflow-x-auto pb-2 scrollbar-none">
                        @if ($photo)
                            <div
                                class="relative size-16 shrink-0 rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 shadow-sm">
                                <img src="{{ $photo->temporaryUrl() }}" class="size-full object-cover">
                                <button @click="$wire.set('photo', null)"
                                    class="absolute top-1 right-1 bg-black/60 text-white rounded-full p-1 hover:bg-black backdrop-blur-sm">
                                    <flux:icon name="x-mark" class="size-3" />
                                </button>
                            </div>
                        @endif
                        @if ($document)
                            <div
                                class="relative w-40 h-16 shrink-0 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 flex items-center p-2 gap-2 shadow-sm">
                                <div
                                    class="size-8 rounded bg-[var(--color-brand-purple)]/10 flex items-center justify-center shrink-0">
                                    <flux:icon name="document" class="size-4 text-[var(--color-brand-purple)]" />
                                </div>
                                <p class="text-[10px] truncate flex-1 font-bold text-zinc-700 dark:text-zinc-300">
                                    {{ Str::limit($document->getClientOriginalName(), 8) }}</p>
                                <button @click="$wire.set('document', null)"
                                    class="absolute -top-1.5 -right-1.5 bg-zinc-400 text-white rounded-full p-0.5 hover:bg-zinc-500 shadow-sm">
                                    <flux:icon name="x-mark" class="size-3" />
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
                <form wire:submit="sendMessage"
                    class="flex items-center gap-1 md:gap-2 bg-zinc-50 dark:bg-zinc-800 rounded-2xl px-2 md:px-4 py-2 border border-zinc-200 dark:border-zinc-700 focus-within:ring-2 focus-within:ring-[var(--color-brand-purple)]/20 focus-within:border-[var(--color-brand-purple)] transition-all">

                    <div class="flex gap-1">
                        <label
                            class="cursor-pointer text-zinc-400 hover:text-[var(--color-brand-purple)] transition-colors p-1">
                            <flux:icon name="photo" class="size-5" />
                            <input type="file" wire:model="photo" class="hidden" accept="image/*">
                        </label>
                        <label
                            class="cursor-pointer text-zinc-400 hover:text-[var(--color-brand-purple)] transition-colors p-1">
                            <flux:icon name="paper-clip" class="size-5" />
                            <input type="file" wire:model="document" class="hidden">
                        </label>
                    </div>

                    <input wire:model="messageText" type="text" placeholder="{{ __('Type a message...') }}"
                        class="flex-1 bg-transparent border-none focus:ring-0 text-sm py-1.5 text-zinc-900 dark:text-zinc-100">

                    <button type="submit"
                        class="p-2 text-[var(--color-brand-purple)] hover:scale-110 transition-transform disabled:opacity-50"
                        @if (empty(trim($messageText)) && !$photo && !$document) disabled @endif>
                        <flux:icon name="paper-airplane" class="size-5" />
                    </button>
                </form>
                <div wire:loading wire:target="photo, document" class="mt-2 text-[10px] text-zinc-400">
                    {{ __('Uploading attachment...') }}
                </div>
            </div>
        @else
            <div class="flex-1 flex flex-col items-center justify-center p-12 text-center">
                <div
                    class="size-20 rounded-full bg-purple-50 dark:bg-purple-900/10 flex items-center justify-center text-purple-600 dark:text-purple-400 mb-4 shadow-inner">
                    <flux:icon name="chat-bubble-left-right" class="size-10" />
                </div>
                <h3 class="text-xl font-black text-zinc-900 dark:text-zinc-100 mb-2">{{ __('Select a Conversation') }}
                </h3>
                <p class="text-zinc-500 max-w-xs text-sm leading-relaxed">
                    {{ __('Choose a message from the left or start a new conversation to get started.') }}
                </p>
            </div>
        @endif
    </div>

    <style>
        /* Hide scrollbar for Chrome, Safari and Opera */
        #message-container::-webkit-scrollbar {
            display: none;
        }

        /* Hide scrollbar for IE, Edge and Firefox */
        #message-container {
            -ms-overflow-style: none;
            /* IE and Edge */
            scrollbar-width: none;
            /* Firefox */
        }
    </style>
</div>
