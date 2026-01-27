<?php

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

        $this->dispatch('toast', type: 'success', title: 'Thank You!', message: 'Your rating has been submitted.');
        $this->loadMessages();
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
            // Upload to Cloudinary for Showcase
            $photos['before'] = $this->showcaseBeforePhoto->store('showcases/before', 'cloudinary');

            // Upload to Public for Feed Post
            if ($this->showcaseToFeed) {
                $feedPhotos[] = $this->showcaseBeforePhoto->store('posts', 'public');
            }
        }

        if ($this->showcaseAfterPhoto) {
            // Upload to Cloudinary for Showcase
            $photos['after'] = $this->showcaseAfterPhoto->store('showcases/after', 'cloudinary');

            // Upload to Public for Feed Post
            if ($this->showcaseToFeed) {
                $feedPhotos[] = $this->showcaseAfterPhoto->store('posts', 'public');
            }
        }

        $engagement->update([
            'is_public' => true,
            'showcase_description' => $this->showcaseDescription,
            'showcase_photos' => $photos,
        ]);

        // 3. Optional: Create Feed Post
        if ($this->showcaseToFeed) {
            Post::create([
                'user_id' => auth()->id(),
                'content' => "⭐ VERIFIED SUCCESS: " . $this->showcaseDescription,
                'images' => implode(',', $feedPhotos), // Store as comma-separated string for public posts
            ]);
        }

        $this->reset(['showShowcaseModal', 'showcaseDescription', 'showcaseBeforePhoto', 'showcaseAfterPhoto', 'showcaseToFeed']);
        $this->dispatch('toast', type: 'success', title: 'Showcase Published!', message: 'This project is now live on your profile portfolio.');
        $this->loadMessages();
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
        $this->loadMessages();
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
        $this->dispatch('toast', [
            'type' => 'info',
            'title' => 'Feature Unavailable!',
            'message' => 'Upgrade to premium plan to use voice and video calls on Allsers. Stay tuned!',
        ]);
    }

    public function rendering($view)
    {
        $view->title(__('Chat'));
    }
}; ?>

