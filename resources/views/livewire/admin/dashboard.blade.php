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
    public $showAdminDeletePostModal = false;
    public $adminDeletePostId = null;
    public $adminDeletePostReason = '';
    public $bannedSearchQuery = '';
    // Filters for Control Center
    public $filterYear = '';
    public $filterMonth = '';
    public $availableYears = [];

    public function mount()
    {
        $this->availableYears = $this->getAvailableYears();
        $this->loadStats();
        $this->loadChartData();
        $this->loadTopLists();
    }

    protected function getAvailableYears(): array
    {
        try {
            $years = User::selectRaw('YEAR(created_at) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->filter()
                ->map(fn($y) => (int) $y)
                ->toArray();
        } catch (\Throwable $e) {
            $years = [];
        }
        $currentYear = (int) Carbon::now()->year;
        if (!in_array($currentYear, $years, true)) {
            $years[] = $currentYear;
        }
        rsort($years);
        // Ensure at least last 5 years available
        for ($i = 1; $i <= 4; $i++) {
            $y = $currentYear - $i;
            if (!in_array($y, $years, true)) {
                $years[] = $y;
            }
        }
        rsort($years);
        return array_values(array_unique($years));
    }

    public function updated($property, $value)
    {
        if (in_array($property, ['filterYear', 'filterMonth'])) {
            $this->loadStats();
            $this->loadChartData();
            $this->loadTopLists();
            $this->dispatch('admin-charts-updated');
        }
    }

    public function clearFilter()
    {
        $this->filterYear = '';
        $this->filterMonth = '';
        Cache::forget('admin:dashboard:stats');
        $this->loadStats();
        $this->loadChartData();
        $this->loadTopLists();
        $this->dispatch('admin-charts-updated');
        $this->dispatch('toast', type: 'success', title: 'Filter Cleared', message: 'Showing all-time data.');
    }

    public function refreshDashboard()
    {
        Cache::forget('admin:dashboard:stats');
        // Also forget filtered caches
        foreach ($this->availableYears as $y) {
            Cache::forget("admin:dashboard:stats:{$y}:");
            for ($m = 1; $m <= 12; $m++) {
                Cache::forget("admin:dashboard:stats:{$y}:{$m}");
            }
        }
        Cache::forget('admin:dashboard:stats::');
        $this->loadStats();
        $this->loadChartData();
        $this->loadTopLists();
        $this->dispatch('admin-charts-updated');
        $this->dispatch('toast', type: 'success', title: 'Refreshed', message: 'Dashboard data refreshed.');
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

        $bannedUsers = User::whereNotNull('banned_until')->where('banned_until', '>', now())->latest('banned_until');
        if (strlen($this->bannedSearchQuery) >= 2) {
            $bannedUsers->where(function($q){
                $q->where('name','like','%'.$this->bannedSearchQuery.'%')->orWhere('username','like','%'.$this->bannedSearchQuery.'%')->orWhere('email','like','%'.$this->bannedSearchQuery.'%');
            });
        }
        $bannedUsers = $bannedUsers->limit(20)->get();

        return [
            'managedPosts' => $posts,
            'managedUsers' => $managedUsers,
            'bannedUsers' => $bannedUsers,
        ];
    }

    protected function hasFilter(): bool
    {
        return $this->filterYear !== '' || $this->filterMonth !== '';
    }

    protected function getFilterLabel(): string
    {
        if ($this->filterYear !== '' && $this->filterMonth !== '') {
            return Carbon::create((int) $this->filterYear, (int) $this->filterMonth, 1)->format('F Y');
        }
        if ($this->filterYear !== '') {
            return (string) $this->filterYear;
        }
        if ($this->filterMonth !== '' && $this->filterYear === '') {
            // Month without year not typical; show month name with current year hint
            return Carbon::create((int) Carbon::now()->year, (int) $this->filterMonth, 1)->format('F');
        }
        return 'All time';
    }

    public function loadStats()
    {
        $year = $this->filterYear !== '' ? (int) $this->filterYear : null;
        $month = $this->filterMonth !== '' ? (int) $this->filterMonth : null;
        $cacheKey = "admin:dashboard:stats:{$year}:{$month}";

        // No cache when filtering to reflect live data immediately
        $useCache = !$this->hasFilter();
        $compute = function () use ($year, $month) {
            $userQuery = User::query();
            $postQuery = Post::query();
            $reportQuery = Report::where('status', 'pending');

            if ($year) {
                $userQuery->whereYear('created_at', $year);
                $postQuery->whereYear('created_at', $year);
                $reportQuery->whereYear('created_at', $year);
            }
            if ($month) {
                // If month is set without year, filter by month of current year for stats
                $filterYearForMonth = $year ?? (int) Carbon::now()->year;
                $userQuery->whereYear('created_at', $filterYearForMonth)->whereMonth('created_at', $month);
                $postQuery->whereYear('created_at', $filterYearForMonth)->whereMonth('created_at', $month);
                $reportQuery->whereYear('created_at', $filterYearForMonth)->whereMonth('created_at', $month);
            }

            $totalUsers = $userQuery->count();
            $totalPosts = $postQuery->count();
            $totalReports = $reportQuery->count();

            // Growth: compare selected month vs previous month (or current month vs last month if no filter)
            $refMonth = $month ? Carbon::create($year ?? (int) Carbon::now()->year, $month, 1) : Carbon::now();
            $currentStart = $refMonth->copy()->startOfMonth();
            $currentEnd = $refMonth->copy()->endOfMonth();
            $prevStart = $refMonth->copy()->subMonth()->startOfMonth();
            $prevEnd = $refMonth->copy()->subMonth()->endOfMonth();

            $currentCount = User::whereBetween('created_at', [$currentStart, $currentEnd]);
            $prevCount = User::whereBetween('created_at', [$prevStart, $prevEnd]);
            if ($year) {
                // When a year filter is active without month, growth = this year vs last year (Jan-Dec)
                if (!$month) {
                    $currentStart = Carbon::create((int) $year, 1, 1)->startOfYear();
                    $currentEnd = Carbon::create((int) $year, 12, 31)->endOfYear();
                    $prevStart = Carbon::create((int) $year - 1, 1, 1)->startOfYear();
                    $prevEnd = Carbon::create((int) $year - 1, 12, 31)->endOfYear();
                    $currentCount = User::whereBetween('created_at', [$currentStart, $currentEnd]);
                    $prevCount = User::whereBetween('created_at', [$prevStart, $prevEnd]);
                }
            }
            $newUsersThisPeriod = $currentCount->count();
            $prevPeriod = $prevCount->count();

            $growthPercentage = 0;
            if ($prevPeriod > 0) {
                $growthPercentage = (($newUsersThisPeriod - $prevPeriod) / $prevPeriod) * 100;
            } elseif ($newUsersThisPeriod > 0) {
                $growthPercentage = 100;
            }

            $artisansCount = User::where('role', 'artisan');
            $guestsCount = User::where('role', 'guest');
            if ($year) {
                $artisansCount->whereYear('created_at', $year);
                $guestsCount->whereYear('created_at', $year);
            }
            if ($month) {
                $filterYearForMonth = $year ?? (int) Carbon::now()->year;
                $artisansCount->whereYear('created_at', $filterYearForMonth)->whereMonth('created_at', $month);
                $guestsCount->whereYear('created_at', $filterYearForMonth)->whereMonth('created_at', $month);
            }

            return [
                'totalUsers' => $totalUsers,
                'totalPosts' => $totalPosts,
                'totalReports' => $totalReports,
                'newUsersThisMonth' => $newUsersThisPeriod,
                'growthPercentage' => $growthPercentage,
                'artisansCount' => $artisansCount->count(),
                'guestsCount' => $guestsCount->count(),
            ];
        };

        $stats = $useCache ? Cache::remember($cacheKey, 300, $compute) : $compute();

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
        $this->userGrowthChartData = ['labels' => [], 'data' => []];
        $year = $this->filterYear !== '' ? (int) $this->filterYear : null;
        $month = $this->filterMonth !== '' ? (int) $this->filterMonth : null;

        if ($year && !$month) {
            // Yearly: 12 months
            for ($m = 1; $m <= 12; $m++) {
                $this->userGrowthChartData['labels'][] = Carbon::create($year, $m, 1)->format('M');
                $this->userGrowthChartData['data'][] = User::whereYear('created_at', $year)->whereMonth('created_at', $m)->count();
            }
        } else {
            // Monthly days (selected month or current month)
            $refYear = $year ?? (int) Carbon::now()->year;
            $refMonth = $month ?? (int) Carbon::now()->month;
            $daysInMonth = Carbon::create($refYear, $refMonth, 1)->daysInMonth;
            $dailyGrowth = User::select(DB::raw('DAY(created_at) as day'), DB::raw('count(*) as count'))
                ->whereYear('created_at', $refYear)
                ->whereMonth('created_at', $refMonth)
                ->groupBy('day')
                ->pluck('count', 'day')
                ->toArray();
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $this->userGrowthChartData['labels'][] = $i;
                $this->userGrowthChartData['data'][] = $dailyGrowth[$i] ?? 0;
            }
        }

        $this->roleDistributionData = [
            'labels' => ['Artisans', 'Guests'],
            'data' => [$this->artisansCount, $this->guestsCount],
        ];
    }

    public function loadTopLists()
    {
        $year = $this->filterYear !== '' ? (int) $this->filterYear : null;
        $month = $this->filterMonth !== '' ? (int) $this->filterMonth : null;

        $artisanQuery = User::where('role', 'artisan');
        if ($year) {
            $artisanQuery->whereYear('created_at', $year);
        }
        if ($month) {
            $filterYearForMonth = $year ?? (int) Carbon::now()->year;
            $artisanQuery->whereYear('created_at', $filterYearForMonth)->whereMonth('created_at', $month);
        }
        $this->topArtisans = $artisanQuery->orderBy('smart_rating', 'desc')->limit(5)->get();

        $reportQuery = Report::with(['user', 'post.user'])->where('status', 'pending');
        if ($year) {
            $reportQuery->whereYear('created_at', $year);
        }
        if ($month) {
            $filterYearForMonth = $year ?? (int) Carbon::now()->year;
            $reportQuery->whereYear('created_at', $filterYearForMonth)->whereMonth('created_at', $month);
        }
        $this->recentReports = $reportQuery->latest()->limit(5)->get();
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

    public function openAdminDeletePostModal($postId)
    {
        $this->adminDeletePostId = (int)$postId;
        $this->adminDeletePostReason = '';
        $this->showAdminDeletePostModal = true;
    }
    public function adminDeletePost()
    {
        $this->validate(['adminDeletePostReason'=>'required|string|min:10|max:500']);
        $post = Post::with('user')->findOrFail($this->adminDeletePostId);
        $owner=$post->user; $excerpt=\Illuminate\Support\Str::limit($post->content??'',500); $reason=$this->adminDeletePostReason;
        $post->delete();
        try { if($owner && $owner->email) \Illuminate\Support\Facades\Mail::to($owner->email)->send(new \App\Mail\PostDeletedMail($owner,$excerpt,$reason)); } catch(\Throwable $e){ \Log::error('PostDeletedMail failed: '.$e->getMessage()); }
        $this->showAdminDeletePostModal=false; $this->adminDeletePostId=null;
        $this->loadStats(); $this->loadTopLists();
        $this->dispatch('toast', type: 'success', title: 'Post Deleted', message: 'Post removed and owner notified.');
    }
    public function deletePost($postId)
    {
        // backward-compat alias still usable but now via modal validation path
        $this->adminDeletePostId=(int)$postId; $this->adminDeletePostReason='Removed by admin (no reason provided — please use the modal).';
        $post=Post::with('user')->findOrFail($postId);
        $owner=$post->user; $excerpt=\Illuminate\Support\Str::limit($post->content??'',500);
        $post->delete();
        try { if($owner && $owner->email) \Illuminate\Support\Facades\Mail::to($owner->email)->send(new \App\Mail\PostDeletedMail($owner,$excerpt,$this->adminDeletePostReason)); } catch(\Throwable $e){ \Log::error('PostDeletedMail failed: '.$e->getMessage()); }
        $this->loadStats(); $this->loadTopLists();
        $this->dispatch('toast', type: 'success', title: 'Post Deleted', message: 'Post removed and owner notified.');
    }
    public function unbanUser($userId)
    {
        $user=User::findOrFail($userId);
        $user->update(['banned_until'=>null,'banned_reason'=>null,'banned_by'=>null,'banned_at'=>null]);
        $this->dispatch('toast', type:'success', title:'User Unbanned', message: $user->name.' has been unbanned.');
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
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 italic uppercase tracking-tighter">
                {{ __('Control Center') }}</h1>
            <p class="text-sm text-zinc-500">{{ __('Overview of your social ecosystem') }}</p>
            @if ($this->hasFilter())
                <p class="mt-1 inline-flex items-center gap-1.5 text-xs font-semibold text-purple-600 dark:text-purple-400">
                    <span class="size-1.5 rounded-full bg-purple-500"></span>
                    {{ __('Filtered:') }} {{ $this->getFilterLabel() }}
                </p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="hidden sm:flex items-center gap-2 text-zinc-400">
                <span class="size-2 bg-green-500 rounded-full animate-pulse"></span>
                <span class="text-xs font-bold uppercase">{{ __('Live System Data') }}</span>
            </div>
            <!-- Refresh: text button on desktop, icon-only on mobile -->
            <button wire:click="refreshDashboard" wire:loading.attr="disabled"
                class="hidden sm:inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 text-xs font-bold uppercase tracking-wide hover:bg-zinc-800 dark:hover:bg-zinc-100 transition-colors disabled:opacity-50">
                <flux:icon name="arrow-path" class="size-4" wire:loading.remove wire:target="refreshDashboard" />
                <span wire:loading wire:target="refreshDashboard" class="size-4 border-2 border-white/30 dark:border-zinc-900/20 border-t-white dark:border-t-zinc-900 rounded-full animate-spin"></span>
                <span wire:loading.remove wire:target="refreshDashboard">{{ __('Refresh') }}</span>
                <span wire:loading wire:target="refreshDashboard">{{ __('Refreshing...') }}</span>
            </button>
            <button wire:click="refreshDashboard" wire:loading.attr="disabled" aria-label="{{ __('Refresh') }}"
                class="sm:hidden size-10 rounded-xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 flex items-center justify-center hover:bg-zinc-800 dark:hover:bg-zinc-100 transition-colors disabled:opacity-50 shrink-0">
                <flux:icon name="arrow-path" class="size-5" wire:loading.remove wire:target="refreshDashboard" />
                <span wire:loading wire:target="refreshDashboard" class="size-5 border-2 border-white/30 dark:border-zinc-900/20 border-t-white dark:border-t-zinc-900 rounded-full animate-spin"></span>
            </button>
        </div>
    </div>

    <!-- Filters: Date / Year for charts, cards, Community Mix -->
    <div class="flex flex-col sm:flex-row gap-3 bg-white dark:bg-zinc-900 p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800">
        <div class="flex-1 min-w-0">
            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1.5">{{ __('Year') }}</label>
            <select wire:model.live="filterYear" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm font-medium text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none">
                <option value="">{{ __('All years') }}</option>
                @foreach ($availableYears as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-0">
            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1.5">{{ __('Month') }}</label>
            <select wire:model.live="filterMonth" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm font-medium text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none">
                <option value="">{{ $filterYear ? __('All months') : __('All months (current year)') }}</option>
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}">{{ Carbon::create(2000, $m, 1)->format('F') }}</option>
                @endfor
            </select>
            <p class="mt-1 text-xs text-zinc-500">{{ $filterYear ? __('Filters charts, cards & Community Mix') : __('Month uses current year when no year selected') }}</p>
        </div>
        <div class="flex items-end gap-2">
            <flux:button wire:click="clearFilter" variant="ghost" size="sm" class="h-[42px] whitespace-nowrap">{{ __('Clear filter') }}</flux:button>
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
        <button @click="activeTab = 'banned'"
            :class="activeTab === 'banned' ? 'bg-white dark:bg-zinc-700  text-zinc-900 dark:text-white' :
                'text-zinc-500 hover:text-zinc-700'"
            class="px-6 py-2 rounded-xl text-xs font-bold uppercase  transition-all">
            {{ __('Banned') }} <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] {{ ($bannedUsers->count() ?? 0) > 0 ? 'bg-red-500 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-400' }}">{{ $bannedUsers->count() ?? 0 }}</span>
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
                    @if ($filterYear && !$filterMonth)
                        {{ __('User Growth') }} — {{ $filterYear }}
                    @elseif ($filterMonth)
                        {{ __('User Growth') }} — {{ $filterYear ? Carbon::create((int)$filterYear, (int)$filterMonth, 1)->format('F Y') : Carbon::create((int)Carbon::now()->year, (int)$filterMonth, 1)->format('F Y') }}
                    @else
                        {{ $filterYear ? __('User Growth') . ' — ' . $filterYear : __('User Growth - Current Month') }}
                    @endif
                </h3>
                <div class="h-[300px]">
                    <canvas id="growthChart"></canvas>
                </div>
            </div>

            <!-- Role Distribution (Pie Chart) -->
            <div
                class="bg-white dark:bg-zinc-900 p-8 rounded-3xl border border-zinc-200 dark:border-zinc-800 ">
                <h3 class="text-sm font-bold uppercase  text-zinc-900 dark:text-white mb-8">
                    {{ __('Community Mix') }}
                    @if ($this->hasFilter())
                        <span class="ml-2 text-xs font-normal normal-case text-zinc-500">({{ $this->getFilterLabel() }})</span>
                    @endif
                </h3>
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
                        <flux:button wire:click="openAdminDeletePostModal({{ $post->id }})" variant="danger" size="sm" class="shrink-0">{{ __('Delete') }}</flux:button>
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
                            @if ($user->isBanned())
                                <flux:button wire:click="unbanUser({{ $user->id }})" variant="danger" size="sm" class="font-bold text-xs uppercase">{{ __('Unban') }}</flux:button>
                            @else
                                <button x-data @click="$dispatch('open-ban-modal', {userId: {{ $user->id }}, userName: '{{ addslashes($user->name) }}'})" class="px-3 py-1.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold uppercase">{{ __('Ban') }}</button>
                            @endif
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

    <!-- Banned Users Tab -->
    <div x-show="activeTab === 'banned'" x-cloak class="space-y-6">
        <div class="bg-white dark:bg-zinc-900 p-8 rounded-3xl border border-zinc-200 dark:border-zinc-800">
            <h3 class="text-sm font-bold uppercase text-zinc-900 dark:text-white mb-2">{{ __('Banned Users') }}</h3>
            <p class="text-xs text-zinc-500 mb-6">{{ __('Search and unban users. Ban via the Users tab or directly on the feed.') }}</p>
            <div class="max-w-md">
                <flux:input wire:model.live.debounce.300ms="bannedSearchQuery" icon="magnifying-glass" placeholder="{{ __('Search banned by name, username or email…') }}" />
            </div>
            <div class="mt-8 space-y-4">
                @forelse($bannedUsers as $bUser)
                    <div class="p-5 rounded-2xl bg-red-50/60 dark:bg-red-900/10 border border-red-200 dark:border-red-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="size-10 rounded-xl overflow-hidden shrink-0 border border-zinc-200 dark:border-zinc-700">
                                @if($bUser->profile_picture_url)
                                    <img src="{{ $bUser->profile_picture_url }}" class="size-full object-cover">
                                @else
                                    <div class="size-full flex items-center justify-center bg-zinc-200 dark:bg-zinc-700 text-xs font-bold">{{ $bUser->initials() }}</div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-zinc-900 dark:text-white truncate">{{ $bUser->name }} <span class="text-zinc-400 font-normal">@<span>{{ $bUser->username }}</span></span></p>
                                <p class="text-xs text-zinc-500 truncate">{{ $bUser->email }}</p>
                                <p class="text-xs text-red-600 dark:text-red-400 mt-0.5">
                                    {{ __('Banned until') }} <span class="font-bold">{{ $bUser->banned_until?->format('M d, Y') }}</span>
                                    @if($bUser->banned_reason) • <span class="italic break-words">{{ \Illuminate\Support\Str::limit($bUser->banned_reason, 80) }}</span> @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button wire:click="unbanUser({{ $bUser->id }})" wire:confirm="{{ __('Unban this user?') }}" class="px-3 py-1.5 rounded-xl bg-green-600 hover:bg-green-700 text-white text-xs font-bold">{{ __('Unban') }}</button>
                            <button x-data @click="$dispatch('open-ban-modal', {userId: {{ $bUser->id }}, userName: '{{ addslashes($bUser->name) }}'})" class="px-3 py-1.5 rounded-xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 text-xs font-bold">{{ __('Extend') }}</button>
                            <flux:button wire:click="viewUser({{ $bUser->id }})" variant="ghost" size="sm" class="text-xs">{{ __('View') }}</flux:button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 border-2 border-dashed border-zinc-100 dark:border-zinc-800 rounded-3xl">
                        <flux:icon name="shield-check" class="size-12 text-zinc-200 dark:text-zinc-800 mx-auto mb-4" />
                        <p class="text-xs text-zinc-400 font-bold uppercase">{{ __('No banned users') }}</p>
                        @if(strlen($bannedSearchQuery) >= 2) <p class="text-xs text-zinc-400 mt-1">{{ __('No matches for') }} "{{ $bannedSearchQuery }}"</p> @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Admin Delete Post Modal -->
    <flux:modal wire:model="showAdminDeletePostModal" class="sm:max-w-lg">
        <div class="space-y-6">
            <div><flux:heading size="lg">{{ __('Delete Post') }}</flux:heading><flux:subheading>{{ __('Remove this post and notify the owner by email.') }}</flux:subheading></div>
            <div><flux:label>{{ __('Reason') }} *</flux:label><flux:textarea wire:model="adminDeletePostReason" rows="3" placeholder="{{ __('Why — owner will receive by email') }}" />@error('adminDeletePostReason') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror</div>
            <div class="flex gap-2 justify-end"><flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close><flux:button variant="danger" wire:click="adminDeletePost">{{ __('Delete & Notify') }}</flux:button></div>
        </div>
    </flux:modal>

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
            // Re-init charts when Livewire updates filtered data
            document.addEventListener('livewire:initialized', () => {
                if (window.Livewire) {
                    Livewire.on('admin-charts-updated', () => {
                        setTimeout(() => initAdminCharts(), 80);
                    });
                }
            });
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
