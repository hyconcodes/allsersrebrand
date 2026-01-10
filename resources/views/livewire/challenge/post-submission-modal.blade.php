<?php

use App\Models\Post;
use App\Models\Challenge;
use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $challengeId;
    public $hashtag;
    public $content = '';
    public $images = [];
    public $video;
    public $show = false;

    #[On('open-challenge-post-modal')]
    public function open($challengeId, $hashtag)
    {
        $this->challengeId = $challengeId;
        $this->hashtag = $hashtag;
        $this->show = true;
    }

    public function submit()
    {
        $this->validate([
            'content' => 'required|min:10',
            'images' => 'nullable|array|max:4',
            'images.*' => 'image|max:5120',
            'video' => 'nullable|mimes:mp4,mov,avi,wmv|max:51200',
        ]);

        if (count($this->images) > 0 && $this->video) {
            $this->addError('media', 'You can only upload images OR a video, not both.');
            return;
        }

        $imagePaths = [];
        foreach ($this->images as $image) {
            $imagePaths[] = $image->store('posts/challenges', 'public');
        }

        $videoPath = $this->video ? $this->video->store('posts/challenges/videos', 'public') : null;

        // Auto append hashtag if not present
        if (!Str::contains($this->content, '#' . $this->hashtag)) {
            $this->content .= "\n\n#" . $this->hashtag;
        }

        $post = Post::create([
            'user_id' => auth()->id(),
            'challenge_id' => $this->challengeId,
            'content' => $this->content,
            'images' => count($imagePaths) > 0 ? implode(',', $imagePaths) : null,
            'video' => $videoPath,
        ]);

        $this->notifyMentionedUsers($post);

        $this->reset(['content', 'images', 'video', 'show']);
        $this->dispatch('toast', type: 'success', title: 'Submitted!', message: 'Your entry has been posted to the challenge.');
        $this->dispatch('post-created');
    }

    protected function notifyMentionedUsers(Post $post)
    {
        if (empty($post->content)) return;

        preg_match_all('/(?:^|\\s)@([a-zA-Z0-9_]+)/', $post->content, $matches);
        $usernames = array_unique($matches[1]);

        if (empty($usernames)) return;

        $users = \App\Models\User::whereIn('username', $usernames)->get();

        foreach ($users as $user) {
            if ($user->id !== auth()->id()) {
                $user->notify(new \App\Notifications\UserTagged($post, auth()->user()));
            }
        }
    }
}; ?>

<flux:modal name="challenge-post-modal" wire:model="show" class="sm:max-w-xl">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Submit Entry') }}</flux:heading>
            <flux:subheading>{{ __('Participating in') }} <span class="font-bold text-[var(--color-brand-purple)]">#{{ $hashtag }}</span></flux:subheading>
        </div>

        <div class="space-y-4" x-data="{ 
            insertEmoji(emoji) {
                const el = $wire.$el.querySelector('textarea');
                const start = el.selectionStart;
                const end = el.selectionEnd;
                const text = $wire.content;
                $wire.content = text.substring(0, start) + emoji + text.substring(end);
                el.focus();
                // Set cursor position after insertion
                setTimeout(() => el.setSelectionRange(start + emoji.length, start + emoji.length), 0);
            }
        }">
            <div>
                <flux:textarea wire:model="content" placeholder="Tell us about your work..." rows="6" />
                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach(['🔥', '✨', '🛠️', '🎨', '🚀', '👏', '🙌'] as $emoji)
                        <button type="button" @click="insertEmoji('{{ $emoji }}')" class="hover:scale-125 transition-transform p-1 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ $emoji }}</button>
                    @endforeach
                </div>
                <p class="text-[10px] text-zinc-500 mt-1">{{ __('Hashtag') }} #{{ $hashtag }} {{ __('will be automatically added.') }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <flux:label>{{ __('Images (Max 4)') }}</flux:label>
                    <flux:input type="file" wire:model="images" multiple />
                    <flux:error name="images" />
                </div>
                <div class="space-y-2">
                    <flux:label>{{ __('Video (Max 1)') }}</flux:label>
                    <flux:input type="file" wire:model="video" />
                    <flux:error name="video" />
                </div>
            </div>

            @if(count($images) > 0)
                <div class="grid grid-cols-4 gap-2">
                    @foreach($images as $img)
                        <div class="aspect-square rounded-lg border border-zinc-200 overflow-hidden">
                            <img src="{{ $img->temporaryUrl() }}" class="size-full object-cover">
                        </div>
                    @endforeach
                </div>
            @endif

            <flux:error name="media" />
        </div>

        <div class="flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('show', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="submit">{{ __('Post Submission') }}</flux:button>
        </div>
    </div>
</flux:modal>