<div wire:poll.10s="refreshChat" x-data="{
    mobileView: '{{ $activeConversation ? 'chat' : 'list' }}',
    uistate_opened: false,
    interactionType: null,
    quotePrice: '',
    quoteTime: '',
    inquiryTitle: '',
    rating: 0,
    reviewComment: '',
    showPrompt: true,
}" x-init="setTimeout(() => showPrompt = false, 8000)"
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
            <div id="message-container" class="flex-1 overflow-y-auto p-4 md:p-6 space-y-6 min-h-0" x-data="{
                                                scrollToBottom() {
                                                    this.$el.scrollTo({ top: this.$el.scrollHeight, behavior: 'smooth' });
                                                }
                                            }" x-init="scrollToBottom()" @message-sent.window="scrollToBottom()">
                @foreach ($messages as $msg)
                    @php $isMine = $msg->user_id === auth()->id(); @endphp

                    @if ($msg->type !== 'text')
                        {{-- Engagement Cards --}}
                        <div class="flex justify-center w-full py-2">
                            <div class="w-full max-w-sm">
                                @if ($msg->type === 'inquiry')
                                    <div
                                        class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-[2.5rem] p-6 shadow-2xl relative overflow-hidden group">
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
                                                class="px-2.5 py-0.5 rounded-full text-[8px] font-black uppercase tracking-widest border {{ $urgencyClass }}">
                                                {{ $msg->engagement?->urgency_level ?? 'Normal' }}
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-4 mb-6 relative z-10">
                                            <div
                                                class="size-12 bg-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/20">
                                                <flux:icon name="magnifying-glass-circle" class="size-7 text-white" />
                                            </div>
                                            <div>
                                                <h4
                                                    class="font-black text-zinc-900 dark:text-zinc-100 uppercase tracking-widest text-[10px]">
                                                    {{ __('Initial Brief') }}
                                                </h4>
                                                <p class="text-sm font-bold text-zinc-500">
                                                    {{ __('Project Request') }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="space-y-4 relative z-10">
                                            <div
                                                class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-2xl border border-zinc-100 dark:border-zinc-800">
                                                <p class="text-[8px] font-black text-zinc-400 uppercase tracking-widest mb-1">
                                                    {{ __('Task Description') }}
                                                </p>
                                                <p class="text-sm font-bold text-zinc-700 dark:text-zinc-200 leading-relaxed italic">
                                                    "{{ $msg->content }}"</p>
                                            </div>

                                            @if ($msg->engagement?->location_context)
                                                <div class="flex items-center gap-2 px-1">
                                                    <flux:icon name="map-pin" class="size-3 text-zinc-400" />
                                                    <p class="text-[10px] font-bold text-zinc-500">
                                                        {{ $msg->engagement->location_context }}
                                                    </p>
                                                </div>
                                            @endif

                                            @if ($msg->engagement?->inquiry_photos)
                                                <div class="grid grid-cols-4 gap-2">
                                                    @foreach ($msg->engagement->inquiry_photos as $iPhoto)
                                                        <div
                                                            class="aspect-square rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800">
                                                            <img src="{{ \App\Models\Setting::asset($iPhoto) }}"
                                                                class="size-full object-cover cursor-pointer hover:scale-110 transition-transform"
                                                                @click="window.open('{{ \App\Models\Setting::asset($iPhoto) }}', '_blank')">
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($msg->type === 'quote')
                                    <div
                                        class="bg-indigo-50 dark:bg-indigo-900/10 border-2 border-indigo-500/30 rounded-[2rem] p-6 shadow-xl relative overflow-hidden">
                                        <div class="absolute top-0 right-0 p-8 bg-indigo-500/5 rounded-full -mr-10 -mt-10 blur-2xl">
                                        </div>
                                        <div class="flex items-center gap-4 mb-6 relative z-10">
                                            <div
                                                class="size-12 bg-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/20">
                                                <flux:icon name="currency-dollar" variant="solid" class="size-7 text-white" />
                                            </div>
                                            <div>
                                                <h4
                                                    class="font-black text-indigo-900 dark:text-indigo-100 uppercase tracking-widest text-[10px]">
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
                                                <p class="text-[8px] font-black text-zinc-400 uppercase tracking-widest mb-1">
                                                    {{ __('Budget') }}
                                                </p>
                                                <p class="text-lg font-black text-zinc-900 dark:text-white">
                                                    {{ $msg->engagement->price_estimate }}
                                                </p>
                                            </div>
                                            <div
                                                class="bg-white dark:bg-zinc-800 p-3 rounded-2xl border border-zinc-100 dark:border-zinc-700">
                                                <p class="text-[8px] font-black text-zinc-400 uppercase tracking-widest mb-1">
                                                    {{ __('Timeline') }}
                                                </p>
                                                <p class="text-lg font-black text-zinc-900 dark:text-white">
                                                    {{ $msg->engagement->completion_estimate }}
                                                </p>
                                            </div>
                                        </div>

                                        @if ($msg->engagement->status === 'quoted')
                                            <div class="flex gap-3 relative z-10">
                                                @if (!$isMine)
                                                    <button wire:click="acceptQuote"
                                                        class="flex-1 py-4 bg-indigo-600 text-white font-black rounded-2xl shadow-lg shadow-indigo-500/30 hover:scale-[1.02] active:scale-95 transition-all text-[10px] uppercase tracking-widest text-center">
                                                        {{ __('Confirm Deal') }}
                                                    </button>
                                                    <button wire:click="declineQuote"
                                                        class="flex-1 py-4 bg-white dark:bg-zinc-800 text-zinc-500 font-bold rounded-2xl border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 transition-all text-[10px] uppercase tracking-widest text-center">
                                                        {{ __('Decline') }}
                                                    </button>
                                                @else
                                                    <button wire:click="revokeQuote"
                                                        class="w-full py-4 bg-zinc-100 dark:bg-zinc-800 text-zinc-500 font-bold rounded-2xl border border-dashed border-zinc-300 dark:border-zinc-700 hover:bg-zinc-200 transition-all text-[10px] uppercase tracking-widest text-center">
                                                        {{ __('Withdraw Quote') }}
                                                    </button>
                                                @endif
                                            </div>
                                        @elseif($msg->engagement->status === 'accepted' || $msg->engagement->status === 'completed')
                                            <div
                                                class="w-full py-3 bg-green-500/10 text-green-600 font-black rounded-2xl border border-green-500/20 text-center text-xs uppercase tracking-widest flex items-center justify-center gap-2">
                                                <flux:icon name="check-circle" variant="solid" class="size-4" />
                                                {{ __('Deal Confirmed') }}
                                            </div>
                                        @endif
                                    </div>
                                @elseif($msg->type === 'handshake')
                                    <div
                                        class="bg-emerald-50 dark:bg-emerald-900/10 border-2 border-emerald-500/20 rounded-[2rem] p-5 text-center shadow-lg">
                                        <div
                                            class="size-16 bg-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg shadow-emerald-500/20">
                                            <flux:icon name="sparkles" variant="solid" class="size-8 text-white" />
                                        </div>
                                        <h4
                                            class="font-black text-emerald-900 dark:text-emerald-100 uppercase tracking-widest text-xs mb-1">
                                            {{ __('Deal Started!') }}
                                        </h4>
                                        <p class="text-xs font-bold text-emerald-600/70">
                                            {{ __('The professional is now officially booked.') }}
                                        </p>
                                    </div>
                                @elseif($msg->type === 'completion')
                                    <div
                                        class="bg-zinc-900 dark:bg-white rounded-[2.5rem] p-8 text-center shadow-2xl relative overflow-hidden">
                                        <div
                                            class="absolute top-0 right-0 p-12 bg-white/5 dark:bg-black/5 rounded-full -mr-16 -mt-16 blur-3xl">
                                        </div>
                                        <div
                                            class="size-20 bg-gradient-to-tr from-yellow-400 to-orange-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-xl">
                                            <flux:icon name="trophy" variant="solid" class="size-10 text-white" />
                                        </div>
                                        <h4 class="font-black text-white dark:text-zinc-900 text-xl tracking-tight mb-2">
                                            {{ __('Project Completed!') }}
                                        </h4>
                                        <p class="text-zinc-400 dark:text-zinc-500 text-sm font-medium mb-8 leading-relaxed">
                                            {{ __('The job has been marked as finished. Please share your experience to help the community.') }}
                                        </p>

                                        @if (!$isMine && !$msg->engagement->review_id)
                                            {{-- Rating Interaction --}}
                                            <div class="space-y-6">
                                                <div class="flex justify-center gap-3">
                                                    @for ($i = 1; $i <= 5; $i++)
                                                        <button @click="rating = {{ $i }}" class="transition-transform hover:scale-125"
                                                            x-bind:class="rating >= {{ $i }} ?
                                                                                                                                                                                                                                        'text-yellow-400 scale-110' :
                                                                                                                                                                                                                                        'text-zinc-700 dark:text-zinc-200'">
                                                            <flux:icon name="star" variant="solid" class="size-8" />
                                                        </button>
                                                    @endfor
                                                </div>
                                                <textarea x-model="reviewComment" placeholder="{{ __('Write a quick review...') }}"
                                                    class="w-full bg-zinc-800 dark:bg-zinc-200 border-none rounded-2xl text-white dark:text-zinc-900 text-sm focus:ring-2 focus:ring-yellow-500/50 p-4 resize-none"
                                                    rows="2"></textarea>
                                                <button @click="$wire.submitReview(rating, reviewComment)"
                                                    class="w-full py-4 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white font-black rounded-2xl shadow-xl hover:opacity-90 active:scale-95 transition-all text-xs uppercase tracking-[0.2em]"
                                                    x-bind:disabled="rating === 0">
                                                    {{ __('Publish Review') }}
                                                </button>
                                            </div>
                                        @elseif($msg->engagement->review_id)
                                            <div class="flex flex-col items-center gap-4">
                                                <div
                                                    class="py-3 px-6 bg-yellow-400 text-zinc-900 font-black rounded-2xl inline-flex items-center gap-2 text-xs uppercase tracking-widest">
                                                    <flux:icon name="check-circle" variant="solid" class="size-4" />
                                                    {{ __('Review Submitted') }}
                                                </div>

                                                @if ($isMine && !$msg->engagement->is_public)
                                                    <div class="w-full mt-4 p-6 bg-white/5 rounded-[2rem] border border-white/10">
                                                        <p class="text-[10px] font-black uppercase text-zinc-400 tracking-[0.2em] mb-4">
                                                            {{ __('Verified Success') }}
                                                        </p>
                                                        <button wire:click="showcaseJob"
                                                            class="w-full py-4 bg-purple-600 text-white font-black rounded-2xl shadow-xl shadow-purple-500/20 hover:scale-[1.02] active:scale-95 transition-all text-xs uppercase tracking-widest border border-purple-500">
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
                        <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }} w-full">
                            <div class="max-w-[85%] md:max-w-[70%] space-y-1">
                                <div
                                    class="rounded-[1.5rem] px-5 py-3 text-sm shadow-sm break-words overflow-hidden
                                                                                                                                                                                    @if ($isMine) bg-[var(--color-brand-purple)] text-white rounded-tr-none 
                                                                                                                                                                                    @else 
                                                                                                                                                                                    bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 border border-zinc-100 dark:border-zinc-700 rounded-tl-none @endif">

                                    @if ($msg->image_path)
                                        <div class="mb-2 rounded-lg overflow-hidden border border-white/20 bg-zinc-100/10 -mx-1">
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
                                                    {{ Str::limit($msg->document_name, 8) }}
                                                </p>
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
                                <div class="flex items-center gap-1.5 px-1 {{ $isMine ? 'justify-end' : 'justify-start' }}">
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
                    @endif
                @endforeach
            </div>

            <!-- Interaction Deck (Deal Flow) -->
            <div x-show="uistate_opened" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                class="mx-4 mb-4 p-6 bg-white dark:bg-zinc-800 rounded-[2.5rem] border border-zinc-200 dark:border-zinc-700 shadow-2xl relative overflow-hidden">
                <div
                    class="absolute top-0 right-0 p-12 bg-[var(--color-brand-purple)]/5 rounded-full -mr-16 -mt-16 blur-2xl">
                </div>

                <div class="flex items-center justify-between mb-6 relative z-10">
                    <h3 class="font-black text-zinc-900 dark:text-zinc-100 text-lg tracking-tight">
                        {{ __('Interaction Deck') }}
                    </h3>
                    <button @click="uistate_opened = false" class="text-zinc-400 hover:text-zinc-600 transition-colors">
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                </div>

                <div class="space-y-6 relative z-10">
                    @if (auth()->user()->isArtisan() && $activeConversation->activeEngagement)
                        @if ($activeConversation->activeEngagement->status === 'pending')
                            <div class="space-y-4">
                                <p class="text-[10px] font-black uppercase tracking-widest text-zinc-400">
                                    {{ __('Send Professional Quote') }}
                                </p>
                                <div class="grid grid-cols-2 gap-3">
                                    <flux:input x-model="quotePrice" type="text" placeholder="Price (e.g. ₦25k)"
                                        class="bg-zinc-50 dark:bg-zinc-900 border-none rounded-2xl text-sm focus:ring-2 focus:ring-purple-500/20" />
                                    <flux:input x-model="quoteTime" type="text" placeholder="Time (e.g. 2 days)"
                                        class="bg-zinc-50 dark:bg-zinc-900 border-none rounded-2xl text-sm focus:ring-2 focus:ring-purple-500/20" />
                                </div>
                                <button @click="$wire.sendQuote(quotePrice, quoteTime); uistate_opened = false"
                                    class="w-full py-3.5 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 font-black rounded-2xl shadow-xl hover:opacity-90 transition-all text-xs uppercase tracking-widest">
                                    {{ __('Send Quote Card') }}
                                </button>
                            </div>
                        @elseif($activeConversation->activeEngagement->status === 'accepted')
                            <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-3xl text-center">
                                <p class="text-xs font-bold text-emerald-600 mb-4">
                                    {{ __('This job is currently active.') }}
                                </p>
                                <button @click="$wire.completeJob(); uistate_opened = false"
                                    class="w-full py-3.5 bg-emerald-600 text-white font-black rounded-2xl shadow-lg shadow-emerald-500/20 hover:scale-[1.02] active:scale-95 transition-all text-xs uppercase tracking-widest">
                                    {{ __('Mark as Completed') }}
                                </button>
                            </div>
                        @elseif($activeConversation->activeEngagement->status === 'completed' && !$activeConversation->activeEngagement->is_public && $activeConversation->activeEngagement->completed_at?->gt(now()->subDays(7)))
                            <div class="p-4 bg-purple-500/10 border border-purple-500/20 rounded-3xl text-center">
                                <p class="text-xs font-bold text-purple-600 mb-4">
                                    {{ __('Job finished! Want to show it off?') }}
                                </p>
                                <button @click="$wire.showcaseJob(); uistate_opened = false"
                                    class="w-full py-3.5 bg-purple-600 text-white font-black rounded-2xl shadow-lg shadow-purple-500/20 hover:scale-[1.02] active:scale-95 transition-all text-xs uppercase tracking-widest">
                                    {{ __('Showcase to Portfolio') }}
                                </button>
                            </div>
                        @endif
                    @elseif(!auth()->user()->isArtisan() && !$activeConversation->activeEngagement)
                        <div class="space-y-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-zinc-400">
                                {{ __('Start a Deal') }}
                            </p>
                            <input x-model="inquiryTitle" type="text" placeholder="What do you need done?"
                                class="w-full bg-zinc-50 dark:bg-zinc-900 border-none rounded-2xl text-sm focus:ring-2 focus:ring-purple-500/20 py-3.5">
                            <button @click="$wire.sendInquiry(inquiryTitle); uistate_opened = false"
                                class="w-full py-3.5 bg-purple-600 text-white font-black rounded-2xl shadow-xl shadow-purple-500/20 hover:scale-[1.02] active:scale-95 transition-all text-xs uppercase tracking-widest">
                                {{ __('Send Inquiry Card') }}
                            </button>
                            <p class="text-[10px] text-zinc-500 text-center italic">
                                {{ __('Structured deals are safer and build your hiring history.') }}
                            </p>
                        </div>
                    @else
                        <div
                            class="py-8 text-center bg-zinc-50 dark:bg-zinc-800/50 rounded-3xl border border-dashed border-zinc-200 dark:border-zinc-700">
                            <flux:icon name="lock-closed" class="size-8 text-zinc-300 mx-auto mb-3" />
                            <p class="text-xs font-bold text-zinc-500">{{ __('No active actions available.') }}</p>
                        </div>
                    @endif
                </div>
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
                                    {{ Str::limit($document->getClientOriginalName(), 8) }}
                                </p>
                                <button @click="$wire.set('document', null)"
                                    class="absolute -top-1.5 -right-1.5 bg-zinc-400 text-white rounded-full p-0.5 hover:bg-zinc-500 shadow-sm">
                                    <flux:icon name="x-mark" class="size-3" />
                                </button>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="flex items-center gap-3 relative">
                    <!-- Contextual Action Prompt -->
                    @php
                        $engagementStatus = $activeConversation?->activeEngagement?->status;
                        $isArtisan = auth()->user()->isArtisan();
                        $promptText = match (true) {
                            !$engagementStatus && !$isArtisan => __('Start Deal'),
                            $engagementStatus === 'pending' && $isArtisan => __('Send Quote'),
                            $engagementStatus === 'accepted' && $isArtisan => __('Mark Finished'),
                            $engagementStatus === 'completed' && $isArtisan && !$activeConversation->activeEngagement->is_public && $activeConversation->activeEngagement->completed_at?->gt(now()->subDays(7)) => __('Showcase Work'),
                            default => null
                        };
                    @endphp

                    @if($promptText)
                        <div x-show="showPrompt && !uistate_opened" x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 -translate-x-2"
                            x-transition:enter-end="opacity-100 translate-x-0"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 translate-x-0"
                            x-transition:leave-end="opacity-0 -translate-x-2"
                            class="absolute left-full ml-3 px-3 py-1.5 bg-zinc-900 border border-zinc-800 text-white text-[10px] font-black uppercase tracking-widest rounded-full shadow-xl whitespace-nowrap z-50 pointer-events-none">
                            <div class="flex items-center gap-2">
                                <span class="size-1.5 bg-purple-500 rounded-full animate-pulse"></span>
                                {{ $promptText }}
                            </div>
                            <!-- Arrow pointer -->
                            <div
                                class="absolute top-1/2 -left-1 -translate-y-1/2 w-2 h-2 bg-zinc-900 rotate-45 border-l border-b border-zinc-800">
                            </div>
                        </div>
                    @endif

                    <button @click="uistate_opened = !uistate_opened; showPrompt = false" x-bind:class="uistate_opened ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900' :
                                                        'bg-zinc-100 dark:bg-zinc-800 text-zinc-500'"
                        class="p-3.5 rounded-2xl transition-all hover:scale-105 active:scale-95 shrink-0 relative group">

                        @if($promptText)
                            <span class="absolute -top-1 -right-1 flex h-3 w-3" x-show="!uistate_opened">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75"></span>
                                <span
                                    class="relative inline-flex rounded-full h-3 w-3 bg-purple-500 border border-white"></span>
                            </span>
                        @endif

                        <div x-bind:class="uistate_opened ? 'rotate-45' : ''" class="transition-transform">
                            <flux:icon name="plus" class="size-6" />
                        </div>
                    </button>

                    <form wire:submit="sendMessage"
                        class="flex-1 flex items-center gap-1 md:gap-2 bg-zinc-50 dark:bg-zinc-800 rounded-2xl px-2 md:px-4 py-2 border border-zinc-200 dark:border-zinc-700 focus-within:ring-2 focus-within:ring-[var(--color-brand-purple)]/20 focus-within:border-[var(--color-brand-purple)] transition-all">

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
                </div>
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

        .gradient-text {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
    <!-- Showcase Creator Modal -->
    <flux:modal wire:model="showShowcaseModal" variant="flyout" class="space-y-6">
        <div class="space-y-6">
            <div>
                <h2 class="text-xl font-black text-zinc-900 dark:text-white">{{ __('Create Project Showcase') }}</h2>
                <p class="text-xs text-zinc-500 mt-1">
                    {{ __('Share the results of your hard work with future clients.') }}
                </p>
            </div>

            <form wire:submit="submitShowcase" class="space-y-6">
                {{-- Public Description --}}
                <flux:textarea wire:model="showcaseDescription" label="Public Project Summary"
                    placeholder="Describe what you fixed, how you did it, and any special tools used..." rows="4" />

                {{-- Before/After Photos --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <flux:label>{{ __('Before Fix (Optional)') }}</flux:label>
                        <div
                            class="relative aspect-square rounded-2xl border-2 border-dashed border-zinc-200 dark:border-zinc-700 flex flex-col items-center justify-center cursor-pointer hover:border-purple-400 overflow-hidden">
                            @if ($showcaseBeforePhoto)
                                <img src="{{ $showcaseBeforePhoto->temporaryUrl() }}" class="size-full object-cover">
                            @else
                                <flux:icon name="photo" class="size-6 text-zinc-300" />
                                <span
                                    class="text-[8px] font-bold text-zinc-400 mt-1 uppercase">{{ __('Upload Before') }}</span>
                            @endif
                            <input type="file" wire:model="showcaseBeforePhoto"
                                class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <flux:label>{{ __('After Success') }}</flux:label>
                        <div
                            class="relative aspect-square rounded-2xl border-2 border-dashed border-zinc-200 dark:border-zinc-700 flex flex-col items-center justify-center cursor-pointer hover:border-purple-400 overflow-hidden">
                            @if ($showcaseAfterPhoto)
                                <img src="{{ $showcaseAfterPhoto->temporaryUrl() }}" class="size-full object-cover">
                            @else
                                <flux:icon name="sparkles" variant="solid" class="size-6 text-zinc-300" />
                                <span
                                    class="text-[8px] font-bold text-zinc-400 mt-1 uppercase">{{ __('Upload After') }}</span>
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
                            <p class="text-[10px] font-black uppercase text-zinc-900 dark:text-white">
                                {{ __('Share to Public Feed') }}
                            </p>
                            <p class="text-[9px] text-zinc-500">{{ __('Promote this success story to all users.') }}</p>
                        </div>
                    </div>
                    <flux:checkbox wire:model="showcaseToFeed" variant="toggle" />
                </div>

                @if($errors->any())
                    <div class="bg-red-50 text-red-500 p-3 rounded-xl text-[10px] font-bold">
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