<?php

use App\Actions\CreateMidtransQrisPayment;
use App\Enums\OrderItemStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\TableSession;
use App\Services\Midtrans\MidtransClient;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.customer')] class extends Component
{
    public DiningTable $diningTable;

    public TableSession $tableSession;

    /**
     * Mount and verify the session belongs to the scanned table.
     */
    public function mount(DiningTable $diningTable, TableSession $tableSession): void
    {
        abort_unless($tableSession->dining_table_id === $diningTable->id, 404);

        $this->diningTable = $diningTable;
        $this->tableSession = $tableSession;
    }

    /**
     * Generate or show a Midtrans QRIS payment for this session.
     */
    public function payWithQris(
        CreateMidtransQrisPayment $createMidtransQrisPayment,
        MidtransClient $midtransClient,
    ): void {
        try {
            $createMidtransQrisPayment->handle($this->tableSession, $midtransClient);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Get the data passed to the view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $this->tableSession->refresh();

        $pendingQris = $this->tableSession->payments()
            ->where('method', PaymentMethod::Qris)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        return [
            'orders' => $this->tableSession->orders()->with('items')->latest()->get(),
            'pendingQris' => $pendingQris,
            'ItemStatus' => OrderItemStatus::class,
        ];
    }
}; ?>

<div wire:poll.5s class="space-y-6 p-4 pb-16">
    <header class="flex items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-800">
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ config('app.name') }}</p>
            <h1 class="text-lg font-bold">{{ $diningTable->name }}</h1>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $tableSession->payment_status->badgeClasses() }}">
            {{ $tableSession->payment_status->label() }}
        </span>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-600 dark:text-gray-400">Total Tagihan Meja</span>
            <span class="text-xl font-bold text-brand-600 dark:text-brand-400"><x-rupiah :amount="$tableSession->total_amount" /></span>
        </div>

        @if ($tableSession->isOpen() && ! $tableSession->isPaid())
            <div class="mt-4 space-y-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Pilihan Pembayaran:</p>
                <div class="grid grid-cols-2 gap-2">
                    <a
                        href="#bayar-kasir"
                        class="flex items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    >
                        Bayar di Kasir
                    </a>
                    <button
                        type="button"
                        wire:click="payWithQris"
                        class="w-full rounded-xl bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-500"
                    >
                        Bayar QRIS (Midtrans)
                    </button>
                </div>

                @if ($pendingQris && ! empty($pendingQris->payload['qr_string']))
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 text-center dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-xs font-bold uppercase tracking-wider text-brand-600">QRIS Midtrans</p>
                        <p class="text-[11px] text-gray-500">{{ $pendingQris->invoice_number }}</p>
                        <div class="mx-auto my-3 w-fit rounded-xl bg-white p-3">
                            {!! SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(190)->margin(1)->generate($pendingQris->payload['qr_string']) !!}
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Pindai kode QRIS di atas menggunakan aplikasi e-wallet atau mobile banking Anda.</p>
                        @if (! empty($pendingQris->payload['snap_redirect_url']))
                            <a
                                href="{{ $pendingQris->payload['snap_redirect_url'] }}"
                                target="_blank"
                                class="mt-3 block w-full rounded-xl border border-brand-600 bg-brand-50 px-3 py-2 text-xs font-bold text-brand-700 hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-300"
                            >
                                Buka Halaman Pembayaran Midtrans
                            </a>
                        @endif
                    </div>
                @endif

                <p id="bayar-kasir" class="text-xs text-gray-500 dark:text-gray-400">
                    Untuk pembayaran tunai, sebutkan <strong>{{ $diningTable->name }}</strong> di kasir.
                </p>
            </div>
        @endif
    </div>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Daftar Pesanan</h2>
            @if ($tableSession->isOpen() && ! $tableSession->isPaid())
                <a href="{{ route('customer.menu', $diningTable->code) }}" wire:navigate class="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-400">
                    + Tambah Pesanan
                </a>
            @endif
        </div>

        @foreach ($orders as $order)
            <div wire:key="order-{{ $order->id }}" class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="flex items-center justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                    <div>
                        <p class="text-xs font-semibold text-gray-900 dark:text-white">{{ $order->order_number }}</p>
                        <p class="text-xs text-gray-500">{{ $order->created_at->format('H:i') }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $order->status->badgeClasses() }}">
                        {{ $order->status->label() }}
                    </span>
                </div>

                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($order->items as $item)
                        <li class="flex items-start justify-between gap-2">
                            <div>
                                <span @class(['line-through text-gray-400' => $item->status === $ItemStatus::Cancelled])>
                                    {{ $item->quantity }}x {{ $item->item_name }}
                                </span>
                                @if ($item->notes)
                                    <p class="text-xs text-gray-500">Catatan: {{ $item->notes }}</p>
                                @endif
                                @if ($item->status === $ItemStatus::Cancelled && $item->cancellation_reason)
                                    <p class="text-xs text-red-600 dark:text-red-400">Dibatalkan: {{ $item->cancellation_reason }}</p>
                                @endif
                            </div>
                            <span @class(['text-xs font-medium', 'line-through text-gray-400' => $item->status === $ItemStatus::Cancelled])>
                                <x-rupiah :amount="$item->subtotal" />
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</div>
