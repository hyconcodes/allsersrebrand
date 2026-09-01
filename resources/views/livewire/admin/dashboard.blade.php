<?php

use App\Models\User;
use App\Models\Post;
use App\Models\Report;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

new class extends Component {
    public $totalUsers;
    public $totalPosts;
    public $totalReports;
    public $newUsersThisMonth;
    public $growthPercentage;
    public $artisansCount;
    public $guestsCount;

    // Analytical Data for Charts
    public $userGrowthChartData = [];
    public $roleDistributionData = [];
    public $topArtisans = [];
    public $recentReports = [];

    public $postSearchQuery = '';
    public $userSearchQuery = '';
    public $selectedUser = null;
    public $showUserModal = false;

    public function mount()
    {
        $this->loadStats();
        $this->loadChartData();
        $this->loadTopLists();
    }

    public function with()
    {
        $posts = [];
        if (strlen($this->postSearchQuery) >= 3) {
            $posts = Post::with('user')
                ->where('content', 'like', '%' . $this->postSearchQuery . '%')
                ->orWhereHas('user', function ($q) {
                    $q->where('name', 'like', '%' . $this->postSearchQuery . '%')->orWhere('username', 'like', '%' . $this->postSearchQuery . '%');
                })
                ->latest()
                ->limit(10)
                ->get();
        }

        $managedUsers = [];
        if (strlen($this->userSearchQuery) >= 3) {
            $managedUsers = User::where('name', 'like', '%' . $this->userSearchQuery . '%')
                ->orWhere('username', 'like', '%' . $this->userSearchQuery . '%')
                ->orWhere('email', 'like', '%' . $this->userSearchQuery . '%')
                ->latest()
                ->limit(10)
                ->get();
        }

        return [
            'managedPosts' => $posts,
            'managedUsers' => $managedUsers,
        ];
    }

    public function loadStats()
    {
        $stats = Cache::remember('admin:dashboard:stats', 300, function () {
            $currentMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();
            $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

            $newUsersThisMonth = User::where('created_at', '>=', $currentMonth)->count();
            $lastMonthUsers = User::whereBetween('created_at', [$lastMonth, $endOfLastMonth])->count();

            $growthPercentage = 0;
            if ($lastMonthUsers > 0) {
                $growthPercentage = (($newUsersThisMonth - $lastMonthUsers) / $lastMonthUsers) * 100;
            } elseif ($newUsersThisMonth > 0) {
                $growthPercentage = 100;
            }

            return [
                'totalUsers' => User::count(),
                'totalPosts' => Post::count(),
                'totalReports' => Report::where('status', 'pending')->count(),
                'newUsersThisMonth' => $newUsersThisMonth,
                'growthPercentage' => $growthPercentage,
                'artisansCount' => User::where('role', 'artisan')->count(),
                'guestsCount' => User::where('role', 'guest')->count(),
            ];
        });

        $this->totalUsers = $stats['totalUsers'];
        $this->totalPosts = $stats['totalPosts'];
        $this->totalReports = $stats['totalReports'];
        $this->newUsersThisMonth = $stats['newUsersThisMonth'];
        $this->growthPercentage = $stats['growthPercentage'];
        $this->artisansCount = $stats['artisansCount'];
        $this->guestsCount = $stats['guestsCount'];
    }

    public function loadChartData()
    {
        // 1. User Growth Line Chart Data (Current Month Days)
        $daysInMonth = Carbon::now()->daysInMonth;
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $dailyGrowth = User::select(DB::raw('DAY(created_at) as day'), DB::raw('count(*) as count'))->whereMonth('created_at', $currentMonth)->whereYear('created_at', $currentYear)->groupBy('day')->pluck('count', 'day')->toArray();

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $this->userGrowthChartData['labels'][] = $i;
            $this->userGrowthChartData['data'][] = $dailyGrowth[$i] ?? 0;
        }

        // 2. Role Distribution Pie Chart Data
        $this->roleDistributionData = [
            'labels' => ['Artisans', 'Guests'],
            'data' => [$this->artisansCount, $this->guestsCount],
        ];
    }

    public function loadTopLists()
    {
        $this->topArtisans = User::where('role', 'artisan')->orderBy('smart_rating', 'desc')->limit(5)->get();

        $this->recentReports = Report::with(['user', 'post.user'])
            ->where('status', 'pending')
            ->latest()
            ->limit(5)
            ->get();
    }

    public function viewUser($userId)
    {
        $user = User::withCount('pushSubscriptions')->findOrFail($userId);
        $this->selectedUser = $user;
        $this->showUserModal = true;
    }

    public function closeUserModal()
    {
        $this->showUserModal = false;
        $this->selectedUser = null;
    }

    public function updateUserRole($userId, $newRole)
    {
        $user = User::findOrFail($userId);
        $user->update(['role' => $newRole]);

        $this->loadStats();
        $this->loadChartData();
        $this->dispatch('toast', type: 'success', title: 'Role Updated', message: "User {$user->name} is now a {$newRole}.");
    }

    public function deletePost($postId)
    {
        $post = Post::findOrFail($postId);
        $post->delete();

        $this->loadStats();
        $this->loadTopLists();
        $this->dispatch('toast', type: 'success', title: 'Post Deleted', message: 'The post has been successfully removed.');
    }

    public function resolveReport($reportId, $action)
    {
        $report = Report::findOrFail($reportId);

        if ($action === 'delete') {
            $report->post?->delete();
            $report->update([
                'status' => 'reviewed',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'admin_notes' => 'Post deleted by admin.',
            ]);
        } else {
            $report->update([
                'status' => 'dismissed',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'admin_notes' => 'Report dismissed.',
            ]);
        }

        $this->loadTopLists();
        $this->dispatch('toast', type: 'success', title: 'Report Handled', message: 'Report status updated.');
    }
}; ?>

