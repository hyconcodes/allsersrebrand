<?php

use App\Models\Report;
use App\Models\User;
use App\Mail\UserBannedMail;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public $selectedReport = null;
    public $showBanModal = false;
    public $banUserId = null;
    public $banDays = 7;
    public $banReason = '';
    public $adminNotes = '';
    public $filterStatus = 'pending';
    public $userSearchQuery = '';

    public function mount()
    {
        // Check if user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access');
        }
    }

    public function with()
    {
        $query = Report::with(['user', 'post.user', 'reviewer'])->latest();

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        $users = [];
        if (strlen($this->userSearchQuery) >= 3) {
            $users = User::where(function ($q) {
                $q->where('name', 'like', '%' . $this->userSearchQuery . '%')
                    ->orWhere('username', 'like', '%' . $this->userSearchQuery . '%')
                    ->orWhere('email', 'like', '%' . $this->userSearchQuery . '%');
            })
                ->limit(5)
                ->get();
        }

        return [
            'reports' => $query->paginate(20),
            'searchResults' => $users,
            'pendingCount' => Report::where('status', 'pending')->count(),
            'reviewedCount' => Report::where('status', 'reviewed')->count(),
            'dismissedCount' => Report::where('status', 'dismissed')->count(),
        ];
    }

    public function openBanModal($userId)
    {
        $this->banUserId = $userId;
        $this->showBanModal = true;
        $this->banDays = 7;
        $this->banReason = '';
    }

    public function banUser()
    {
        $this->validate([
            'banDays' => 'required|integer|min:1|max:365',
            'banReason' => 'required|string|max:500',
        ]);

        $user = User::find($this->banUserId);

        if (!$user) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'User not found.');
            return;
        }

        $bannedUntil = now()->addDays($this->banDays);

        $user->update([
            'banned_until' => $bannedUntil,
        ]);

        // Send email notification
        try {
            Mail::to($user->email)->send(new UserBannedMail($user, $bannedUntil, $this->banReason));
        } catch (\Exception $e) {
            // Log error but don't fail the ban
            \Log::error('Failed to send ban email: ' . $e->getMessage());
        }

        $this->showBanModal = false;
        $this->dispatch('toast', type: 'success', title: 'User Banned', message: "User banned until {$bannedUntil->format('M d, Y')}");
    }

    public function deleteUser($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'User not found.');
            return;
        }

        if ($user->isAdmin()) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Cannot delete admin users.');
            return;
        }

        $user->delete();
        $this->dispatch('toast', type: 'success', title: 'User Deleted', message: 'User account has been permanently deleted.');
    }

    public function markAsReviewed($reportId)
    {
        $report = Report::find($reportId);

        if ($report) {
            $report->update([
                'status' => 'reviewed',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'admin_notes' => $this->adminNotes,
            ]);

            $this->adminNotes = '';
            $this->dispatch('toast', type: 'success', title: 'Report Reviewed', message: 'Report marked as reviewed.');
        }
    }

    public function dismissReport($reportId)
    {
        $report = Report::find($reportId);

        if ($report) {
            $report->update([
                'status' => 'dismissed',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $this->dispatch('toast', type: 'success', title: 'Report Dismissed', message: 'Report has been dismissed.');
        }
    }

    public function deletePost($postId)
    {
        $post = \App\Models\Post::find($postId);

        if ($post) {
            $post->delete();
            $this->dispatch('toast', type: 'success', title: 'Post Deleted', message: 'Post has been removed.');
        }
    }

    public function unbanUser($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'User not found.');
            return;
        }

        $user->update([
            'banned_until' => null,
        ]);

        $this->dispatch('toast', type: 'success', title: 'User Unbanned', message: 'User account has been reactivated.');
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">Admin Panel - Reports</h1>
        <p class="text-zinc-600 dark:text-zinc-400 mt-2">Manage flagged posts and user reports</p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-yellow-600 dark:text-yellow-400 font-medium">Pending</p>
                    <p class="text-3xl font-bold text-yellow-700 dark:text-yellow-300 mt-1">{{ $pendingCount }}</p>
                </div>
                <flux:icon name="exclamation-triangle" class="size-12 text-yellow-400" />
            </div>
        </div>

        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-green-600 dark:text-green-400 font-medium">Reviewed</p>
                    <p class="text-3xl font-bold text-green-700 dark:text-green-300 mt-1">{{ $reviewedCount }}</p>
                </div>
                <flux:icon name="check-circle" class="size-12 text-green-400" />
            </div>
        </div>

        <div class="bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 font-medium">Dismissed</p>
                    <p class="text-3xl font-bold text-zinc-700 dark:text-zinc-300 mt-1">{{ $dismissedCount }}</p>
                </div>
                <flux:icon name="x-circle" class="size-12 text-zinc-400" />
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 mb-6">
        <div class="flex border-b border-zinc-200 dark:border-zinc-800">
            <button wire:click="$set('filterStatus', 'pending')"
                class="px-6 py-3 text-sm font-medium transition-colors {{ $filterStatus === 'pending' ? 'text-[var(--color-brand-purple)] border-b-2 border-[var(--color-brand-purple)]' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}">
                Pending ({{ $pendingCount }})
            </button>
            <button wire:click="$set('filterStatus', 'reviewed')"
                class="px-6 py-3 text-sm font-medium transition-colors {{ $filterStatus === 'reviewed' ? 'text-[var(--color-brand-purple)] border-b-2 border-[var(--color-brand-purple)]' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}">
                Reviewed ({{ $reviewedCount }})
            </button>
            <button wire:click="$set('filterStatus', 'dismissed')"
                class="px-6 py-3 text-sm font-medium transition-colors {{ $filterStatus === 'dismissed' ? 'text-[var(--color-brand-purple)] border-b-2 border-[var(--color-brand-purple)]' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}">
                Dismissed ({{ $dismissedCount }})
            </button>
            <button wire:click="$set('filterStatus', 'all')"
                class="px-6 py-3 text-sm font-medium transition-colors {{ $filterStatus === 'all' ? 'text-[var(--color-brand-purple)] border-b-2 border-[var(--color-brand-purple)]' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}">
                All
            </button>
        </div>
    </div>

    <!-- User Search Section -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 mb-8">
        <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 mb-4">Search Users</h2>
        <div class="max-w-md">
            <flux:input wire:model.live.debounce.300ms="userSearchQuery"
                placeholder="Search by name, email, or username..." icon="magnifying-glass" />
        </div>

        @if (strlen($userSearchQuery) >= 3)
            <div class="mt-6 space-y-4">
                <h3 class="text-sm font-semibold text-zinc-500 uppercase tracking-wider">Search Results</h3>
                @forelse($searchResults as $user)
                    <div
                        class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-100 dark:border-zinc-800">
                        <div class="flex items-center gap-4">
                            <div
                                class="size-10 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center overflow-hidden">
                                @if ($user->profile_picture_url)
                                    <img src="{{ $user->profile_picture_url }}" class="size-full object-cover">
                                @else
                                    <div
                                        class="size-full flex items-center justify-center bg-[var(--color-brand-purple)] text-white font-bold text-sm">
                                        {{ substr($user->name, 0, 1) }}
                                    </div>
                                @endif
                            </div>
                            <div>
                                <p class="font-bold text-zinc-900 dark:text-zinc-100">{{ $user->name }}
                                    (@<span>{{ $user->username }}</span>)</p>
                                <p class="text-sm text-zinc-500">{{ $user->email }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($user->isBanned())
                                <span
                                    class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded-full font-medium mr-2">Banned
                                    until {{ $user->banned_until->format('M d, Y') }}</span>
                                <button wire:click="unbanUser({{ $user->id }})"
                                    class="px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-xs font-medium">
                                    Unban
                                </button>
                            @else
                                <button wire:click="openBanModal({{ $user->id }})"
                                    class="px-3 py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-xs font-medium">
                                    Ban User
                                </button>
                            @endif

                            @if (!$user->isAdmin())
                                <button wire:click="deleteUser({{ $user->id }})"
                                    wire:confirm="Are you sure you want to permanently delete this user?"
                                    class="px-3 py-1.5 bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 rounded-lg hover:bg-zinc-300 dark:hover:bg-zinc-600 transition-colors text-xs font-medium">
                                    Delete
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500 py-4">No users found matching "{{ $userSearchQuery }}"</p>
                @endforelse
            </div>
        @endif
    </div>

    <!-- Reports List -->
    <div class="space-y-4">
        @forelse($reports as $report)
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="size-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                            <flux:icon name="flag" class="size-5 text-red-600 dark:text-red-400" />
                        </div>
                        <div>
                            <h3 class="font-bold text-zinc-900 dark:text-zinc-100">Report #{{ $report->id }}</h3>
                            <p class="text-sm text-zinc-500">{{ $report->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    <span
                        class="px-3 py-1 rounded-full text-xs font-bold {{ $report->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : ($report->status === 'reviewed' ? 'bg-green-100 text-green-700' : 'bg-zinc-100 text-zinc-700') }}">
                        {{ ucfirst($report->status) }}
                    </span>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Reporter Info -->
                    <div class="space-y-3">
                        <h4 class="font-semibold text-zinc-900 dark:text-zinc-100">Reporter</h4>
                        <div class="flex items-center gap-3">
                            <div
                                class="size-8 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center overflow-hidden">
                                @if ($report->user->profile_picture_url)
                                    <img src="{{ $report->user->profile_picture_url }}" class="size-full object-cover">
                                @else
                                    <span class="text-xs font-bold">{{ $report->user->initials() }}</span>
                                @endif
                            </div>
                            <div>
                                <p class="font-medium text-sm">{{ $report->user->name }}</p>
                                <p class="text-xs text-zinc-500">{{ $report->user->email }}</p>
                            </div>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-3">
                            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Reason:</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $report->reason }}</p>
                        </div>
                    </div>

                    <!-- Post Owner Info -->
                    <div class="space-y-3">
                        <h4 class="font-semibold text-zinc-900 dark:text-zinc-100">Post Owner</h4>
                        <div class="flex items-center gap-3">
                            <div
                                class="size-8 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center overflow-hidden">
                                @if ($report->post->user->profile_picture_url)
                                    <img src="{{ $report->post->user->profile_picture_url }}"
                                        class="size-full object-cover">
                                @else
                                    <span class="text-xs font-bold">{{ $report->post->user->initials() }}</span>
                                @endif
                            </div>
                            <div>
                                <p class="font-medium text-sm">{{ $report->post->user->name }}</p>
                                <p class="text-xs text-zinc-500">{{ $report->post->user->email }}</p>
                            </div>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-3">
                            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Post Content:</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 line-clamp-3">
                                {{ $report->post->content }}
                            </p>
                        </div>

                        @if ($report->post->user->isBanned())
                            <div
                                class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                                <p class="text-sm font-bold text-red-700 dark:text-red-400 mb-1">⛔ User is Banned</p>
                                <p class="text-xs text-red-600 dark:text-red-400">Until:
                                    {{ $report->post->user->banned_until->format('M d, Y g:i A') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Actions -->
                @if ($report->status === 'pending')
                    <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-800 flex flex-wrap gap-3">
                        @if ($report->post->user->isBanned())
                            <button wire:click="unbanUser({{ $report->post->user_id }})"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">
                                <flux:icon name="check-circle" class="size-4 inline mr-1" /> Unban User
                            </button>
                        @else
                            <button wire:click="openBanModal({{ $report->post->user_id }})"
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium">
                                <flux:icon name="no-symbol" class="size-4 inline mr-1" /> Ban User
                            </button>
                        @endif
                        <button wire:click="deletePost({{ $report->post_id }})"
                            wire:confirm="Are you sure you want to delete this post?"
                            class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors text-sm font-medium">
                            <flux:icon name="trash" class="size-4 inline mr-1" /> Delete Post
                        </button>
                        <button wire:click="deleteUser({{ $report->post->user_id }})"
                            wire:confirm="Are you sure you want to permanently delete this user account?"
                            class="px-4 py-2 bg-red-800 text-white rounded-lg hover:bg-red-900 transition-colors text-sm font-medium">
                            <flux:icon name="user-minus" class="size-4 inline mr-1" /> Delete Account
                        </button>
                        <button wire:click="markAsReviewed({{ $report->id }})"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">
                            <flux:icon name="check" class="size-4 inline mr-1" /> Mark Reviewed
                        </button>
                        <button wire:click="dismissReport({{ $report->id }})"
                            class="px-4 py-2 bg-zinc-600 text-white rounded-lg hover:bg-zinc-700 transition-colors text-sm font-medium">
                            <flux:icon name="x-mark" class="size-4 inline mr-1" /> Dismiss
                        </button>
                    </div>
                @endif

                @if ($report->reviewed_by)
                    <div class="mt-4 text-sm text-zinc-500">
                        Reviewed by {{ $report->reviewer->name }} on
                        {{ $report->reviewed_at->format('M d, Y g:i A') }}
                    </div>
                @endif
            </div>
        @empty
            <div
                class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-12 text-center">
                <flux:icon name="inbox" class="size-16 text-zinc-300 mx-auto mb-4" />
                <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 mb-2">No Reports</h3>
                <p class="text-zinc-500">No reports found for the selected filter.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $reports->links() }}
    </div>

    <!-- Ban User Modal -->
    <flux:modal name="ban-user-modal" wire:model="showBanModal" class="sm:max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Ban User</flux:heading>
                <flux:subheading>Temporarily suspend user account</flux:subheading>
            </div>

            <div class="space-y-4">
                <div>
                    <flux:label>Ban Duration (Days)</flux:label>
                    <flux:input type="number" wire:model="banDays" min="1" max="365" />
                </div>

                <div>
                    <flux:label>Reason for Ban</flux:label>
                    <flux:textarea wire:model="banReason" rows="3"
                        placeholder="Explain why this user is being banned..." />
                </div>
            </div>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="banUser">Ban User</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
