<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Post;
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

    // Showcase Properties
    public $showShowcaseModal = false;
    public $showcaseDescription = '';
    public $showcaseBeforePhoto;
    public $showcaseAfterPhoto;
    public $showcaseToFeed = true;

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
            ->get()
            ->all();
    }

    public function selectConversation($id)
    {
        $this->activeConversation = Conversation::with(['users', 'messages.user', 'activeEngagement'])->findOrFail($id);
        $this->loadMessages();
        $this->markAsRead();
    }

    public function loadMessages()
    {
        if ($this->activeConversation) {
            $this->messages = $this->activeConversation
                ->messages()
                ->with(['user', 'engagement'])
                ->oldest()
                ->get()
                ->all();
        }
    }

    // Hiring Workflow Methods
    public function sendInquiry($title)
    {
        if (!$this->activeConversation || auth()->user()->isArtisan()) {
            return;
        }

        $engagement = \App\Models\Engagement::create([
            'user_id' => auth()->id(),
            'artisan_id' => $this->activeConversation->other_user->id,
            'conversation_id' => $this->activeConversation->id,
            'status' => 'pending',
            'title' => $title,
        ]);

        $this->createSystemMessage('inquiry', $engagement->id, "I'm interested in hiring you for: $title");
    }

    public function sendQuote($price, $time)
    {
        $engagement = $this->activeConversation->activeEngagement;
        if (!$engagement || auth()->id() !== $engagement->artisan_id) {
            return;
        }

        $engagement->update([
            'status' => 'quoted',
            'price_estimate' => $price,
            'completion_estimate' => $time,
        ]);

        $this->createSystemMessage('quote', $engagement->id, "I've sent a quote: $price ($time)");
    }

    public function declineQuote()
    {
        $engagement = $this->activeConversation->activeEngagement;
        if (!$engagement || auth()->id() !== $engagement->user_id) {
            return;
        }

        $engagement->update([
            'status' => 'pending',
            'price_estimate' => null,
            'completion_estimate' => null,
        ]);

        $this->refreshEngagementOnMessages($engagement->id);
        $this->createSystemMessage('text', $engagement->id, "I've declined the quote. Please let's discuss the budget or timeline further.");
    }

    public function revokeQuote()
    {
        $engagement = $this->activeConversation->activeEngagement;
        if (!$engagement || auth()->id() !== $engagement->artisan_id) {
            return;
        }

        $engagement->update([
            'status' => 'pending',
            'price_estimate' => null,
            'completion_estimate' => null,
        ]);

        $this->refreshEngagementOnMessages($engagement->id);
        $this->createSystemMessage('text', $engagement->id, "I've withdrawn my previous quote to make an update.");
    }

    public function acceptQuote()
    {
        $engagement = $this->activeConversation->activeEngagement;
        if (!$engagement || auth()->id() !== $engagement->user_id) {
            return;
        }

        $engagement->update([
            'status' => 'accepted',
            'confirmed_at' => now(),
        ]);

        $this->refreshEngagementOnMessages($engagement->id);
        $this->createSystemMessage('handshake', $engagement->id, "I've accepted your quote. Let's get started!");
    }

    public function completeJob()
    {
        $engagement = $this->activeConversation->activeEngagement;
        if (!$engagement) {
            return;
        }

        $engagement->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->refreshEngagementOnMessages($engagement->id);
        $this->createSystemMessage('completion', $engagement->id, 'Job marked as completed!');
    }

    public function submitReview($rating, $comment)
    {
        $engagement = $this->activeConversation->engagements()->where('status', 'completed')->whereNull('review_id')->latest()->first();
        if (!$engagement || auth()->id() !== $engagement->user_id) {
            return;
        }

        $review = \App\Models\Review::create([
            'reviewer_id' => auth()->id(),
            'artisan_id' => $engagement->artisan_id,
            'rating' => $rating,
            'comment' => $comment,
            'engagement_id' => $engagement->id,
        ]);

        $engagement->update(['review_id' => $review->id]);

        $this->refreshEngagementOnMessages($engagement->id);
        $this->dispatch('toast', type: 'success', title: 'Thank You!', message: 'Your rating has been submitted.');
    }

    public function showcaseJob()
    {
        $this->showShowcaseModal = true;
    }

    public function submitShowcase()
    {
        $engagement = $this->activeConversation->activeEngagement ?: $this->activeConversation->engagements()->where('status', 'completed')->latest()->first();

        if (!$engagement || auth()->id() !== $engagement->artisan_id) {
            return;
        }

        $this->validate([
            'showcaseDescription' => 'required|min:20',
            'showcaseBeforePhoto' => 'nullable|image|max:5120',
            'showcaseAfterPhoto' => 'required|image|max:5120',
        ]);

        $photos = [];
        $feedPhotos = [];

        if ($this->showcaseBeforePhoto) {
            $photos['before'] = $this->showcaseBeforePhoto->store('showcases/before', 'cloudinary');

            if ($this->showcaseToFeed) {
                $feedPhotos[] = $this->showcaseBeforePhoto->store('posts', 'public');
            }
        }

        if ($this->showcaseAfterPhoto) {
            $photos['after'] = $this->showcaseAfterPhoto->store('showcases/after', 'cloudinary');

            if ($this->showcaseToFeed) {
                $feedPhotos[] = $this->showcaseAfterPhoto->store('posts', 'public');
            }
        }

        $engagement->update([
            'is_public' => true,
            'showcase_description' => $this->showcaseDescription,
            'showcase_photos' => $photos,
        ]);

        if ($this->showcaseToFeed) {
            Post::create([
                'user_id' => auth()->id(),
                'content' => '⭐ VERIFIED SUCCESS: ' . $this->showcaseDescription,
                'images' => implode(',', $feedPhotos),
            ]);
        }

        $this->refreshEngagementOnMessages($engagement->id);
        $this->reset(['showShowcaseModal', 'showcaseDescription', 'showcaseBeforePhoto', 'showcaseAfterPhoto', 'showcaseToFeed']);
        $this->dispatch('toast', type: 'success', title: 'Showcase Published!', message: 'This project is now live on your profile portfolio.');
    }

    private function refreshEngagementOnMessages($engagementId)
    {
        foreach ($this->messages as $i => $msg) {
            if ($msg->engagement_id === $engagementId) {
                $msg->load('engagement');
                $this->messages[$i] = $msg;
            }
        }
    }

    private function createSystemMessage($type, $engagementId, $content)
    {
        $message = Message::create([
            'conversation_id' => $this->activeConversation->id,
            'user_id' => auth()->id(),
            'type' => $type,
            'engagement_id' => $engagementId,
            'content' => $content,
        ]);

        $this->activeConversation->update(['last_message_at' => now()]);
        $this->activeConversation->other_user->notify(new NewMessage($message));
        broadcast(new MessageSent($message));
        $this->messages[] = $message;
        $this->loadConversations();
        $this->dispatch('message-sent');
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
        broadcast(new MessageSent($message));

        $this->reset(['messageText', 'photo', 'document']);

        // Optimistic: append directly instead of full DB reload
        $this->messages[] = $message;
        $this->loadConversations();

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
        $this->dispatch('toast', [
            'type' => 'info',
            'title' => 'Feature Unavailable!',
            'message' => 'Upgrade to premium plan to use voice and video calls on Allsers. Stay tuned!',
        ]);
    }

    public function deleteMessage($id)
    {
        $message = Message::where('conversation_id', $this->activeConversation->id)->where('id', $id)->first();

        if ($message) {
            $message->delete();
            $this->loadMessages();
            $this->loadConversations();
            $this->dispatch('toast', type: 'info', title: 'Removed', message: 'Chat content deleted.');
        }
    }

    public function rendering($view)
    {
        $view->title(__('Chat'));
    }
}; ?>

