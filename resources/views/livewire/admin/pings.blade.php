<?php

use App\Models\Engagement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $scope = '24h';

    public $filterYear = '';

    public $filterMonth = '';

    public $filterDay = '';

    public $fromHour = '';

    public $toHour = '';

    public string $search = '';

    /** @var array<int, array{label: string, data: int[]}> */
    public array $pingChartData = ['labels' => [], 'data' => []];

    /** @var array{total: int, pending: int, high: int, pingers: int} */
    public array $stats = ['total' => 0, 'pending' => 0, 'high' => 0, 'pingers' => 0];

    /** @var array<int, int> */
    public array $availableYears = [];

    public function mount(): void
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        $this->availableYears = $this->distinctYears();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['filterYear', 'filterMonth', 'filterDay'], true) && filled($this->$property)) {
            $this->scope = 'custom';
        }

        if (in_array($property, ['scope', 'filterYear', 'filterMonth', 'filterDay', 'fromHour', 'toHour', 'search'], true)) {
            $this->resetPage();
            $this->dispatch('ping-charts-updated');
        }
    }

    public function setScope(string $scope): void
    {
        if (! in_array($scope, ['24h', '7d', '30d', 'all'], true)) {
            return;
        }

        $this->scope = $scope;
        $this->filterYear = '';
        $this->filterMonth = '';
        $this->filterDay = '';
        $this->resetPage();
        $this->dispatch('ping-charts-updated');
    }

    public function clearFilters(): void
    {
        $this->scope = '24h';
        $this->filterYear = '';
        $this->filterMonth = '';
        $this->filterDay = '';
        $this->fromHour = '';
        $this->toHour = '';
        $this->search = '';
        $this->resetPage();
        $this->dispatch('ping-charts-updated');
    }

    public function hasActiveFilters(): bool
    {
        return $this->scope !== '24h'
            || $this->filterYear !== ''
            || $this->filterMonth !== ''
            || $this->filterDay !== ''
            || $this->fromHour !== ''
            || $this->toHour !== ''
            || $this->search !== '';
    }

    public function getScopeLabelProperty(): string
    {
        if ($this->scope === 'custom' || $this->filterYear !== '' || $this->filterMonth !== '' || $this->filterDay !== '') {
            if ($this->filterDay !== '' && $this->filterMonth !== '') {
                $year = (int) ($this->filterYear ?: Carbon::now()->year);

                return Carbon::create($year, (int) $this->filterMonth, (int) $this->filterDay)->format('F j, Y');
            }

            if ($this->filterMonth !== '') {
                $year = (int) ($this->filterYear ?: Carbon::now()->year);

                return Carbon::create($year, (int) $this->filterMonth, 1)->format('F Y');
            }

            if ($this->filterYear !== '') {
                return (string) $this->filterYear;
            }

            return 'Custom range';
        }

        return match ($this->scope) {
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            'all' => 'All time',
            default => 'Last 24 hours',
        };
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function window(): array
    {
        $custom = $this->filterYear !== '' || $this->filterMonth !== '' || $this->filterDay !== '';

        if ($custom) {
            if ($this->filterDay !== '' && $this->filterMonth !== '') {
                $year = (int) ($this->filterYear ?: Carbon::now()->year);
                $start = Carbon::create($year, (int) $this->filterMonth, (int) $this->filterDay)->startOfDay();

                return [$start, $start->copy()->endOfDay()];
            }

            if ($this->filterMonth !== '') {
                $year = (int) ($this->filterYear ?: Carbon::now()->year);
                $start = Carbon::create($year, (int) $this->filterMonth, 1)->startOfMonth();

                return [$start, $start->copy()->endOfMonth()];
            }

            $start = Carbon::create((int) $this->filterYear, 1, 1)->startOfYear();

            return [$start, $start->copy()->endOfYear()];
        }

        return match ($this->scope) {
            '7d' => [Carbon::now()->subDays(7), Carbon::now()],
            '30d' => [Carbon::now()->subDays(30), Carbon::now()],
            'all' => [null, null],
            default => [Carbon::now()->subDay(), Carbon::now()],
        };
    }

    protected function hourExpression(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', created_at) AS INTEGER)"
            : 'HOUR(created_at)';
    }

    protected function yearExpression(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', created_at) AS INTEGER)"
            : 'YEAR(created_at)';
    }

    /**
     * @return array<int, int>
     */
    protected function distinctYears(): array
    {
        try {
            return Engagement::select(DB::raw($this->yearExpression().' as y'))
                ->distinct()
                ->orderByDesc('y')
                ->pluck('y')
                ->map(fn ($y) => (int) $y)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function filteredQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Engagement::query();

        [$from, $to] = $this->window();
        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        if ($this->fromHour !== '') {
            $query->whereRaw($this->hourExpression().' >= ?', [(int) $this->fromHour]);
        }

        if ($this->toHour !== '') {
            $query->whereRaw($this->hourExpression().' <= ?', [(int) $this->toHour]);
        }

        if (filled($this->search)) {
            $like = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($like) {
                $q->whereHas('user', function ($u) use ($like) {
                    $u->where('name', 'like', $like)->orWhere('email', 'like', $like);
                })->orWhereHas('artisan', function ($a) use ($like) {
                    $a->where('name', 'like', $like)->orWhere('email', 'like', $like);
                });
            });
        }

        return $query;
    }

    protected function buildChartData(): array
    {
        $labels = [];
        $data = [];

        $custom = $this->filterYear !== '' || $this->filterMonth !== '' || $this->filterDay !== '';

        if ($this->filterDay !== '' && $this->filterMonth !== '') {
            // Single day → hourly buckets
            $year = (int) ($this->filterYear ?: Carbon::now()->year);
            $base = Carbon::create($year, (int) $this->filterMonth, (int) $this->filterDay);
            $counts = $this->bucketCounts($this->hourExpression(), '%H');
            for ($h = 0; $h <= 23; $h++) {
                $labels[] = Carbon::create($year, $base->month, $base->day, $h)->format('g A');
                $data[] = $counts[$h] ?? 0;
            }

            return ['labels' => $labels, 'data' => $data];
        }

        if ($this->filterMonth !== '') {
            // Whole month → daily buckets
            $year = (int) ($this->filterYear ?: Carbon::now()->year);
            $days = Carbon::create($year, (int) $this->filterMonth, 1)->daysInMonth;
            $expr = DB::getDriverName() === 'sqlite'
                ? "CAST(strftime('%d', created_at) AS INTEGER)"
                : 'DAY(created_at)';
            $counts = $this->bucketCounts($expr, '%d');
            for ($d = 1; $d <= $days; $d++) {
                $labels[] = $d;
                $data[] = $counts[$d] ?? 0;
            }

            return ['labels' => $labels, 'data' => $data];
        }

        if ($this->filterYear !== '') {
            // Whole year → monthly buckets
            $year = (int) $this->filterYear;
            $counts = $this->monthlyCounts($year);
            for ($m = 1; $m <= 12; $m++) {
                $labels[] = Carbon::create($year, $m, 1)->format('M');
                $data[] = $counts[$m] ?? 0;
            }

            return ['labels' => $labels, 'data' => $data];
        }

        if ($this->scope === 'all') {
            // All time → monthly buckets across the data span (cap 24 months)
            $years = $this->availableYears;
            if ($years === []) {
                return ['labels' => [], 'data' => []];
            }
            $startYear = min($years);
            $start = Carbon::create($startYear, 1, 1);
            $months = (int) abs(Carbon::now()->diffInMonths($start)) + 1;
            $months = min($months, 24);
            for ($i = $months - 1; $i >= 0; $i--) {
                $cursor = Carbon::now()->subMonthsNoOverflow($i)->startOfMonth();
                $labels[] = $cursor->format('M y');
                $data[] = $this->filteredQuery()
                    ->whereBetween('created_at', [$cursor, $cursor->copy()->endOfMonth()])
                    ->count();
            }

            return ['labels' => $labels, 'data' => $data];
        }

        if (in_array($this->scope, ['7d', '30d'], true)) {
            // Rolling range → daily buckets
            $days = $this->scope === '7d' ? 7 : 30;
            $counts = $this->bucketCounts(
                DB::getDriverName() === 'sqlite' ? "strftime('%Y-%m-%d', created_at)" : 'DATE(created_at)',
                '%Y-%m-%d',
            );
            for ($i = $days - 1; $i >= 0; $i--) {
                $cursor = Carbon::now()->subDays($i);
                $labels[] = $cursor->format('M j');
                $data[] = $counts[$cursor->format('Y-m-d')] ?? 0;
            }

            return ['labels' => $labels, 'data' => $data];
        }

        // Default: last 24 hours → hourly buckets (range-based, not hour-of-day)
        for ($i = 23; $i >= 0; $i--) {
            $start = Carbon::now()->subHours($i)->startOfHour();
            $labels[] = $start->format('g A');
            $data[] = $this->filteredQuery()
                ->whereBetween('created_at', [$start, $start->copy()->endOfHour()])
                ->count();
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Count pings grouped by a driver-switched bucket expression.
     *
     * @return array<int|string, int>
     */
    protected function bucketCounts(string $expression, string $strftimeFormat): array
    {
        $expr = $expression;
        if (DB::getDriverName() === 'sqlite' && ! str_contains($expr, 'strftime')) {
            $expr = "CAST(strftime('".$strftimeFormat."', created_at) AS INTEGER)";
        }

        return $this->filteredQuery()
            ->select(DB::raw($expr.' as bucket'), DB::raw('count(*) as cnt'))
            ->groupBy('bucket')
            ->pluck('cnt', 'bucket')
            ->toArray();
    }

    /**
     * @return array<int, int>
     */
    protected function monthlyCounts(int $year): array
    {
        $expr = DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', created_at) AS INTEGER)"
            : 'MONTH(created_at)';

        return $this->filteredQuery()
            ->select(DB::raw($expr.' as bucket'), DB::raw('count(*) as cnt'))
            ->whereYear('created_at', $year)
            ->groupBy('bucket')
            ->pluck('cnt', 'bucket')
            ->map(fn ($c) => (int) $c)
            ->toArray();
    }

    public function render(): mixed
    {
        $pings = $this->filteredQuery()
            ->with(['user', 'artisan'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $base = $this->filteredQuery();
        $this->stats = [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'high' => (clone $base)->where('urgency_level', 'high')->count(),
            'pingers' => (clone $base)->distinct()->count('user_id'),
        ];

        $this->pingChartData = $this->buildChartData();

        return view('livewire.admin.pings', ['pings' => $pings]);
    }
}; ?>

<div class="px-4 py-8 max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4 min-w-0">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 italic uppercase tracking-tighter">
                {{ __('Pings') }}
            </h1>
            <p class="text-sm text-zinc-500">{{ __('Every service inquiry on the platform — who pinged whom, and when.') }}</p>
        </div>
        @if ($this->hasActiveFilters())
            <flux:button wire:click="clearFilters" variant="ghost" size="sm" icon="x-mark">
                {{ __('Reset to last 24h') }}
            </flux:button>
        @endif
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-5 space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            @foreach (['24h' => 'Last 24h', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'all' => 'All time'] as $value => $label)
                <button wire:click="setScope('{{ $value }}')"
                    class="px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-wide border transition
                        {{ $scope === $value && $filterYear === '' && $filterMonth === '' && $filterDay === ''
                            ? 'bg-purple-600 border-purple-600 text-white'
                            : 'bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:border-purple-400' }}">
                    {{ __($label) }}
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Year') }}</label>
                <select wire:model.live="filterYear" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm font-medium text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none">
                    <option value="">{{ __('Any year') }}</option>
                    @foreach ($availableYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Month') }}</label>
                <select wire:model.live="filterMonth" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm font-medium text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none">
                    <option value="">{{ __('Any month') }}</option>
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}">{{ Carbon::create(2000, $m, 1)->format('F') }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Day') }}</label>
                <select wire:model.live="filterDay" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm font-medium text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none" {{ $filterMonth === '' ? 'disabled' : '' }}>
                    <option value="">{{ $filterMonth === '' ? __('Pick a month first') : __('Any day') }}</option>
                    @for ($d = 1; $d <= 31; $d++)
                        <option value="{{ $d }}">{{ $d }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('From hour') }}</label>
                <select wire:model.live="fromHour" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm font-medium text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none">
                    <option value="">{{ __('00:00') }}</option>
                    @for ($h = 1; $h <= 23; $h++)
                        <option value="{{ $h }}">{{ sprintf('%02d:00', $h) }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('To hour') }}</label>
                <select wire:model.live="toHour" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm font-medium text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none">
                    <option value="">{{ __('23:00') }}</option>
                    @for ($h = 0; $h <= 22; $h++)
                        <option value="{{ $h }}">{{ sprintf('%02d:00', $h) }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Search names') }}</label>
                <input wire:model.live.debounce.400ms="search" type="search" placeholder="{{ __('Name or email...') }}"
                    class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm font-medium text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none" />
            </div>
        </div>
        <p class="text-xs text-zinc-500">{{ __('Showing') }}: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $this->scopeLabel }}</span></p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-5">
            <p class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Total pings') }}</p>
            <p class="mt-1 text-3xl font-black text-zinc-900 dark:text-zinc-100">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-5">
            <p class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Pending') }}</p>
            <p class="mt-1 text-3xl font-black text-amber-500">{{ number_format($stats['pending']) }}</p>
        </div>
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-5">
            <p class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('High urgency') }}</p>
            <p class="mt-1 text-3xl font-black text-red-500">{{ number_format($stats['high']) }}</p>
        </div>
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-5">
            <p class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Unique pingers') }}</p>
            <p class="mt-1 text-3xl font-black text-purple-500">{{ number_format($stats['pingers']) }}</p>
        </div>
    </div>

    <!-- Chart data (morphs on every render — read fresh by initPingChart) -->
    <div id="ping-chart-data" class="hidden" data-chart="{{ json_encode($pingChartData ?: ['labels' => [], 'data' => []]) }}"></div>

    <!-- Chart -->
    <div class="bg-white dark:bg-zinc-900 p-8 rounded-3xl border border-zinc-200 dark:border-zinc-800">
        <h3 class="text-sm font-bold uppercase text-zinc-900 dark:text-white mb-8">
            {{ __('Pings over time') }} — {{ $this->scopeLabel }}
        </h3>
        <div class="h-[300px]">
            <canvas id="pingChart"></canvas>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
        <div class="px-5 py-4 border-b border-zinc-200 dark:border-zinc-800">
            <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-900 dark:text-zinc-100">{{ __('Ping activity') }}</h2>
        </div>

        @if ($pings->isEmpty())
            <div class="p-10 text-center text-sm text-zinc-500">
                {{ __('No pings found for this range.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wider text-zinc-500 border-b border-zinc-200 dark:border-zinc-800">
                            <th class="px-5 py-3 font-bold">{{ __('Time') }}</th>
                            <th class="px-5 py-3 font-bold">{{ __('Pinger') }}</th>
                            <th class="px-5 py-3 font-bold">{{ __('Artisan') }}</th>
                            <th class="px-5 py-3 font-bold">{{ __('Task') }}</th>
                            <th class="px-5 py-3 font-bold">{{ __('Urgency') }}</th>
                            <th class="px-5 py-3 font-bold">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($pings as $ping)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-5 py-3.5 whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                                    {{ $ping->created_at->format('M j, Y') }}
                                    <span class="block text-xs text-zinc-400">{{ $ping->created_at->format('g:i A') }}</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $ping->user?->name ?? '—' }}</p>
                                    <p class="text-xs text-zinc-500">{{ $ping->user?->email }}</p>
                                    <p class="text-[10px] font-bold uppercase text-zinc-400">{{ $ping->user?->role }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $ping->artisan?->name ?? '—' }}</p>
                                    <p class="text-xs text-zinc-500">{{ $ping->artisan?->work ?: $ping->artisan?->role }}</p>
                                    <p class="text-xs text-zinc-500">{{ $ping->artisan?->email }}</p>
                                    @if ($ping->artisan?->phone_number)
                                        <p class="text-xs text-zinc-400">{{ $ping->artisan->phone_number }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 max-w-[260px]">
                                    <p class="text-zinc-700 dark:text-zinc-300 line-clamp-2 break-words">{{ $ping->title }}</p>
                                    @if ($ping->location_context)
                                        <p class="text-xs text-zinc-400 mt-0.5">{{ $ping->location_context }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    @php $urgency = $ping->urgency_level ?? '—'; @endphp
                                    <span class="inline-flex items-center text-[10px] font-bold uppercase px-2 py-0.5 rounded-full
                                        {{ $urgency === 'high' ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'
                                            : ($urgency === 'medium' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
                                            : ($urgency === 'low' ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300'
                                            : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400')) }}">
                                        {{ $urgency }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center text-[10px] font-bold uppercase px-2 py-0.5 rounded-full
                                        {{ $ping->status === 'pending' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
                                            : ($ping->status === 'accepted' || $ping->status === 'started' ? 'bg-purple-100 text-purple-700 dark:bg-purple-500/15 dark:text-purple-300'
                                            : ($ping->status === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300'
                                            : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400')) }}">
                                        {{ $ping->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-4 border-t border-zinc-200 dark:border-zinc-800">
                {{ $pings->links() }}
            </div>
        @endif
    </div>

    <!-- Chart scripts -->
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js" data-navigate-once></script>
        <script>
            document.addEventListener('livewire:initialized', () => {
                if (window.Livewire) {
                    Livewire.on('ping-charts-updated', () => {
                        setTimeout(() => initPingChart(), 80);
                    });
                }
            });

            function initPingChart() {
                const ctx = document.getElementById('pingChart');
                if (!ctx) return;

                const existing = Chart.getChart(ctx);
                if (existing) existing.destroy();

                let chart = { labels: [], data: [] };
                try {
                    const dataEl = document.getElementById('ping-chart-data');
                    chart = JSON.parse(dataEl?.dataset.chart || '{}') || { labels: [], data: [] };
                } catch (e) {
                    chart = { labels: [], data: [] };
                }

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chart.labels || [],
                        datasets: [{
                            label: 'Pings',
                            data: chart.data || [],
                            borderColor: '#a855f7',
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
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { display: false },
                                ticks: { font: { size: 10 }, color: '#71717a', precision: 0 }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 10 }, color: '#71717a', maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }
                            }
                        }
                    }
                });
            }

            document.addEventListener('livewire:navigated', initPingChart);
            document.addEventListener('DOMContentLoaded', initPingChart);
            document.addEventListener('livewire:initialized', initPingChart);
        </script>
    @endpush
</div>
