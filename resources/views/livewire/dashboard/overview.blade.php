<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Livewire\Volt\Component;

new class extends Component
{
    public string $period = 'daily';

    public string $search = '';

    /**
     * Build the sales chart series for the selected period (daily, weekly, monthly).
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    protected function buildChartSeries(): array
    {
        $labels = [];
        $values = [];

        if ($this->period === 'weekly') {
            for ($i = 7; $i >= 0; $i--) {
                $start = Carbon::now()->subWeeks($i)->startOfWeek();
                $end = Carbon::now()->subWeeks($i)->endOfWeek();
                $labels[] = $start->format('d M');
                $values[] = (int) Payment::where('status', PaymentStatus::Paid)
                    ->whereBetween('paid_at', [$start, $end])
                    ->sum('amount');
            }

            return ['labels' => $labels, 'values' => $values];
        }

        if ($this->period === 'monthly') {
            for ($i = 5; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $labels[] = $month->format('M Y');
                $values[] = (int) Payment::where('status', PaymentStatus::Paid)
                    ->whereYear('paid_at', $month->year)
                    ->whereMonth('paid_at', $month->month)
                    ->sum('amount');
            }

            return ['labels' => $labels, 'values' => $values];
        }

        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $labels[] = $day->format('d M');
            $values[] = (int) Payment::where('status', PaymentStatus::Paid)
                ->whereDate('paid_at', $day)
                ->sum('amount');
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Get the data passed to the view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $todaySales = (int) Payment::where('status', PaymentStatus::Paid)
            ->whereDate('paid_at', Carbon::today())
            ->sum('amount');

        $todayOrdersCount = Order::whereDate('created_at', Carbon::today())
            ->where('status', '!=', OrderStatus::Cancelled)
            ->count();

        $activeTablesCount = DiningTable::whereIn('status', [TableStatus::Occupied, TableStatus::Paid])->count();
        $totalTablesCount = DiningTable::where('is_active', true)->count();

        $recentOrders = Order::with(['diningTable', 'tableSession'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($sub): void {
                    $sub->where('order_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('diningTable', fn ($t) => $t->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->latest()
            ->limit(10)
            ->get();

        return [
            'todaySales' => $todaySales,
            'todayOrdersCount' => $todayOrdersCount,
            'activeTablesCount' => $activeTablesCount,
            'totalTablesCount' => $totalTablesCount,
            'chartSeries' => $this->buildChartSeries(),
            'recentOrders' => $recentOrders,
        ];
    }
}; ?>

<div class="space-y-6 p-4 sm:p-6 lg:p-8">
    <!-- Stat Cards -->
    <div class="grid gap-4 sm:grid-cols-3">
        <x-card class="p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Penjualan Hari Ini</p>
            <p class="mt-2 text-2xl font-bold text-brand-600 dark:text-brand-400"><x-rupiah :amount="$todaySales" /></p>
        </x-card>

        <x-card class="p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Pesanan Hari Ini</p>
            <p class="mt-2 text-2xl font-bold">{{ $todayOrdersCount }} Pesanan</p>
        </x-card>

        <x-card class="p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Meja Aktif</p>
            <p class="mt-2 text-2xl font-bold">{{ $activeTablesCount }} / {{ $totalTablesCount }} Meja</p>
        </x-card>
    </div>

    <!-- Sales Chart -->
    <x-card class="p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-bold">Grafik Penjualan</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Tren pembayaran lunas berdasarkan periode.</p>
            </div>
            <div class="flex gap-2">
                @foreach (['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan'] as $pKey => $pLabel)
                    <button
                        type="button"
                        wire:click="$set('period', '{{ $pKey }}')"
                        @class([
                            'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                            'bg-brand-600 text-white' => $period === $pKey,
                            'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => $period !== $pKey,
                        ])
                    >
                        {{ $pLabel }}
                    </button>
                @endforeach
            </div>
        </div>

        <div
            wire:key="sales-chart-{{ $period }}"
            x-data="{
                labels: @js($chartSeries['labels']),
                values: @js($chartSeries['values']),
                init() {
                    if (!window.Chart) return;
                    new window.Chart(this.$refs.canvas, {
                        type: 'bar',
                        data: {
                            labels: this.labels,
                            datasets: [{
                                label: 'Penjualan (Rp)',
                                data: this.values,
                                backgroundColor: '#d97706',
                                borderRadius: 6,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                        }
                    });
                }
            }"
            class="mt-4 h-64"
        >
            <canvas x-ref="canvas"></canvas>
        </div>
    </x-card>

    <!-- Recent Orders / Transactions Table -->
    <x-card class="p-6">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-base font-bold">Pesanan &amp; Transaksi Terbaru</h2>
            <x-text-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari no. pesanan atau meja..." class="w-full text-sm sm:max-w-xs" />
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">No. Pesanan</th>
                        <th class="px-4 py-3">Meja</th>
                        <th class="px-4 py-3">Sumber</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Status Pesanan</th>
                        <th class="px-4 py-3">Status Bayar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($recentOrders as $order)
                        <tr wire:key="dash-order-{{ $order->id }}">
                            <td class="px-4 py-3 font-medium">{{ $order->order_number }}</td>
                            <td class="px-4 py-3">{{ $order->diningTable->name }}</td>
                            <td class="px-4 py-3 uppercase text-xs">{{ $order->source }}</td>
                            <td class="px-4 py-3 font-semibold"><x-rupiah :amount="$order->total_amount" /></td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $order->status->badgeClasses() }}">
                                    {{ $order->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $order->tableSession->payment_status->badgeClasses() }}">
                                    {{ $order->tableSession->payment_status->label() }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada data pesanan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