<div wire:poll.3s="refreshChat" x-data="{
    mobileView: '{{ $activeConversation ? 'chat' : 'list' }}',
    uistate_opened: false,
    interactionType: null,
    quotePrice: '',
    quoteTime: '',
    inquiryTitle: '',
    rating: 0,
    reviewComment: '',
    showPrompt: true,
    isOnline: navigator.onLine,
    checkConnectionAndSend() {
        if (!navigator.onLine) {
            this.$dispatch('toast', { type: 'error', title: 'Offline!', message: 'Please check your internet connection and try again.' });
            return;
        }
        $wire.sendMessage();
    }
}" x-init="setTimeout(() => showPrompt = false, 8000);
window.addEventListener('online', () => isOnline = true);
window.addEventListener('offline', () => isOnline = false);"
    class="h-[calc(100dvh-4rem)] md:h-[calc(100vh-4rem)] flex flex-col overflow-hidden bg-white dark:bg-zinc-950 border-x-0 border-t-0 border-zinc-200 dark:border-zinc-800 relative">
    <x-top-bar title="{{ __('Messages') }}" />

    <div class="flex flex-1 min-h-0 overflow-hidden">
    <!-- Conversation List -->
    <div class="w-full md:w-80 border-e border-zinc-200 dark:border-zinc-800 flex flex-col transition-all duration-300"
        :class="mobileView === 'list' ? 'flex' : 'hidden md:flex'">
        <div class="flex-1 overflow-y-auto">
            @forelse($conversations as $conv)
                @php $otherUser = $conv->other_user; @endphp
                @if (!$otherUser)
                    @continue
                @endif
                <button wire:click="selectConversation({{ $conv->id }})" @click="mobileView = 'chat'"
                    class="w-full p-4 flex items-center gap-3 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50 text-left relative @if ($activeConversation && $activeConversation->id === $conv->id) bg-zinc-50 dark:bg-zinc-800/30 @endif">
                    @if ($activeConversation && $activeConversation->id === $conv->id)
                        <div class="absolute left-0 top-2 bottom-2 w-[3px] bg-[var(--color-brand-purple)] rounded-full"></div>
                    @endif
                    <div
                        class="shrink-0 size-12 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-purple-600 dark:text-purple-400 font-bold overflow-hidden">
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
                            <span class="text-xs text-zinc-500 whitespace-nowrap">
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
    <div class="flex-1 flex flex-col bg-white dark:bg-zinc-950 transition-all duration-300"
        :class="mobileView === 'chat' ? 'flex' : 'hidden md:flex'">
        @if ($activeConversation)
            @php $otherUser = $activeConversation->other_user; @endphp
            <!-- Header -->
            <div
                class="p-4 bg-white dark:bg-zinc-950 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
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
                            {{-- <p class="text-xs text-green-500 font-medium">{{ __('Online') }}</p> --}}
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <flux:button variant="ghost" icon="information-circle" size="sm" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300" />
                </div>
            </div>

            <!-- Messages Area -->
            <div id="message-container" class="flex-1 overflow-y-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6 min-h-0"
                x-data="{
                    deletingId: null,
                    scrollToBottom() {
                        this.$el.scrollTo({ top: this.$el.scrollHeight, behavior: 'smooth' });
                    }
                }" x-init="scrollToBottom()" @message-sent.window="scrollToBottom()">
                @foreach ($messages as $msg)
                    @php $isMine = $msg->user_id === auth()->id(); @endphp

                    @if ($msg->type !== 'text')
                        {{-- Engagement Cards --}}
                        <div class="flex justify-center w-full py-2 px-2 sm:px-0 select-none">
                            <div class="w-full max-w-[280px] xs:max-w-[320px] sm:max-w-sm transition-all duration-300">
                                @if ($msg->type === 'inquiry')
                                    <div
                                        class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 relative group">
                                        {{-- Urgency Badge --}}
                                        <div class="absolute top-4 right-4 focus:outline-none">
                                            @php
                                                $urgencyClass = match ($msg->engagement?->urgency_level) {
                                                    'high' => 'bg-red-100 text-red-600 border-red-200',
                                                    'medium' => 'bg-orange-100 text-orange-600 border-orange-200',
                                                    default => 'bg-blue-100 text-blue-600 border-blue-200',
                                                };
                                            @endphp
                                            <span
                                                class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-normal border {{ $urgencyClass }}">
                                                {{ $msg->engagement?->urgency_level ?? 'Normal' }}
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-3 sm:gap-4 mb-4 sm:mb-6 relative z-10">
                                            <div
                                                class="size-10 sm:size-12 bg-purple-600 rounded-xl flex items-center justify-center shrink-0">
                                                <flux:icon name="magnifying-glass-circle"
                                                    class="size-6 sm:size-7 text-white" />
                                            </div>
                                            <div>
                                                <h4
                                                    class="font-bold text-zinc-900 dark:text-zinc-100 uppercase tracking-normal text-xs sm:text-xs">
                                                    {{ __('Initial Brief') }}
                                                </h4>
                                                <p class="text-xs sm:text-sm font-bold text-zinc-500">
                                                    {{ __('Project Request') }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="space-y-4 relative z-10">
                                            <div
                                                class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-2xl border border-zinc-100 dark:border-zinc-800">
                                                <p
                                                    class="text-xs font-bold text-zinc-400 uppercase tracking-normal mb-1">
                                                    {{ __('Task Description') }}
                                                </p>
                                                <p
                                                    class="text-sm font-bold text-zinc-700 dark:text-zinc-200 leading-relaxed italic">
                                                    "{{ $msg->content }}"</p>
                                            </div>

                                            @if ($msg->engagement?->location_context)
                                                <div class="flex items-center gap-2 px-1">
                                                    <flux:icon name="map-pin" class="size-3 text-zinc-400" />
                                                    <p class="text-xs font-bold text-zinc-500">
                                                        {{ $msg->engagement->location_context }}
                                                    </p>
                                                </div>
                                            @endif

                                            @if ($msg->engagement?->inquiry_photos)
                                                @php $inquiryUrls = array_map(fn($p) => \App\Models\Setting::asset($p), $msg->engagement->inquiry_photos); @endphp
                                                <div class="grid grid-cols-4 gap-2">
                                                    @foreach ($inquiryUrls as $i => $iPhoto)
                                                        <div
                                                            class="aspect-square rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 cursor-pointer"
                                                            @click="$dispatch('open-lightbox', { images: {{ Js::from($inquiryUrls) }}, index: {{ $i }} })">
                                                            <img src="{{ $iPhoto }}"
                                                                class="size-full object-cover hover:scale-110 transition-transform">
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($msg->type === 'quote')
                                    <div
                                        class="bg-indigo-50 dark:bg-indigo-900/10 border border-indigo-500/30 rounded-xl p-4 sm:p-6 relative">
                                        <div class="flex items-center gap-4 mb-6">
                                            <div
                                                class="size-12 bg-indigo-600 rounded-xl flex items-center justify-center">
                                                <flux:icon name="currency-dollar" variant="solid"
                                                    class="size-7 text-white" />
                                            </div>
                                            <div>
                                                <h4
                                                    class="font-bold text-indigo-900 dark:text-indigo-100 uppercase tracking-normal text-xs">
                                                    {{ __('Professional Quote') }}
                                                </h4>
                                                <p class="text-sm font-bold text-zinc-500">
                                                    {{ __('Estimate for work') }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4 mb-6 relative z-10">
                                            <div
                                                class="bg-white dark:bg-zinc-800 p-3 rounded-2xl border border-zinc-100 dark:border-zinc-700">
                                                <p
                                                    class="text-xs font-bold text-zinc-400 uppercase tracking-normal mb-1">
                                                    {{ __('Budget') }}
                                                </p>
                                                <p class="text-lg font-bold text-zinc-900 dark:text-white">
                                                    {{ $msg->engagement->price_estimate }}
                                                </p>
                                            </div>
                                            <div
                                                class="bg-white dark:bg-zinc-800 p-3 rounded-2xl border border-zinc-100 dark:border-zinc-700">
                                                <p
                                                    class="text-xs font-bold text-zinc-400 uppercase tracking-normal mb-1">
                                                    {{ __('Timeline') }}
                                                </p>
                                                <p class="text-lg font-bold text-zinc-900 dark:text-white">
                                                    {{ $msg->engagement->completion_estimate }}
                                                </p>
                                            </div>
                                        </div>

                                        @if ($msg->engagement->status === 'quoted')
                                            <div class="flex gap-3 relative z-10">
                                                @if (!$isMine)
                                                    <button wire:click="acceptQuote"
                                                        class="flex-1 py-4 bg-indigo-600 text-white font-bold rounded-xl hover:opacity-90 transition-all text-xs uppercase tracking-normal text-center">
                                                        {{ __('Confirm Deal') }}
                                                    </button>
                                                    <button wire:click="declineQuote"
                                                        class="flex-1 py-4 bg-white dark:bg-zinc-800 text-zinc-500 font-bold rounded-xl border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 transition-all text-xs uppercase tracking-normal text-center">
                                                        {{ __('Decline') }}
                                                    </button>
                                                @else
                                                    <button wire:click="revokeQuote"
                                                        class="w-full py-4 bg-zinc-100 dark:bg-zinc-800 text-zinc-500 font-bold rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 hover:bg-zinc-200 transition-all text-xs uppercase tracking-normal text-center">
                                                        {{ __('Withdraw Quote') }}
                                                    </button>
                                                @endif
                                            </div>
                                        @elseif($msg->engagement->status === 'accepted' || $msg->engagement->status === 'completed')
                                            <div
                                                class="w-full py-3 bg-green-500/10 text-green-600 font-bold rounded-xl border border-green-500/20 text-center text-xs uppercase tracking-normal flex items-center justify-center gap-2">
                                                <flux:icon name="check-circle" variant="solid" class="size-4" />
                                                {{ __('Deal Confirmed') }}
                                            </div>
                                        @endif
                                    </div>
                                @elseif($msg->type === 'handshake')
                                    <div
                                        class="bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-500/20 rounded-xl p-5 text-center">
                                        <div
                                            class="size-16 bg-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4">
                                            <flux:icon name="sparkles" variant="solid" class="size-8 text-white" />
                                        </div>
                                        <h4
                                            class="font-bold text-emerald-900 dark:text-emerald-100 uppercase tracking-normal text-xs mb-1">
                                            {{ __('Deal Started!') }}
                                        </h4>
                                        <p class="text-xs font-bold text-emerald-600/70">
                                            {{ __('The professional is now officially booked.') }}
                                        </p>
                                    </div>
                                @elseif($msg->type === 'completion')
                                    <div
                                        class="bg-zinc-900 dark:bg-white rounded-xl p-5 sm:p-8 text-center relative">
                                        <div
                                            class="size-20 bg-gradient-to-tr from-yellow-400 to-orange-500 rounded-full flex items-center justify-center mx-auto mb-6">
                                            <flux:icon name="trophy" variant="solid" class="size-10 text-white" />
                                        </div>
                                        <h4
                                            class="font-bold text-white dark:text-zinc-900 text-xl tracking-tight mb-2">
                                            {{ __('Project Completed!') }}
                                        </h4>
                                        <p
                                            class="text-zinc-400 dark:text-zinc-500 text-sm font-medium mb-8 leading-relaxed">
                                            {{ __('The job has been marked as finished. Please share your experience to help the community.') }}
                                        </p>

                                        @if (!$isMine && !$msg->engagement->review_id)
                                            {{-- Rating Interaction --}}
                                            <div class="space-y-6">
                                                <div class="flex justify-center gap-3">
                                                    @for ($i = 1; $i <= 5; $i++)
                                                        <button @click="rating = {{ $i }}"
                                                            class="transition-transform hover:scale-125"
                                                            x-bind:class="rating >= {{ $i }} ?
                                                                'text-yellow-400 scale-110' :
                                                                'text-zinc-700 dark:text-zinc-200'">
                                                            <flux:icon name="star" variant="solid"
                                                                class="size-8" />
                                                        </button>
                                                    @endfor
                                                </div>
                                                <textarea x-model="reviewComment" placeholder="{{ __('Write a quick review...') }}"
                                                    class="w-full bg-zinc-800 dark:bg-zinc-200 border-none rounded-2xl text-white dark:text-zinc-900 text-sm focus:ring-2 focus:ring-yellow-500/50 p-4 resize-none"
                                                    rows="2"></textarea>
                                                <button @click="$wire.submitReview(rating, reviewComment)"
                                                    class="w-full py-4 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white font-bold rounded-xl hover:opacity-90 active:scale-95 transition-all text-xs uppercase tracking-normal"
                                                    x-bind:disabled="rating === 0">
                                                    {{ __('Publish Review') }}
                                                </button>
                                            </div>
                                        @elseif($msg->engagement->review_id)
                                            <div class="flex flex-col items-center gap-4">
                                                <div
                                                    class="py-3 px-6 bg-yellow-400 text-zinc-900 font-bold rounded-2xl inline-flex items-center gap-2 text-xs uppercase tracking-normal">
                                                    <flux:icon name="check-circle" variant="solid" class="size-4" />
                                                    {{ __('Review Submitted') }}
                                                </div>

                                                @if ($isMine && !$msg->engagement->is_public)
                                                    <div
                                                        class="w-full mt-4 p-4 sm:p-6 bg-white/5 rounded-xl border border-white/10">
                                                        <p
                                                            class="text-xs font-bold uppercase text-zinc-400 tracking-normal mb-4">
                                                            {{ __('Verified Success') }}
                                                        </p>
                                                        <button wire:click="showcaseJob"
                                                            class="w-full py-4 bg-purple-600 text-white font-bold rounded-xl hover:opacity-90 transition-all text-xs uppercase tracking-normal border border-purple-500">
                                                            {{ __('Showcase to Portfolio') }}
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }} w-full px-2 select-none relative"
                            @if ($isMine) @dblclick="deletingId = (deletingId === {{ $msg->id }} ? null : {{ $msg->id }})" @endif>

                            @if ($isMine)
                                <!-- Deletion Overlay -->
                                <div x-show="deletingId === {{ $msg->id }}" x-transition.opacity
                                    class="absolute inset-0 z-20 flex items-center justify-center bg-white/10 dark:bg-black/10 rounded-xl">
                                    <div class="flex flex-col items-center gap-1">
                                        <button
                                            @click.stop="$wire.deleteMessage({{ $msg->id }}); deletingId = null"
                                            class="size-10 bg-red-500 text-white rounded-full flex items-center justify-center hover:scale-110 active:scale-95 transition-all">
                                            <flux:icon name="trash" variant="solid" class="size-5" />
                                        </button>
                                        <span
                                            class="text-xs font-bold uppercase tracking-normal text-zinc-900 dark:text-white">{{ __('Delete') }}</span>
                                    </div>
                                    <button @click.stop="deletingId = null"
                                        class="absolute -top-4 right-0 text-xs font-bold text-zinc-400 hover:text-zinc-600">{{ __('Cancel') }}</button>
                                </div>
                            @endif

                            <div class="max-w-[90%] sm:max-w-[85%] md:max-w-[70%] space-y-1 transition-all duration-300"
                                :class="deletingId === {{ $msg->id }} ? 'blur-md opacity-40 scale-95' : ''">
                                <div
                                    class="px-4 py-2.5 sm:px-5 sm:py-3 text-sm break-words overflow-hidden cursor-pointer leading-relaxed
                                    @if ($isMine) bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 rounded-[22px] rounded-br-[6px]
                                    @else bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 rounded-[22px] rounded-bl-[6px] @endif">

                                    @if ($msg->image_path)
                                        @php $chatImageUrl = \App\Models\Setting::asset($msg->image_path); @endphp
                                        <div
                                            class="mb-2 rounded-lg overflow-hidden border border-white/20 bg-zinc-100/10 -mx-1 cursor-pointer"
                                            @click="$dispatch('open-lightbox', { images: ['{{ $chatImageUrl }}'], index: 0 })">
                                            <img src="{{ $chatImageUrl }}"
                                                class="max-w-full h-auto hover:opacity-90 transition-opacity w-full object-cover">
                                        </div>
                                    @endif

                                    @if ($msg->document_path)
                                        <div
                                            class="mb-2 flex items-center gap-2 p-2 rounded-lg @if ($isMine) bg-white/20 @else bg-zinc-100 dark:bg-zinc-700 @endif border border-white/10">
                                            <flux:icon name="document" class="size-5" />
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-bold truncate">
                                                    {{ Str::limit($msg->document_name, 8) }}
                                                </p>
                                            </div>
                                            <a href="{{ \App\Models\Setting::asset($msg->document_path) }}"
                                                target="_blank" download="{{ $msg->document_name }}"
                                                title="{{ __('Download Document') }}"
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
                                    class="flex items-center gap-1 px-1 {{ $isMine ? 'justify-end' : 'justify-start' }}">
                                    <span
                                        class="text-[11px] text-zinc-400 dark:text-zinc-500 font-medium">{{ $msg->created_at->format('h:i A') }}</span>
                                    @if ($isMine)
                                        @if ($msg->read_at)
                                            <flux:icon name="check" class="size-2.5 text-blue-400" />
                                        @else
                                            <flux:icon name="check" class="size-2.5 text-zinc-300 dark:text-zinc-600" />
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <!-- Interaction Deck (Deal Flow) -->
            <div x-show="uistate_opened" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                class="mx-4 mb-4 p-6 bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 relative">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="font-bold text-zinc-900 dark:text-zinc-100 text-lg tracking-tight">
                        {{ __('Interaction Deck') }}
                    </h3>
                    <button @click="uistate_opened = false"
                        class="text-zinc-400 hover:text-zinc-600 transition-colors">
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                </div>

                <div class="space-y-6">
                    @if (auth()->user()->isArtisan() && $activeConversation->activeEngagement)
                        @if ($activeConversation->activeEngagement->status === 'pending')
                            <div class="space-y-4">
                                <p class="text-xs font-bold uppercase tracking-normal text-zinc-400">
                                    {{ __('Send Professional Quote') }}
                                </p>
                                <div class="grid grid-cols-2 gap-3">
                                    <flux:input x-model="quotePrice" type="text" placeholder="Price (e.g. ₦25k)"
                                        class="bg-zinc-50 dark:bg-zinc-900 border-none rounded-2xl text-sm focus:ring-2 focus:ring-purple-500/20" />
                                    <flux:input x-model="quoteTime" type="text" placeholder="Time (e.g. 2 days)"
                                        class="bg-zinc-50 dark:bg-zinc-900 border-none rounded-2xl text-sm focus:ring-2 focus:ring-purple-500/20" />
                                </div>
                                <button @click="$wire.sendQuote(quotePrice, quoteTime); uistate_opened = false"
                                    class="w-full py-3.5 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 font-bold rounded-xl hover:opacity-90 transition-all text-xs uppercase tracking-normal">
                                    {{ __('Send Quote Card') }}
                                </button>
                            </div>
                        @elseif($activeConversation->activeEngagement->status === 'accepted')
                            <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-center">
                                <p class="text-xs font-bold text-emerald-600 mb-4">
                                    {{ __('This job is currently active.') }}
                                </p>
                                <button @click="$wire.completeJob(); uistate_opened = false"
                                    class="w-full py-3.5 bg-emerald-600 text-white font-bold rounded-xl hover:opacity-90 transition-all text-xs uppercase tracking-normal">
                                    {{ __('Mark as Completed') }}
                                </button>
                            </div>
                        @elseif(
                            $activeConversation->activeEngagement->status === 'completed' &&
                                !$activeConversation->activeEngagement->is_public &&
                                $activeConversation->activeEngagement->completed_at?->gt(now()->subDays(7)))
                            <div class="p-4 bg-purple-500/10 border border-purple-500/20 rounded-xl text-center">
                                <p class="text-xs font-bold text-purple-600 mb-4">
                                    {{ __('Job finished! Want to show it off?') }}
                                </p>
                                <button @click="$wire.showcaseJob(); uistate_opened = false"
                                    class="w-full py-3.5 bg-purple-600 text-white font-bold rounded-xl hover:opacity-90 transition-all text-xs uppercase tracking-normal">
                                    {{ __('Showcase to Portfolio') }}
                                </button>
                            </div>
                        @endif
                    @elseif(!auth()->user()->isArtisan() && !$activeConversation->activeEngagement)
                        <div class="space-y-4">
                            <p class="text-xs font-bold uppercase tracking-normal text-zinc-400">
                                {{ __('Start a Deal') }}
                            </p>
                            <input x-model="inquiryTitle" type="text" placeholder="What do you need done?"
                                class="w-full bg-zinc-50 dark:bg-zinc-900 border-none rounded-xl text-sm focus:ring-2 focus:ring-purple-500/20 py-3.5">
                            <button @click="$wire.sendInquiry(inquiryTitle); uistate_opened = false"
                                class="w-full py-3.5 bg-purple-600 text-white font-bold rounded-xl hover:opacity-90 transition-all text-xs uppercase tracking-normal">
                                {{ __('Send Inquiry Card') }}
                            </button>
                            <p class="text-xs text-zinc-500 text-center italic">
                                {{ __('Structured deals are safer and build your hiring history.') }}
                            </p>
                        </div>
                    @else
                        <div
                            class="py-8 text-center bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700">
                            <flux:icon name="lock-closed" class="size-8 text-zinc-300 mx-auto mb-3" />
                            <p class="text-xs font-bold text-zinc-500">{{ __('No active actions available.') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Input Area -->
            <div class="px-4 pt-3 pb-[env(safe-area-inset-bottom,1rem)] bg-white dark:bg-zinc-950 border-t border-zinc-200 dark:border-zinc-800 shrink-0">
                <!-- Previews -->
                @if ($photo || $document)
                    <div class="mb-3 flex gap-2 overflow-x-auto pb-2 scrollbar-none">
                        @if ($photo)
                            <div
                                class="relative size-16 shrink-0 rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700">
                                <img src="{{ $photo->temporaryUrl() }}" class="size-full object-cover">
                                <button @click="$wire.set('photo', null)"
                                    class="absolute top-1 right-1 bg-black/60 text-white rounded-full p-1 hover:bg-black">
                                    <flux:icon name="x-mark" class="size-3" />
                                </button>
                            </div>
                        @endif
                        @if ($document)
                            <div
                                class="relative w-40 h-16 shrink-0 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 flex items-center p-2 gap-2">
                                <div
                                    class="size-8 rounded bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center shrink-0">
                                    <flux:icon name="document" class="size-4 text-zinc-500" />
                                </div>
                                <p class="text-xs truncate flex-1 font-bold text-zinc-700 dark:text-zinc-300">
                                    {{ Str::limit($document->getClientOriginalName(), 8) }}
                                </p>
                                <button @click="$wire.set('document', null)"
                                    class="absolute -top-1.5 -right-1.5 bg-zinc-400 text-white rounded-full p-0.5 hover:bg-zinc-500">
                                    <flux:icon name="x-mark" class="size-3" />
                                </button>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="flex items-end gap-2">
                    <button @click="uistate_opened = !uistate_opened; showPrompt = false"
                        x-bind:class="uistate_opened ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-400'"
                        class="p-2.5 rounded-xl transition-all active:scale-95 shrink-0" :title="uistate_opened ? 'Close' : 'Actions'">
                        <div x-bind:class="uistate_opened ? 'rotate-45' : ''" class="transition-transform duration-200">
                            <flux:icon name="plus" class="size-5" />
                        </div>
                    </button>

                    <form @submit.prevent="checkConnectionAndSend()"
                        class="flex-1 flex items-end gap-1 bg-zinc-100 dark:bg-zinc-800 rounded-2xl px-3 py-1.5 border border-transparent focus-within:border-zinc-300 dark:focus-within:border-zinc-600 focus-within:bg-white dark:focus-within:bg-zinc-900 transition-all">

                        <textarea wire:model="messageText" placeholder="{{ __('Type a message...') }}"
                            x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                            x-on:keydown.enter.prevent="if(!$event.shiftKey) checkConnectionAndSend()"
                            class="flex-1 bg-transparent border-none focus:ring-0 outline-none text-sm py-1.5 text-zinc-900 dark:text-zinc-100 resize-none max-h-32 scrollbar-none placeholder:text-zinc-400"
                            rows="1"></textarea>

                        <div class="flex items-center gap-0.5 pb-1">
                            <label class="cursor-pointer text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors p-1">
                                <flux:icon name="photo" class="size-4" />
                                <input type="file" wire:model="photo" class="hidden" accept="image/*">
                            </label>
                            <label class="cursor-pointer text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors p-1">
                                <flux:icon name="paper-clip" class="size-4" />
                                <input type="file" wire:model="document" class="hidden">
                            </label>
                            <button type="submit"
                                class="p-1.5 text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors disabled:opacity-30"
                                @if (empty(trim($messageText)) && !$photo && !$document) disabled @endif>
                                <flux:icon name="paper-airplane" variant="solid" class="size-4" />
                            </button>
                        </div>
                    </form>
                </div>
                <div wire:loading wire:target="photo, document" class="mt-2 text-xs text-zinc-400">
                    {{ __('Uploading attachment...') }}
                </div>
            </div>
        @else
            <div class="flex-1 flex flex-col items-center justify-center p-12 text-center">
                <div
                    class="size-20 rounded-full bg-purple-50 dark:bg-purple-900/10 flex items-center justify-center text-purple-600 dark:text-purple-400 mb-4">
                    <flux:icon name="chat-bubble-left-right" class="size-10" />
                </div>
                <h3 class="text-xl font-bold text-zinc-900 dark:text-zinc-100 mb-2">{{ __('Select a Conversation') }}
                </h3>
                <p class="text-zinc-500 max-w-xs text-sm leading-relaxed">
                    {{ __('Choose a message from the left or start a new conversation to get started.') }}
                </p>
            </div>
        @endif
    </div>

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

        .gradient-text {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .select-none {
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }

        /* Better text wrapping for mobile */
        .break-words {
            word-break: break-word;
            overflow-wrap: break-word;
        }

        @media (max-width: 640px) {
            .max-w-sm {
                max-width: 100% !important;
            }
        }
    </style>
    <!-- Showcase Creator Modal -->
    <flux:modal wire:model="showShowcaseModal" variant="flyout" class="space-y-6">
        <div class="space-y-6">
            <div>
                <h2 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Create Project Showcase') }}</h2>
                <p class="text-xs text-zinc-500 mt-1">
                    {{ __('Share the results of your hard work with future clients.') }}
                </p>
            </div>

            <form wire:submit="submitShowcase" class="space-y-6">
                {{-- Public Description --}}
                <flux:textarea wire:model="showcaseDescription" label="Public Project Summary"
                    placeholder="Describe what you fixed, how you did it, and any special tools used..."
                    rows="4" />

                {{-- Before/After Photos --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <flux:label>{{ __('Before Fix (Optional)') }}</flux:label>
                        <div
                            class="relative aspect-[4/3] sm:aspect-square rounded-2xl border-2 border-dashed border-zinc-200 dark:border-zinc-700 flex flex-col items-center justify-center cursor-pointer hover:border-purple-400 overflow-hidden bg-zinc-50 dark:bg-zinc-800/50">
                            @if ($showcaseBeforePhoto)
                                <img src="{{ $showcaseBeforePhoto->temporaryUrl() }}" class="size-full object-cover">
                            @else
                                <flux:icon name="photo" class="size-6 text-zinc-300" />
                                <span
                                    class="text-xs font-bold text-zinc-400 mt-1 uppercase">{{ __('Upload Before') }}</span>
                            @endif
                            <input type="file" wire:model="showcaseBeforePhoto"
                                class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <flux:label>{{ __('After Success') }}</flux:label>
                        <div
                            class="relative aspect-[4/3] sm:aspect-square rounded-2xl border-2 border-dashed border-zinc-200 dark:border-zinc-700 flex flex-col items-center justify-center cursor-pointer hover:border-purple-400 overflow-hidden bg-zinc-50 dark:bg-zinc-800/50">
                            @if ($showcaseAfterPhoto)
                                <img src="{{ $showcaseAfterPhoto->temporaryUrl() }}" class="size-full object-cover">
                            @else
                                <flux:icon name="sparkles" variant="solid" class="size-6 text-zinc-300" />
                                <span
                                    class="text-xs font-bold text-zinc-400 mt-1 uppercase">{{ __('Upload After') }}</span>
                            @endif
                            <input type="file" wire:model="showcaseAfterPhoto"
                                class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*">
                        </div>
                    </div>
                </div>

                {{-- Feed Toggle --}}
                <div
                    class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-2xl border border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="size-10 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                            <flux:icon name="rss" class="size-5 text-purple-600" />
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-zinc-900 dark:text-white">
                                {{ __('Share to Public Feed') }}
                            </p>
                            <p class="text-xs text-zinc-500">{{ __('Promote this success story to all users.') }}
                            </p>
                        </div>
                    </div>
                    <flux:checkbox wire:model="showcaseToFeed" variant="toggle" />
                </div>

                @if ($errors->any())
                    <div class="bg-red-50 text-red-500 p-3 rounded-xl text-xs font-bold">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" class="w-full rounded-xl py-3"
                        wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitShowcase">
                            {{ __('Publish to Public Profile') }}
                        </span>
                        <span wire:loading wire:target="submitShowcase">
                            {{ __('Publishing Success...') }}
                        </span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>