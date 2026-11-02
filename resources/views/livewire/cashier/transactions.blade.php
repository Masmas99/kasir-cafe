<?php

use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\TableSession;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Transaksi')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $paymentStatus = '';

    #[Url]
    public ?int $tableId = null;

    /**
     * Reset pagination when any filter is updated.
     */
    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * Get the data passed to the view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $sessions = TableSession::with(['diningTable', 'payments'])
            ->withCount('orders')
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('opened_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('opened_at', '<=', $this->dateTo))
            ->when($this->paymentStatus !== '', fn ($query) => $query->where('payment_status', $this->paymentStatus))
            ->when($this->tableId, fn ($query) => $query->where('dining_table_id', $this->tableId))
            ->latest('opened_at')
            ->paginate(15);

        return [
            'sessions' => $sessions,
            'tables' => DiningTable::orderBy('name')->get(),
            'paymentStatuses' => PaymentStatus::cases(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Riwayat Transaksi" description="Filter transaksi berdasarkan tanggal, status pembayaran, dan meja." />

    <div class="space-y-4 p-4 sm:p-6 lg:p-8">
        <x-card class="grid gap-3 p-4 sm:grid-cols-4">
            <div>
                <x-input-label for="dateFrom" value="Dari Tanggal" />
                <x-text-input wire:model.live="dateFrom" id="dateFrom" type="date" class="mt-1 block w-full text-sm" />
            </div>

            <div>
                <x-input-label for="dateTo" value="Sampai Tanggal" />
                <x-text-input wire:model.live="dateTo" id="dateTo" type="date" class="mt-1 block w-full text-sm" />
            </div>

            <div>
                <x-input-label for="paymentStatus" value="Status Pembayaran" />
                <x-select-input wire:model.live="paymentStatus" id="paymentStatus" class="mt-1 block w-full text-sm">
                    <option value="">Semua Status</option>
                    @foreach ($paymentStatuses as $statusOption)
                        <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                    @endforeach
                </x-select-input>
            </div>

            <div>
                <x-input-label for="tableId" value="Meja" />
                <x-select-input wire:model.live="tableId" id="tableId" class="mt-1 block w-full text-sm">
                    <option value="">Semua Meja</option>
                    @foreach ($tables as $table)
                        <option value="{{ $table->id }}">{{ $table->name }}</option>
                    @endforeach
                </x-select-input>
            </div>
        </x-card>

        <x-card class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Waktu Buka</th>
                        <th class="px-4 py-3">Meja</th>
                        <th class="px-4 py-3">Pesanan</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Status Bayar</th>
                        <th class="px-4 py-3">Status Sesi</th>
                        <th class="px-4 py-3 text-right">Struk</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($sessions as $session)
                        <tr wire:key="tx-session-{{ $session->id }}">
                            <td class="whitespace-nowrap px-4 py-3">{{ $session->opened_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 font-medium">{{ $session->diningTable->name }}</td>
                            <td class="px-4 py-3">{{ $session->orders_count }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-semibold"><x-rupiah :amount="$session->total_amount" /></td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $session->payment_status->badgeClasses() }}">
                                    {{ $session->payment_status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                {{ $session->closed_at ? 'Ditutup '.$session->closed_at->format('H:i') : 'Aktif' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <a href="{{ route('cashier.receipts.show', $session) }}" target="_blank" class="font-medium text-brand-600 hover:underline dark:text-brand-400">
                                    Lihat Struk
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Tidak ada transaksi ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        {{ $sessions->links() }}
    </div>
</div>
