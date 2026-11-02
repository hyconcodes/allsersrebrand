<?php

use App\Mail\AdminMessageMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public string $searchQuery = '';

    public string $roleFilter = 'all';

    /** @var array<int, int> */
    public array $selected = [];

    public string $title = '';

    public string $body = '';

    public function mount(): void
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403, 'Unauthorized access');
        }
    }

    public function updatingSearchQuery(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'Please add a message title.',
            'title.max' => 'The title must not exceed 191 characters.',
            'body.required' => 'Please write a message body.',
            'body.max' => 'The message must not exceed 5000 characters.',
        ];
    }

    public function toggleRecipient(int $userId): void
    {
        $key = array_search($userId, $this->selected);

        if ($key !== false) {
            unset($this->selected[$key]);
            $this->selected = array_values($this->selected);
        } else {
            $this->selected[] = $userId;
        }
    }

    public function selectAllOnPage(): void
    {
        foreach ($this->buildRecipientsQuery()->orderBy('name')->paginate(8)->items() as $user) {
            if (! in_array($user->id, $this->selected)) {
                $this->selected[] = $user->id;
            }
        }

        $this->dispatch('toast', type: 'info', title: 'Recipients selected',
            message: 'Selected '.count($this->selected).' recipient(s). They stay selected across pages.');
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function sendMessages(): void
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        if (count($this->selected) === 0) {
            $this->dispatch('toast', type: 'warning', title: 'No recipients',
                message: 'Select at least one user before sending.');

            return;
        }

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Message incomplete',
                message: 'Add a title and a body before sending.');

            throw $e;
        }

        $recipients = User::whereIn('id', $this->selected)
            ->where(fn ($q) => $q->whereNull('banned_until')->orWhere('banned_until', '<=', now()))
            ->orderBy('name')
            ->get();

        if ($recipients->isEmpty()) {
            $this->dispatch('toast', type: 'warning', title: 'No active recipients',
                message: 'The selected accounts are no longer available.');

            return;
        }

        $totalExpected = $recipients->count();
        $sentCount = 0;
        $failedCount = 0;
        $failedNames = [];

        try {
            foreach ($recipients as $recipient) {
                if (empty($recipient->email)) {
                    $failedCount++;
                    $failedNames[] = $recipient->name;

                    continue;
                }

                try {
                    Mail::to($recipient->email)->send(new AdminMessageMail(
                        $recipient,
                        trim($this->title),
                        trim($this->body),
                    ));
                    $sentCount++;
                } catch (\Throwable $e) {
                    $failedCount++;
                    $failedNames[] = $recipient->name;
                    Log::error('Admin message could not be delivered.', [
                        'recipient_id' => $recipient->id,
                        'email' => $recipient->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Admin broadcast crashed before completion.', ['error' => $e->getMessage()]);

            $this->dispatch('toast', type: 'error', title: 'Send aborted',
                message: 'Something went wrong while sending. No crash occurred — check the logs and try again.');

            return;
        }

        try {
            if ($sentCount > 0 && $failedCount === 0) {
                $this->dispatch('toast', type: 'success', title: 'Messages sent',
                    message: '('.$sentCount.'/'.($sentCount + $failedCount).') delivered successfully to '.\Illuminate\Support\Str::plural('user', $sentCount).' - '.$totalExpected.' selected.');
            } elseif ($sentCount > 0) {
                $this->dispatch('toast', type: 'warning', title: 'Partially sent',
                    message: '('.$sentCount.'/'.$totalExpected.') delivered. Failed: '.\Illuminate\Support\Str::limit(implode(', ', $failedNames), 120).'. Failures were logged.');
            } else {
                $this->dispatch('toast', type: 'error', title: 'Send failed',
                    message: '(0/'.$totalExpected.') delivered. No emails left the server — failures were logged, recipients kept selected.');
            }
        } catch (\Throwable $e) {
            Log::error('Admin broadcast toast failed.', ['error' => $e->getMessage()]);
        }

        // Keep recipients selected on total failure; clear only when at least one went through.
        if ($sentCount > 0) {
            $this->selected = [];
            $this->title = '';
            $this->body = '';
        }
    }

    protected function buildRecipientsQuery()
    {
        return User::query()
            ->where(fn ($q) => $q->whereNull('banned_until')->orWhere('banned_until', '<=', now()))
            ->when($this->roleFilter === 'artisan', fn ($q) => $q->where('role', 'artisan'))
            ->when($this->roleFilter === 'guest', fn ($q) => $q->where('role', 'guest'))
            ->when(filled(trim($this->searchQuery)), function ($q) {
                $search = trim($this->searchQuery);
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('work', 'like', "%{$search}%")
                        ->orWhere('bio', 'like', "%{$search}%");
                });
            });
    }

    public function with(): array
    {
        return [
            'recipients' => $this->buildRecipientsQuery()->orderBy('name')->paginate(8),
            'selectedUsers' => User::whereIn('id', $this->selected)->orderBy('name')->get(),
            'activeMailDriver' => config('mail.default'),
        ];
    }
}
?>
<div class="max-w-4xl mx-auto">
    <x-top-bar>
        <x-slot:left>@include('partials.admin-top-tabs')</x-slot:left>
    </x-top-bar>

    <div class="px-4 py-8 space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 italic uppercase tracking-tighter">
                    {{ __('Messages') }}</h1>
                <p class="text-sm text-zinc-500">{{ __('Send an email broadcast to any of your users') }}</p>
            </div>
            <span class="shrink-0 inline-flex items-center gap-1.5 text-xs font-bold uppercase px-2.5 py-1 rounded-full {{ $activeMailDriver === 'mailtrap' ? 'bg-purple-100 text-purple-700 dark:bg-purple-500/15 dark:text-purple-300' : 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300' }}">
                <span class="size-1.5 rounded-full {{ $activeMailDriver === 'mailtrap' ? 'bg-purple-500' : 'bg-green-500' }}"></span>
                {{ $activeMailDriver === 'mailtrap' ? __('via Mailtrap API') : __('via Server SMTP') }}
            </span>
        </div>

        <!-- Step 1: Choose recipients -->
        <div class="bg-white dark:bg-zinc-900 rounded-2xl sm:rounded-3xl border border-zinc-200 dark:border-zinc-800 p-5 sm:p-8 space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold uppercase text-zinc-900 dark:text-white">
                        {{ __('1. Choose recipients') }}</h3>
                    <p class="mt-1 text-xs text-zinc-500">{{ __('Search by personal info (name, username, email) or artisan work info (work, bio). Banned users are hidden automatically.') }}</p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-3.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 3.75.772 9.37 9.37 0 0 0 3.75-.772M15 19.128v-.003c0-1.31-.53-2.566-1.477-3.493A5.25 5.25 0 0 0 9.75 14.25a5.25 5.25 0 0 0-3.773 1.382A4.875 4.875 0 0 0 4.5 19.125v.003M15 19.128v.003m0-.003a9.375 9.375 0 0 1-6 0m-4.5 0A9.37 9.37 0 0 1 .75 18.36a9.38 9.38 0 0 1 4.5-3.235M9.75 14.25a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Zm9-3.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    {{ count($selected) }} {{ __('selected') }}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1.5">{{ __('Search') }}</label>
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400 pointer-events-none" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" />
                            <path stroke-linecap="round" d="m20 20-4-4" />
                        </svg>
                        <input type="search" wire:model.live.debounce.250ms="searchQuery"
                            placeholder="{{ __('Search name, @username, email, work or bio...') }}"
                            aria-label="{{ __('Search users by name, username, email, work or bio') }}"
                            class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 py-2.5 pl-10 pr-3 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1.5">{{ __('Role') }}</label>
                    <fieldset>
                        <legend class="sr-only">{{ __('Filter recipients by role') }}</legend>
                        <div class="grid grid-cols-3 gap-1 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 p-1">
                            @foreach (['all' => __('All'), 'guest' => __('Guests'), 'artisan' => __('Artisans')] as $roleValue => $roleLabel)
                                <label wire:key="role-filter-{{ $roleValue }}"
                                    class="cursor-pointer rounded-lg px-2 py-2 text-center text-xs font-bold text-zinc-500 transition-colors hover:text-zinc-900 dark:hover:text-zinc-100 has-[:checked]:bg-white has-[:checked]:text-purple-700 has-[:checked]:shadow-sm dark:has-[:checked]:bg-zinc-700 dark:has-[:checked]:text-purple-300">
                                    <input type="radio" name="role-filter" value="{{ $roleValue }}"
                                        wire:model.live="roleFilter" class="sr-only">
                                    {{ $roleLabel }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
            </div>

            <div class="space-y-3">
                @forelse ($recipients as $user)
                    @php
                        $isSelected = in_array($user->id, $selected);
                    @endphp
                    <button type="button" wire:click="toggleRecipient({{ $user->id }})"
                        wire:key="recipient-{{ $user->id }}"
                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                        class="w-full text-left cursor-pointer p-4 sm:p-5 rounded-2xl border transition-colors flex items-start gap-3 sm:items-center focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 @if($isSelected) bg-purple-50 dark:bg-purple-500/5 border-purple-300 dark:border-purple-500/40 @else bg-zinc-50 dark:bg-zinc-800/50 border-zinc-100 dark:border-zinc-800 hover:border-purple-200 dark:hover:border-purple-500/30 @endif">
                        <div class="shrink-0 pt-0.5 sm:pt-0">
                            <div class="size-6 rounded-full border-2 flex items-center justify-center transition-colors {{ $isSelected ? 'bg-purple-600 border-purple-600 text-white' : 'border-zinc-300 dark:border-zinc-600' }}">
                                @if ($isSelected)
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="size-3" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
                                    </svg>
                                @endif
                            </div>
                        </div>
                        <div class="size-10 sm:size-12 rounded-2xl overflow-hidden shrink-0 border border-zinc-200 dark:border-zinc-700">
                            @if ($user->profile_picture_url)
                                <img loading="lazy" src="{{ $user->profile_picture_url }}" class="size-full object-cover" alt="">
                            @else
                                <div class="size-full flex items-center justify-center bg-zinc-200 dark:bg-zinc-700 text-sm font-bold">{{ $user->initials() }}</div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-zinc-900 dark:text-white flex items-center gap-2 flex-wrap">
                                <span class="truncate">{{ $user->name }}</span>
                                <span class="shrink-0 px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $user->role === 'artisan' ? 'bg-zinc-100 text-purple-600 dark:bg-purple-500/15 dark:text-purple-300' : 'bg-blue-100 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300' }}">
                                    {{ $user->role }}
                                </span>
                            </p>
                            <p class="text-xs text-zinc-500 truncate">@<span>{{ $user->username }}</span> &middot; {{ $user->email }}</p>
                            @if ($user->isArtisan() && filled($user->work))
                                <p class="text-xs text-zinc-500 truncate mt-0.5">
                                    <span class="font-semibold text-zinc-600 dark:text-zinc-400">{{ $user->work }}</span>
                                    @if (filled($user->experience_year)) &middot; {{ $user->experience_year }} {{ __('yr experience') }} @endif
                                </p>
                            @endif
                        </div>
                    </button>
                @empty
                    <div class="text-center py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-12 text-zinc-200 dark:text-zinc-800 mx-auto mb-4" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" />
                            <path stroke-linecap="round" d="m20 20-4-4" />
                        </svg>
                        @if (filled(trim($searchQuery)))
                            <p class="text-sm text-zinc-400">{{ __('No users found matching') }} "{{ $searchQuery }}"</p>
                        @else
                            <p class="text-sm text-zinc-400">{{ __('No users found.') }}</p>
                        @endif
                    </div>
                @endforelse
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <button type="button" wire:click="selectAllOnPage" wire:loading.attr="disabled"
                    class="rounded-lg px-3 py-2 text-xs font-bold uppercase text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 disabled:cursor-wait disabled:opacity-50 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white">
                    {{ __('Select all on this page') }}
                </button>
                @if (count($selected) === 0)
                    <button type="button" disabled class="rounded-lg px-3 py-2 text-xs font-bold uppercase text-red-600 opacity-50 dark:text-red-400">
                        {{ __('Clear selection') }}
                    </button>
                @else
                    <button type="button" wire:click="clearSelection" wire:loading.attr="disabled"
                        class="rounded-lg px-3 py-2 text-xs font-bold uppercase text-red-600 hover:bg-red-50 disabled:cursor-wait disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-500/10">
                        {{ __('Clear selection') }}
                    </button>
                @endif
            </div>

            @if ($recipients->hasPages())
                <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    {{ $recipients->links() }}
                </div>
            @endif
        </div>

        <!-- Step 2: Compose & send -->
        <div class="bg-white dark:bg-zinc-900 rounded-2xl sm:rounded-3xl border border-zinc-200 dark:border-zinc-800 p-5 sm:p-8 space-y-5">
            <div>
                <h3 class="text-sm font-bold uppercase text-zinc-900 dark:text-white">
                    {{ __('2. Compose your message') }}</h3>
                <p class="mt-1 text-xs text-zinc-500">{{ __('The title becomes the email subject. Each selected user receives their own email copy.') }}</p>
            </div>

            @if (count($selected) > 0)
                <div class="flex flex-wrap gap-2">
                    @foreach ($selectedUsers as $selectedUser)
                        <span wire:key="chip-{{ $selectedUser->id }}"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-purple-50 dark:bg-purple-500/10 border border-purple-200 dark:border-purple-500/30 text-xs font-semibold text-purple-700 dark:text-purple-300">
                            {{ $selectedUser->name }}
                            <button type="button" wire:click="toggleRecipient({{ $selectedUser->id }})"
                                aria-label="{{ __('Remove :name', ['name' => $selectedUser->name]) }}"
                                class="rounded-full p-0.5 hover:text-red-500 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-3" aria-hidden="true">
                                    <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </span>
                    @endforeach
                </div>
            @endif

            <div>
                <label for="message-title" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-zinc-500">
                    {{ __('Title (email subject)') }}
                </label>
                <input id="message-title" type="text" wire:model="title" maxlength="191"
                    placeholder="{{ __('e.g. Important update about your Allsers account') }}"
                    class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20">
                @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="message-body" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-zinc-500">
                    {{ __('Message') }}
                </label>
                <textarea id="message-body" wire:model="body" rows="6" maxlength="5000"
                    placeholder="{{ __('Write your message... Each recipient will receive it by email.') }}"
                    class="w-full resize-y rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20"></textarea>
                @error('body') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3">
                <p class="text-xs text-zinc-500 sm:mr-auto">
                    {{ __('Ready to send to') }} <span class="font-bold text-zinc-700 dark:text-zinc-300">{{ count($selected) }}</span>
                    {{ __('recipient(s) via') }} {{ $activeMailDriver === 'mailtrap' ? __('Mailtrap API') : __('Server SMTP') }}.
                </p>
                <button type="button" wire:click="sendMessages" wire:loading.attr="disabled" wire:target="sendMessages"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-purple-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition-colors hover:bg-purple-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60 dark:focus-visible:ring-offset-zinc-900">
                    <span wire:loading.remove wire:target="sendMessages">
                        {{ count($selected) > 0 ? __('Send to :count user(s)', ['count' => count($selected)]) : __('Send') }}
                    </span>
                    <span wire:loading wire:target="sendMessages">{{ __('Sending...') }}</span>
                    <svg wire:loading.remove wire:target="sendMessages" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-4" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 12 13-7-4 14-3-6-6-1Zm0 0 6 1" />
                    </svg>
            </div>
        </div>
    </div>
</div>
