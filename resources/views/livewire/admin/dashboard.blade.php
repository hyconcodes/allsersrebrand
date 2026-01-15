<?php

use App\Models\User;
use App\Models\Post;
use App\Models\Report;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;
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

        return [
            'managedPosts' => $posts,
        ];
    }

    public function loadStats()
    {
        $this->totalUsers = User::count();
        $this->totalPosts = Post::count();
        $this->totalReports = Report::where('status', 'pending')->count();

        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        $this->newUsersThisMonth = User::where('created_at', '>=', $currentMonth)->count();
        $lastMonthUsers = User::whereBetween('created_at', [$lastMonth, $endOfLastMonth])->count();

        if ($lastMonthUsers > 0) {
            $this->growthPercentage = (($this->newUsersThisMonth - $lastMonthUsers) / $lastMonthUsers) * 100;
        } else {
            $this->growthPercentage = $this->newUsersThisMonth > 0 ? 100 : 0;
        }

        $this->artisansCount = User::where('role', 'artisan')->count();
        $this->guestsCount = User::where('role', 'guest')->count();
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
                'status' => 'resolved',
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
            <h1 class="text-2xl font-black text-zinc-900 dark:text-zinc-100 italic uppercase tracking-tighter">
                {{ __('Control Center') }}</h1>
            <p class="text-sm text-zinc-500">{{ __('Overview of your social ecosystem') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="size-2 bg-green-500 rounded-full animate-pulse"></span>
            <span class="text-xs font-bold text-zinc-400 uppercase tracking-widest">{{ __('Live System Data') }}</span>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="flex overflow-x-auto gap-2 p-1 bg-zinc-100 dark:bg-zinc-800 rounded-2xl w-fit">
        <button @click="activeTab = 'overview'"
            :class="activeTab === 'overview' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white' :
                'text-zinc-500 hover:text-zinc-700'"
            class="px-6 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all">
            {{ __('Overview') }}
        </button>
        <button @click="activeTab = 'posts'"
            :class="activeTab === 'posts' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white' :
                'text-zinc-500 hover:text-zinc-700'"
            class="px-6 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all">
            {{ __('Posts Management') }}
        </button>
    </div>

    <div x-show="activeTab === 'overview'" class="space-y-8">
        <!-- Quick Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Total Users -->
            <div
                class="bg-white dark:bg-zinc-900 p-6 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden relative group">
                <div
                    class="absolute -right-4 -top-4 size-24 bg-purple-500/10 rounded-full blur-2xl group-hover:bg-purple-500/20 transition-all">
                </div>
                <div class="relative z-10 flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between mb-4">
                        <div
                            class="size-10 rounded-xl bg-purple-100 dark:bg-purple-500/20 flex items-center justify-center text-purple-600 dark:text-purple-400">
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
                        <p class="text-[10px] uppercase font-bold tracking-widest text-zinc-400 mb-1">
                            {{ __('Total Users') }}</p>
                        <h2 class="text-3xl font-black text-zinc-900 dark:text-zinc-100">
                            {{ number_format($totalUsers) }}
                        </h2>
                    </div>
                </div>
            </div>

            <!-- Total Posts -->
            <div
                class="bg-white dark:bg-zinc-900 p-6 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden relative group">
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
                        <p class="text-[10px] uppercase font-bold tracking-widest text-zinc-400 mb-1">
                            {{ __('Total Posts') }}</p>
                        <h2 class="text-3xl font-black text-zinc-900 dark:text-zinc-100">
                            {{ number_format($totalPosts) }}
                        </h2>
                    </div>
                </div>
            </div>

            <!-- Pending Reports -->
            <div
                class="bg-white dark:bg-zinc-900 p-6 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden relative group">
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
                        <p class="text-[10px] uppercase font-bold tracking-widest text-zinc-400 mb-1">
                            {{ __('Pending Reports') }}</p>
                        <h2 class="text-3xl font-black text-zinc-900 dark:text-zinc-100">
                            {{ number_format($totalReports) }}
                        </h2>
                    </div>
                </div>
            </div>

            <!-- New Artisans -->
            <div
                class="bg-white dark:bg-zinc-900 p-6 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden relative group">
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
                        <p class="text-[10px] uppercase font-bold tracking-widest text-zinc-400 mb-1">
                            {{ __('Artisans') }}
                        </p>
                        <h2 class="text-3xl font-black text-zinc-900 dark:text-zinc-100">
                            {{ number_format($artisansCount) }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- User Growth (Line Chart) -->
            <div
                class="lg:col-span-2 bg-white dark:bg-zinc-900 p-8 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-sm">
                <h3 class="text-sm font-black uppercase tracking-widest text-zinc-900 dark:text-white mb-8">
                    {{ __('User Growth - Current Month') }}</h3>
                <div class="h-[300px]">
                    <canvas id="growthChart"></canvas>
                </div>
            </div>

            <!-- Role Distribution (Pie Chart) -->
            <div
                class="bg-white dark:bg-zinc-900 p-8 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-sm">
                <h3 class="text-sm font-black uppercase tracking-widest text-zinc-900 dark:text-white mb-8">
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
                class="bg-white dark:bg-zinc-900 p-8 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-sm">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-sm font-black uppercase tracking-widest text-zinc-900 dark:text-white">
                        {{ __('Critical Reports') }}</h3>
                    <flux:button :href="route('admin.reports')" variant="ghost" size="sm" class="text-[10px]">
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
                                        class="text-[8px] text-zinc-400 whitespace-nowrap">{{ $report->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-[10px] text-zinc-500 mb-3 truncate">
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
                class="bg-white dark:bg-zinc-900 p-8 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-sm">
                <h3 class="text-sm font-black uppercase tracking-widest text-zinc-900 dark:text-white mb-6">
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
                                    <span class="text-xs font-black text-zinc-500">{{ $artisan->initials() }}</span>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <p class="text-sm font-bold text-zinc-900 dark:text-white truncate">
                                        {{ $artisan->name }}</p>
                                    <div class="flex items-center gap-1">
                                        <flux:icon name="star" variant="solid" class="size-3 text-yellow-400" />
                                        <span
                                            class="text-xs font-black text-zinc-900 dark:text-white">{{ number_format($artisan->smart_rating, 1) }}</span>
                                    </div>
                                </div>
                                <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-tighter">
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
        <div class="bg-white dark:bg-zinc-900 p-8 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-sm">
            <h3 class="text-sm font-black uppercase tracking-widest text-zinc-900 dark:text-white mb-6">
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
                                <p class="text-xs font-black text-zinc-900 dark:text-white">{{ $post->user->name }}
                                    <span
                                        class="text-zinc-400 font-normal">@<span>{{ $post->user->username }}</span></span>
                                </p>
                                <p class="text-xs text-zinc-500 mt-2 line-clamp-3 leading-relaxed">
                                    {{ $post->content }}</p>
                                <p class="text-[10px] text-zinc-400 mt-2">
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
                            <p class="text-xs text-zinc-400 font-bold uppercase tracking-widest">
                                {{ __('Enter at least 3 characters to search') }}</p>
                        </div>
                    @endif
                @endforelse
            </div>
        </div>
    </div>


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