<div class="px-4 py-8 max-w-7xl mx-auto space-y-8" x-data="{ activeTab: 'overview' }">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 italic uppercase tracking-tighter">
                {{ __('Control Center') }}</h1>
            <p class="text-sm text-zinc-500">{{ __('Overview of your social ecosystem') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="size-2 bg-green-500 rounded-full animate-pulse"></span>
            <span class="text-xs font-bold text-zinc-400 uppercase ">{{ __('Live System Data') }}</span>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="flex overflow-x-auto gap-2 p-1 bg-zinc-100 dark:bg-zinc-800 rounded-2xl w-fit">
        <button @click="activeTab = 'overview'"
            :class="activeTab === 'overview' ? 'bg-white dark:bg-zinc-700  text-zinc-900 dark:text-white' :
                'text-zinc-500 hover:text-zinc-700'"
            class="px-6 py-2 rounded-xl text-xs font-bold uppercase  transition-all">
            {{ __('Overview') }}
        </button>
        <button @click="activeTab = 'posts'"
            :class="activeTab === 'posts' ? 'bg-white dark:bg-zinc-700  text-zinc-900 dark:text-white' :
                'text-zinc-500 hover:text-zinc-700'"
            class="px-6 py-2 rounded-xl text-xs font-bold uppercase  transition-all">
            {{ __('Posts') }}
        </button>
        <button @click="activeTab = 'users'"
            :class="activeTab === 'users' ? 'bg-white dark:bg-zinc-700  text-zinc-900 dark:text-white' :
                'text-zinc-500 hover:text-zinc-700'"
            class="px-6 py-2 rounded-xl text-xs font-bold uppercase  transition-all">
            {{ __('Users') }}
        </button>
    </div>

    <div x-show="activeTab === 'overview'" class="space-y-8">
        <!-- Quick Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Total Users -->
            <div
                class="bg-white dark:bg-zinc-900 p-6 rounded-3xl border border-zinc-200 dark:border-zinc-800  overflow-hidden relative group">
                <div
                    class="absolute -right-4 -top-4 size-24 bg-purple-500/10 rounded-full blur-2xl group-hover:bg-purple-500/20 transition-all">
                </div>
                <div class="relative z-10 flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between mb-4">
                        <div
                            class="size-10 rounded-xl bg-zinc-100 dark:bg-purple-500/20 flex items-center justify-center text-purple-600 dark:text-purple-400">
                            <flux:icon name="users" class="size-5" />
                        </div>
                        @if ($growthPercentage > 0)
                            <span class="text-xs font-bold text-green-500 flex items-center gap-1">
                                <flux:icon name="arrow-trending-up" class="size-3" />
                                +{{ round($growthPercentage, 1) }}%
                            </span>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs uppercase font-bold  text-zinc-400 mb-1">
                            {{ __('Total Users') }}</p>
                        <h2 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                            {{ number_format($totalUsers) }}
                        </h2>
                    </div>
                </div>
            </div>

            <!-- Total Posts -->
            <div
                class="bg-white dark:bg-zinc-900 p-6 rounded-3xl border border-zinc-200 dark:border-zinc-800  overflow-hidden relative group">
                <div
                    class="absolute -right-4 -top-4 size-24 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition-all">
                </div>
                <div class="relative z-10 flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between mb-4">
                        <div
                            class="size-10 rounded-xl bg-blue-100 dark:bg-blue-500/20 flex items-center justify-center text-blue-600 dark:text-blue-400">
                            <flux:icon name="chat-bubble-left-right" class="size-5" />
                        </div>
                    </div>
                    <div>
                        <p class="text-xs uppercase font-bold  text-zinc-400 mb-1">
                            {{ __('Total Posts') }}</p>
                        <h2 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                            {{ number_format($totalPosts) }}
                        </h2>
                    </div>
                </div>
            </div>

            <!-- Pending Reports -->
            <div
                class="bg-white dark:bg-zinc-900 p-6 rounded-3xl border border-zinc-200 dark:border-zinc-800  overflow-hidden relative group">
                <div
                    class="absolute -right-4 -top-4 size-24 bg-red-500/10 rounded-full blur-2xl group-hover:bg-red-500/20 transition-all">
                </div>
                <div class="relative z-10 flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between mb-4">
                        <div
                            class="size-10 rounded-xl bg-red-100 dark:bg-red-500/20 flex items-center justify-center text-red-600 dark:text-red-400">
                            <flux:icon name="exclamation-triangle" class="size-5" />
                        </div>
                        @if ($totalReports > 0)
                            <span class="size-2 bg-red-500 rounded-full animate-ping"></span>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs uppercase font-bold  text-zinc-400 mb-1">
                            {{ __('Pending Reports') }}</p>
                        <h2 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                            {{ number_format($totalReports) }}
                        </h2>
                    </div>
                </div>
            </div>

            <!-- New Artisans -->
            <div
                class="bg-white dark:bg-zinc-900 p-6 rounded-3xl border border-zinc-200 dark:border-zinc-800  overflow-hidden relative group">
                <div
                    class="absolute -right-4 -top-4 size-24 bg-amber-500/10 rounded-full blur-2xl group-hover:bg-amber-500/20 transition-all">
                </div>
                <div class="relative z-10 flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between mb-4">
                        <div
                            class="size-10 rounded-xl bg-amber-100 dark:bg-amber-500/20 flex items-center justify-center text-amber-600 dark:text-amber-400">
                            <flux:icon name="sparkles" class="size-5" />
                        </div>
                    </div>
                    <div>
                        <p class="text-xs uppercase font-bold  text-zinc-400 mb-1">
                            {{ __('Artisans') }}
                        </p>
                        <h2 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                            {{ number_format($artisansCount) }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- User Growth (Line Chart) -->
            <div
                class="lg:col-span-2 bg-white dark:bg-zinc-900 p-8 rounded-3xl border border-zinc-200 dark:border-zinc-800 ">
                <h3 class="text-sm font-bold uppercase  text-zinc-900 dark:text-white mb-8">
                    {{ __('User Growth - Current Month') }}</h3>
                <div class="h-[300px]">
                    <canvas id="growthChart"></canvas>
                </div>
            </div>

            <!-- Role Distribution (Pie Chart) -->
            <div
                class="bg-white dark:bg-zinc-900 p-8 rounded-3xl border border-zinc-200 dark:border-zinc-800 ">
                <h3 class="text-sm font-bold uppercase  text-zinc-900 dark:text-white mb-8">
                    {{ __('Community Mix') }}</h3>
                <div class="h-[300px] flex items-center justify-center">
                    <canvas id="roleChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Bottom Lists Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Reports -->
            <div
                class="bg-white dark:bg-zinc-900 p-8 rounded-3xl border border-zinc-200 dark:border-zinc-800 ">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-sm font-bold uppercase  text-zinc-900 dark:text-white">
                        {{ __('Critical Reports') }}</h3>
                    <flux:button :href="route('admin.reports')" variant="ghost" size="sm" class="text-xs">
                        {{ __('Manage All Reports') }}</flux:button>
                </div>

                <div class="space-y-4">
                    @forelse($recentReports as $report)
                        <div
                            class="flex items-start gap-4 p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-100 dark:border-zinc-800">
                            <div
                                class="size-10 rounded-full bg-red-100 dark:bg-red-900/20 flex items-center justify-center text-red-600 shrink-0">
                                <flux:icon name="flag" class="size-5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <p class="text-xs font-bold text-zinc-900 dark:text-white truncate">
                                        {{ $report->reason }}
                                    </p>
                                    <span
                                        class="text-xs text-zinc-400 whitespace-nowrap">{{ $report->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-xs text-zinc-500 mb-3 truncate">
                                    {{ __('Reported by') }} <span
                                        class="font-bold text-zinc-700 dark:text-zinc-300">{{ $report->user->name }}</span>
                                    @if ($report->post)
                                        {{ __('on post of') }} <span
                                            class="font-bold text-zinc-700 dark:text-zinc-300">{{ $report->post->user->name }}</span>
                                    @endif
                                </p>
                                <div class="flex gap-2">
                                    <flux:button wire:click="resolveReport({{ $report->id }}, 'delete')"
                                        variant="danger" size="xs" class="px-3">
                                        {{ __('Remove Post') }}
                                    </flux:button>
                                    <flux:button wire:click="resolveReport({{ $report->id }}, 'dismiss')"
                                        variant="ghost" size="xs" class="px-3">
                                        {{ __('Dismiss') }}
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center">
                            <flux:icon name="check-circle"
                                class="size-12 text-zinc-200 dark:text-zinc-800 mx-auto mb-4" />
                            <p class="text-sm text-zinc-400">{{ __('No pending reports to handle.') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- System Intelligence (Top Artisans) -->
            <div
                class="bg-white dark:bg-zinc-900 p-8 rounded-3xl border border-zinc-200 dark:border-zinc-800 ">
                <h3 class="text-sm font-bold uppercase  text-zinc-900 dark:text-white mb-6">
                    {{ __('Top Performing Artisans') }}</h3>

                <div class="space-y-4">
                    @foreach ($topArtisans as $artisan)
                        <div
                            class="flex items-center gap-4 p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-100 dark:border-zinc-800 group hover:border-[var(--color-brand-purple)]/30 transition-all">
                            <div
                                class="size-12 rounded-2xl bg-white dark:bg-zinc-800 flex items-center justify-center overflow-hidden border border-zinc-200 dark:border-zinc-700">
                                @if ($artisan->profile_picture_url)
                                    <img src="{{ $artisan->profile_picture_url }}" class="size-full object-cover">
                                @else
                                    <span class="text-xs font-bold text-zinc-500">{{ $artisan->initials() }}</span>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <p class="text-sm font-bold text-zinc-900 dark:text-white truncate">
                                        {{ $artisan->name }}</p>
                                    <div class="flex items-center gap-1">
                                        <flux:icon name="star" variant="solid" class="size-3 text-yellow-400" />
                                        <span
                                            class="text-xs font-bold text-zinc-900 dark:text-white">{{ number_format($artisan->smart_rating, 1) }}</span>
                                    </div>
                                </div>
                                <p class="text-xs text-zinc-500 font-bold uppercase tracking-tighter">
                                    {{ $artisan->work ?: __('Professional') }} • {{ $artisan->posts()->count() }}
                                    {{ __('posts') }}</p>
                            </div>
                            <flux:button :href="route('artisan.profile', $artisan->username)" variant="ghost"
                                size="sm" class="size-8 p-0 rounded-xl">
                                <flux:icon name="arrow-up-right" class="size-4" />
                            </flux:button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Post Management Tab -->
    <div x-show="activeTab === 'posts'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-zinc-900 p-8 rounded-3xl border border-zinc-200 dark:border-zinc-800 ">
            <h3 class="text-sm font-bold uppercase  text-zinc-900 dark:text-white mb-6">
                {{ __('Find & Manage Posts') }}</h3>

            <div class="max-w-md">
                <flux:input wire:model.live.debounce.300ms="postSearchQuery" icon="magnifying-glass"
                    placeholder="Search content or author..." />
            </div>

            <div class="mt-8 space-y-4">
                @forelse($managedPosts as $post)
                    <div
                        class="p-6 rounded-2xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-100 dark:border-zinc-800 flex items-start justify-between gap-4">
                        <div class="flex gap-4 min-w-0">
                            <div
                                class="size-10 rounded-xl overflow-hidden shrink-0 border border-zinc-200 dark:border-zinc-700">
                                @if ($post->user->profile_picture_url)
                                    <img src="{{ $post->user->profile_picture_url }}" class="size-full object-cover">
                                @else
                                    <div
                                        class="size-full flex items-center justify-center bg-zinc-200 dark:bg-zinc-700 text-xs font-bold">
                                        {{ $post->user->initials() }}</div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-zinc-900 dark:text-white">
                                    {{ $post->user->username }}
                                    <span
                                        class="text-zinc-400 font-normal">@<span>{{ $post->user->username }}</span></span>
                                </p>
                                <p class="text-xs text-zinc-500 mt-2 line-clamp-3 leading-relaxed">
                                    {{ $post->content }}</p>
                                <p class="text-xs text-zinc-400 mt-2">
                                    {{ $post->created_at->format('M d, Y • g:i A') }}</p>
                            </div>
                        </div>
                        <flux:button wire:click="deletePost({{ $post->id }})"
                            wire:confirm="Delete this post permanently?" variant="danger" size="sm"
                            class="shrink-0">
                            {{ __('Delete') }}
                        </flux:button>
                    </div>
                @empty
                    @if (strlen($postSearchQuery) >= 3)
                        <div class="text-center py-12">
                            <flux:icon name="magnifying-glass"
                                class="size-12 text-zinc-200 dark:text-zinc-800 mx-auto mb-4" />
                            <p class="text-sm text-zinc-400">{{ __('No posts found matching') }}
                                "{{ $postSearchQuery }}"</p>
                        </div>
                    @else
                        <div
                            class="text-center py-12 border-2 border-dashed border-zinc-100 dark:border-zinc-800 rounded-3xl">
                            <p class="text-xs text-zinc-400 font-bold uppercase ">
                                {{ __('Enter at least 3 characters to search') }}</p>
                        </div>
                    @endif
                @endforelse
            </div>
        </div>
    </div>

    <!-- User Management Tab -->
    <div x-show="activeTab === 'users'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-zinc-900 p-8 rounded-3xl border border-zinc-200 dark:border-zinc-800 ">
            <h3 class="text-sm font-bold uppercase  text-zinc-900 dark:text-white mb-6">
                {{ __('Find & Manage Users') }}</h3>

            <div class="max-w-md">
                <flux:input wire:model.live.debounce.300ms="userSearchQuery" icon="magnifying-glass"
                    placeholder="Search name, username or email..." />
            </div>

            <div class="mt-8 space-y-4">
                @forelse($managedUsers as $user)
                    <div
                        class="p-6 rounded-2xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-4 min-w-0">
                            <div
                                class="size-12 rounded-2xl overflow-hidden shrink-0 border border-zinc-200 dark:border-zinc-700">
                                @if ($user->profile_picture_url)
                                    <img src="{{ $user->profile_picture_url }}" class="size-full object-cover">
                                @else
                                    <div
                                        class="size-full flex items-center justify-center bg-zinc-200 dark:bg-zinc-700 text-sm font-bold">
                                        {{ $user->initials() }}</div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                                    {{ $user->name }}
                                    <span
                                        class="px-2 py-0.5 rounded text-xs font-bold uppercase  {{ $user->role === 'artisan' ? 'bg-zinc-100 text-purple-600' : 'bg-blue-100 text-blue-600' }}">
                                        {{ $user->role }}
                                    </span>
                                </p>
                                <p class="text-xs text-zinc-500">@<span>{{ $user->username }}</span> •
                                    {{ $user->email }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <flux:button wire:click="viewUser({{ $user->id }})" variant="ghost" size="sm" class="font-bold text-xs uppercase">
                                {{ __('View more') }}
                            </flux:button>
                            @if ($user->role === 'artisan')
                                <flux:button wire:click="updateUserRole({{ $user->id }}, 'guest')"
                                    variant="outline" size="sm"
                                    class="font-bold text-xs uppercase ">
                                    {{ __('Make Guest') }}
                                </flux:button>
                            @else
                                <flux:button wire:click="updateUserRole({{ $user->id }}, 'artisan')"
                                    variant="primary" size="sm"
                                    class="font-bold text-xs uppercase ">
                                    {{ __('Make Artisan') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @empty
                    @if (strlen($userSearchQuery) >= 3)
                        <div class="text-center py-12">
                            <flux:icon name="magnifying-glass"
                                class="size-12 text-zinc-200 dark:text-zinc-800 mx-auto mb-4" />
                            <p class="text-sm text-zinc-400">{{ __('No users found matching') }}
                                "{{ $userSearchQuery }}"</p>
                        </div>
                    @else
                        <div
                            class="text-center py-12 border-2 border-dashed border-zinc-100 dark:border-zinc-800 rounded-3xl">
                            <p class="text-xs text-zinc-400 font-bold uppercase ">
                                {{ __('Enter at least 3 characters to search users') }}</p>
                        </div>
                    @endif
                @endforelse
            </div>
        </div>
    </div>


    <!-- User Detail Modal -->
    <flux:modal wire:model="showUserModal" class="max-w-xl">
        @if ($selectedUser)
            <div class="space-y-0 max-h-[75vh] overflow-y-auto -mx-1 px-1"
                 x-data="{
                     decoded: '',
                     loadingDecoded: false,
                     async fetchDecoded() {
                         const lat = '{{ $selectedUser->latitude }}';
                         const lng = '{{ $selectedUser->longitude }}';
                         if (!lat || !lng || lat === '—' || lng === '—') return;
                         this.loadingDecoded = true;
                         try {
                             const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18`, { headers: { 'Accept': 'application/json' } });
                             const data = await res.json();
                             this.decoded = data.display_name || '';
                         } catch (e) { this.decoded = ''; }
                         this.loadingDecoded = false;
                     }
                 }"
                 x-init="fetchDecoded()">
                <!-- Header -->
                <div class="flex items-start gap-4 pb-5">
                    <div class="size-14 rounded-2xl overflow-hidden shrink-0 border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-lg font-bold">
                        @if ($selectedUser->profile_picture_url)
                            <img src="{{ $selectedUser->profile_picture_url }}" class="size-full object-cover">
                        @else
                            {{ $selectedUser->initials() }}
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-zinc-900 dark:text-white truncate">{{ $selectedUser->name }}</h3>
                        <p class="text-xs text-zinc-500 break-all">{{ '@' . $selectedUser->username }} • <span class="break-all">{{ $selectedUser->email }}</span></p>
                        <span class="mt-1.5 inline-flex px-2 py-0.5 rounded text-xs font-bold uppercase {{ $selectedUser->role === 'artisan' ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600' }}">{{ $selectedUser->role }}</span>
                    </div>
                </div>

                <div class="h-px bg-zinc-100 dark:bg-zinc-800"></div>

                <!-- Account Section -->
                <div class="py-4 space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400">Account</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase text-zinc-400">Username</p>
                            <p class="text-sm text-zinc-900 dark:text-zinc-100 font-medium break-all">{{ '@' . $selectedUser->username }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase text-zinc-400">Email</p>
                            <p class="text-sm text-zinc-900 dark:text-zinc-100 break-all">{{ $selectedUser->email }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase text-zinc-400">Work</p>
                            <p class="text-sm text-zinc-900 dark:text-zinc-100 break-words">{{ $selectedUser->work ?: '—' }}</p>
                            @if ($selectedUser->work_status)
                                <p class="text-xs text-zinc-500 break-words">{{ $selectedUser->work_status }}</p>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase text-zinc-400">Phone</p>
                            <p class="text-sm text-zinc-900 dark:text-zinc-100 break-all">{{ $selectedUser->phone_number ?: '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="h-px bg-zinc-100 dark:bg-zinc-800"></div>

                <!-- Location Section — Two Locations -->
                <div class="py-4 space-y-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400">Location</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="min-w-0 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-3 border border-zinc-100 dark:border-zinc-800">
                            <p class="text-xs font-bold uppercase text-zinc-400 mb-1">Stored Address</p>
                            <p class="text-sm text-zinc-900 dark:text-zinc-100 break-words leading-relaxed">{{ $selectedUser->address ?: '—' }}</p>
                            @if ($selectedUser->country_code)
                                <p class="text-xs text-zinc-500 mt-1 break-all">{{ $selectedUser->country_code }}</p>
                            @endif
                        </div>
                        <div class="min-w-0 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-3 border border-zinc-100 dark:border-zinc-800">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <p class="text-xs font-bold uppercase text-zinc-400">Current Location (decoded)</p>
                                <button @click="fetchDecoded()" x-bind:disabled="loadingDecoded" class="size-6 flex items-center justify-center rounded-full hover:bg-white dark:hover:bg-zinc-700 border border-zinc-200 dark:border-zinc-700 text-zinc-500 hover:text-purple-600 dark:text-zinc-400 dark:hover:text-purple-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed shrink-0" title="Refetch location">
                                    <span x-bind:class="loadingDecoded ? 'animate-spin' : ''" class="flex">
                                        <flux:icon name="arrow-path" class="size-3.5" />
                                    </span>
                                </button>
                            </div>
                            <template x-if="loadingDecoded">
                                <p class="text-xs text-zinc-500 flex items-center gap-1.5"><span class="size-3 border-2 border-zinc-300 border-t-purple-600 rounded-full animate-spin"></span> Resolving...</p>
                            </template>
                            <template x-if="!loadingDecoded">
                                <p class="text-sm text-zinc-900 dark:text-zinc-100 break-words leading-relaxed" x-text="decoded || '—'"></p>
                            </template>
                            <p class="text-xs text-zinc-400 mt-1 break-words" x-show="decoded" x-text="'via coordinates'"></p>
                        </div>
                    </div>
                    <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-3 border border-zinc-100 dark:border-zinc-800">
                        <p class="text-xs font-bold uppercase text-zinc-400 mb-1">Coordinates</p>
                        <p class="text-zinc-900 dark:text-zinc-100 font-mono text-xs break-all">{{ $selectedUser->latitude ?: '—' }}, {{ $selectedUser->longitude ?: '—' }}</p>
                        @if ($selectedUser->latitude && $selectedUser->longitude)
                            <a href="https://www.openstreetmap.org/?mlat={{ $selectedUser->latitude }}&mlon={{ $selectedUser->longitude }}#map=15/{{ $selectedUser->latitude }}/{{ $selectedUser->longitude }}" target="_blank" class="inline-flex items-center gap-1 text-xs text-purple-600 hover:underline mt-1 break-all">
                                <span>View on map</span>
                                <flux:icon name="arrow-top-right-on-square" class="size-3" />
                            </a>
                        @endif
                    </div>
                </div>

                <div class="h-px bg-zinc-100 dark:bg-zinc-800"></div>

                <!-- Notifications Section -->
                <div class="py-4 space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400">Notifications</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase text-zinc-400">Allowed Notification</p>
                            @php $hasPush = $selectedUser->pushSubscriptions()->exists(); @endphp
                            <span class="mt-1 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold break-all {{ $hasPush ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' }}">
                                <span class="size-2 rounded-full shrink-0 {{ $hasPush ? 'bg-green-500' : 'bg-zinc-400' }}"></span>
                                <span class="truncate">{{ $hasPush ? 'Yes — Granted' : 'No / Not yet' }}</span>
                            </span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase text-zinc-400">Subscribed</p>
                            @php $pushCount = $selectedUser->pushSubscriptions()->count(); @endphp
                            <span class="mt-1 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold {{ $pushCount > 0 ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                <span class="size-2 rounded-full shrink-0 {{ $pushCount > 0 ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                                <span class="truncate">{{ $pushCount > 0 ? $pushCount . ' device' . ($pushCount > 1 ? 's' : '') : 'Not subscribed' }}</span>
                            </span>
                            @if ($pushCount > 0)
                                <p class="text-xs text-zinc-500 mt-1 break-all">{{ $pushCount }} active endpoint(s)</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="h-px bg-zinc-100 dark:bg-zinc-800"></div>

                <!-- System Section -->
                <div class="py-4 grid grid-cols-2 gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase text-zinc-400">Joined</p>
                        <p class="text-zinc-900 dark:text-zinc-100 text-xs break-all">{{ $selectedUser->created_at?->format('M d, Y g:i A') }}</p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase text-zinc-400">Status</p>
                        <p class="text-xs font-bold break-words {{ $selectedUser->isBanned() ? 'text-red-600' : 'text-green-600' }}">{{ $selectedUser->isBanned() ? 'Banned until ' . $selectedUser->banned_until?->format('M d, Y') : ($selectedUser->status ?: 'active') }}</p>
                    </div>
                </div>

                @if ($selectedUser->bio)
                    <div class="h-px bg-zinc-100 dark:bg-zinc-800"></div>
                    <div class="py-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-2">Bio</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed break-words whitespace-pre-wrap">{{ $selectedUser->bio }}</p>
                    </div>
                @endif

                <div class="flex justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button wire:click="closeUserModal" variant="ghost">{{ __('Close') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Chart Scripts -->
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js" data-navigate-once></script>
        <script>
            function initAdminCharts() {
                const growthCtx = document.getElementById('growthChart');
                const roleCtx = document.getElementById('roleChart');

                if (!growthCtx || !roleCtx) return;

                // Destroy existing instances to avoid "Canvas is already in use" error
                const existingGrowth = Chart.getChart(growthCtx);
                if (existingGrowth) existingGrowth.destroy();

                const existingRole = Chart.getChart(roleCtx);
                if (existingRole) existingRole.destroy();

                const colorPurple = '#a855f7';

                new Chart(growthCtx, {
                    type: 'line',
                    data: {
                        labels: @js($userGrowthChartData['labels'] ?? []),
                        datasets: [{
                            label: 'New Users',
                            data: @js($userGrowthChartData['data'] ?? []),
                            borderColor: colorPurple,
                            backgroundColor: 'rgba(168, 85, 247, 0.1)',
                            borderWidth: 4,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 0,
                            pointHitRadius: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 10
                                    },
                                    color: '#71717a'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 10
                                    },
                                    color: '#71717a'
                                }
                            }
                        }
                    }
                });

                new Chart(roleCtx, {
                    type: 'doughnut',
                    data: {
                        labels: @js($roleDistributionData['labels'] ?? []),
                        datasets: [{
                            data: @js($roleDistributionData['data'] ?? []),
                            backgroundColor: [colorPurple, '#3b82f6'],
                            borderWidth: 0,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    font: {
                                        size: 10,
                                        weight: 'bold'
                                    },
                                    usePointStyle: true,
                                    color: '#71717a'
                                }
                            }
                        },
                        cutout: '70%'
                    }
                });
            }

            // Run on initial load and every navigation
            document.addEventListener('livewire:navigated', initAdminCharts);

            // Initial call for the very first load
            document.addEventListener('DOMContentLoaded', initAdminCharts);

            // Also support Livewire's own initialization event
            document.addEventListener('livewire:initialized', initAdminCharts);
        </script>
    @endpush
</div>
