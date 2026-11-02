<?php

use App\Actions\CompleteMidtransPayment;
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

    public string $selectedPaymentChoice = 'qris';

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
     * Select cashier payment instructions.
     */
    public function chooseCashierPayment(): void
    {
        $this->selectedPaymentChoice = 'cash';
    }

    /**
     * Generate or show a Midtrans QRIS payment for this session.
     */
    public function payWithQris(
        CreateMidtransQrisPayment $createMidtransQrisPayment,
        MidtransClient $midtransClient,
    ): void {
        $this->selectedPaymentChoice = 'qris';

        try {
            $payment = $createMidtransQrisPayment->handle($this->tableSession, $midtransClient);
            $this->tableSession->refresh();

            if ($payment->status === PaymentStatus::Paid || $this->tableSession->isPaid()) {
                session()->flash('status', 'Pembayaran QRIS Midtrans terkonfirmasi lunas! Berikut struk pembayaran Anda.');
            }
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Explicitly check or poll whether the pending Midtrans QRIS payment has been settled.
     */
    public function checkQrisStatus(
        MidtransClient $midtransClient,
        CompleteMidtransPayment $completeMidtransPayment,
    ): void {
        $this->tableSession->refresh();

        if ($this->tableSession->isPaid()) {
            return;
        }

        $pendingQris = $this->tableSession->payments()
            ->where('method', PaymentMethod::Qris)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($pendingQris === null) {
            return;
        }

        $remoteStatus = $midtransClient->getTransactionStatus($pendingQris->invoice_number);

        if ($remoteStatus !== null) {
            $txStatus = strtolower((string) ($remoteStatus['transaction_status'] ?? ''));
            $fraudStatus = strtolower((string) ($remoteStatus['fraud_status'] ?? 'accept'));

            if ($txStatus === 'settlement' || ($txStatus === 'capture' && $fraudStatus === 'accept')) {
                $completeMidtransPayment->handle(
                    $pendingQris,
                    $remoteStatus,
                    (string) ($remoteStatus['transaction_id'] ?? $pendingQris->invoice_number),
                );
                $this->tableSession->refresh();
                session()->flash('status', 'Pembayaran QRIS Midtrans terkonfirmasi lunas! Berikut struk pembayaran Anda.');
            }
        }
    }

    /**
     * Get the data passed to the view.
     *
     * @return array<string, mixed>
     */
    public function with(
        MidtransClient $midtransClient,
        CompleteMidtransPayment $completeMidtransPayment,
    ): array {
        $this->checkQrisStatus($midtransClient, $completeMidtransPayment);

        $orders = $this->tableSession->orders()->with('items')->latest()->get();

        $pendingQris = ! $this->tableSession->isPaid()
            ? $this->tableSession->payments()
                ->where('method', PaymentMethod::Qris)
                ->where('status', PaymentStatus::Pending)
                ->latest('id')
                ->first()
            : null;

        $customerName = $this->tableSession->customer_name
            ?? $orders->firstWhere('customer_name', '!=', null)?->customer_name;

        $customerPhone = $this->tableSession->customer_phone
            ?? $orders->firstWhere('customer_phone', '!=', null)?->customer_phone;

        return [
            'orders' => $orders,
            'pendingQris' => $pendingQris,
            'paidPayment' => $this->tableSession->payments()->where('status', PaymentStatus::Paid)->latest('id')->first(),
            'customerName' => $customerName,
            'customerPhone' => $customerPhone,
            'ItemStatus' => OrderItemStatus::class,
        ];
    }
}; ?>

<div
    wire:poll.2s.keep-alive="checkQrisStatus"
    x-data
    x-on:focus.window="$wire.checkQrisStatus()"
    x-on:visibilitychange.window="if (!document.hidden) $wire.checkQrisStatus()"
    class="space-y-5 p-4 pb-16"
>
    <header class="border-b border-gray-200 pb-4 dark:border-gray-800 print:hidden">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <a href="{{ route('customer.menu', $diningTable->code) }}" wire:navigate class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800" title="Kembali ke Menu">
                    &larr;
                </a>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ config('app.name') }}</p>
                    <h1 class="text-lg font-bold text-gray-900 dark:text-white">Detail Pesanan — {{ $diningTable->name }}</h1>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $tableSession->payment_status->badgeClasses() }}">
                    {{ $tableSession->payment_status->label() }}
                </span>
                <x-theme-toggle />
            </div>
        </div>

        <!-- Stepper Indicator -->
        <div class="mt-3 flex items-center justify-between rounded-xl bg-gray-100 px-3 py-1.5 text-[11px] font-semibold dark:bg-gray-800">
            <span class="text-emerald-600 dark:text-emerald-400">
                &#10003; 1. Pilih Menu
            </span>
            <span class="text-gray-300 dark:text-gray-600">&rarr;</span>
            <span class="text-emerald-600 dark:text-emerald-400">
                &#10003; 2. Data Pemesan
            </span>
            <span class="text-gray-300 dark:text-gray-600">&rarr;</span>
            <span class="font-bold text-brand-600 dark:text-brand-400">
                3. Detail &amp; Pembayaran
            </span>
        </div>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-300 print:hidden">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300 print:hidden">
            {{ session('error') }}
        </div>
    @endif

    @if ($tableSession->isPaid())
        <!-- STRUK PEMBAYARAN LUNAS UNTUK PELANGGAN -->
        <div class="rounded-2xl border border-green-300 bg-white p-5 font-mono text-gray-900 shadow-sm dark:border-green-500/40 dark:bg-gray-800 dark:text-gray-100">
            <div class="border-b border-dashed border-gray-300 pb-4 text-center dark:border-gray-700">
                <span class="inline-block rounded-full bg-green-100 px-3 py-1 font-sans text-xs font-bold text-green-800 dark:bg-green-500/20 dark:text-green-300">
                    PEMBAYARAN LUNAS
                </span>
                <h2 class="mt-2 text-base font-bold uppercase">{{ config('app.name') }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $diningTable->name }} &bull; {{ ($paidPayment?->paid_at ?? $tableSession->paid_at ?? now())->format('d/m/Y H:i') }}
                </p>
                @if ($paidPayment)
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $paidPayment->invoice_number }}</p>
                @endif
            </div>

            @if ($customerName || $customerPhone)
                <div class="border-b border-dashed border-gray-200 py-2.5 text-xs dark:border-gray-700">
                    @if ($customerName)
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Nama Pemesan</span>
                            <span class="font-semibold">{{ $customerName }}</span>
                        </div>
                    @endif
                    @if ($customerPhone)
                        <div class="mt-0.5 flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">No. WhatsApp</span>
                            <span class="font-semibold">{{ $customerPhone }}</span>
                        </div>
                    @endif
                </div>
            @endif

            <div class="divide-y divide-dashed divide-gray-200 py-3 text-xs dark:divide-gray-700">
                @foreach ($orders as $order)
                    @if ($order->status->value !== 'cancelled')
                        <div class="py-2">
                            <p class="font-semibold">{{ $order->order_number }}</p>
                            @foreach ($order->items as $item)
                                @if ($item->status === $ItemStatus::Active)
                                    <div class="mt-1 flex justify-between">
                                        <span>{{ $item->quantity }}x {{ $item->item_name }}</span>
                                        <x-rupiah :amount="$item->subtotal" />
                                    </div>
                                    @if ($item->notes)
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400">Catatan: {{ $item->notes }}</p>
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="space-y-1.5 border-t border-dashed border-gray-300 pt-3 text-xs dark:border-gray-700">
                <div class="flex justify-between text-sm font-bold">
                    <span>TOTAL TAGIHAN</span>
                    <x-rupiah :amount="$tableSession->total_amount" />
                </div>
                @if ($paidPayment)
                    <div class="flex justify-between">
                        <span>Metode Pembayaran</span>
                        <span class="font-semibold">{{ $paidPayment->method->label() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Nominal Dibayar</span>
                        <x-rupiah :amount="$paidPayment->amount_received ?? $paidPayment->amount" />
                    </div>
                    @if ($paidPayment->change_amount > 0)
                        <div class="flex justify-between">
                            <span>Kembalian</span>
                            <x-rupiah :amount="$paidPayment->change_amount" />
                        </div>
                    @endif
                @endif
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 font-sans print:hidden">
                <button
                    type="button"
                    onclick="window.print()"
                    class="flex items-center justify-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-xs font-bold uppercase tracking-wider text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                >
                    <x-icon name="receipt" class="h-4 w-4" /> Cetak Struk
                </button>
                <a
                    href="{{ route('customer.menu', $diningTable->code) }}"
                    wire:navigate
                    class="flex items-center justify-center rounded-xl bg-brand-600 px-3 py-2.5 text-center text-xs font-bold uppercase tracking-wider text-white hover:bg-brand-500"
                >
                    + Pesanan Baru
                </a>
            </div>
        </div>
    @else
        <!-- BAGIAN 1: RINGKASAN DATA PEMESAN & DETAIL PESANAN -->
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-800/50">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                <h2 class="text-sm font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200">
                    Detail Pesanan Anda
                </h2>
                <a href="{{ route('customer.menu', $diningTable->code) }}" wire:navigate class="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-400">
                    + Tambah Menu
                </a>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-gray-50 p-3 text-xs dark:bg-gray-900/60">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Nama Pemesan</span>
                    <p class="font-bold text-gray-900 dark:text-white">{{ $customerName ?: 'Pelanggan' }}</p>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Nomor WhatsApp</span>
                    <p class="font-bold text-gray-900 dark:text-white">{{ $customerPhone ?: '-' }}</p>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Meja</span>
                    <p class="font-bold text-gray-900 dark:text-white">{{ $diningTable->name }}</p>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Jumlah Pesanan</span>
                    <p class="font-bold text-gray-900 dark:text-white">{{ $orders->count() }} pesanan</p>
                </div>
            </div>

            <!-- Rincian Item Pesanan -->
            <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($orders as $order)
                    <div wire:key="order-{{ $order->id }}" class="py-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-900 dark:text-white">{{ $order->order_number }}</p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">Dipesan pukul {{ $order->created_at->format('H:i') }}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $order->status->badgeClasses() }}">
                                {{ $order->status->label() }}
                            </span>
                        </div>

                        <ul class="mt-2.5 space-y-2 text-sm">
                            @foreach ($order->items as $item)
                                <li class="flex items-start justify-between gap-2">
                                    <div>
                                        <span @class(['font-medium text-gray-800 dark:text-gray-200', 'line-through text-gray-400' => $item->status === $ItemStatus::Cancelled])>
                                            {{ $item->quantity }}x {{ $item->item_name }}
                                        </span>
                                        @if ($item->notes)
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Catatan: {{ $item->notes }}</p>
                                        @endif
                                        @if ($item->status === $ItemStatus::Cancelled && $item->cancellation_reason)
                                            <p class="text-xs text-red-600 dark:text-red-400">Dibatalkan: {{ $item->cancellation_reason }}</p>
                                        @endif
                                    </div>
                                    <span @class(['text-xs font-semibold text-gray-900 dark:text-white', 'line-through text-gray-400' => $item->status === $ItemStatus::Cancelled])>
                                        <x-rupiah :amount="$item->subtotal" />
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            <div class="mt-2 flex items-center justify-between border-t border-gray-200 pt-3 dark:border-gray-700">
                <span class="text-sm font-bold text-gray-700 dark:text-gray-200">Total Tagihan Meja</span>
                <span class="text-xl font-extrabold text-brand-600 dark:text-brand-400"><x-rupiah :amount="$tableSession->total_amount" /></span>
            </div>
        </div>

        <!-- BAGIAN 2: MELAKUKAN PEMBAYARAN -->
        @if ($tableSession->isOpen())
            <div class="rounded-2xl border-2 border-brand-500/40 bg-brand-50/40 p-4 shadow-xs dark:border-brand-500/30 dark:bg-gray-800/70">
                <h2 class="text-sm font-bold uppercase tracking-wider text-gray-900 dark:text-white">
                    Pilih Metode Pembayaran
                </h2>
                <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">
                    Silakan pilih metode pembayaran untuk menyelesaikan pesanan Anda:
                </p>

                <div class="mt-3 grid grid-cols-2 gap-2.5">
                    <button
                        type="button"
                        wire:click="payWithQris"
                        @class([
                            'flex flex-col items-center justify-center rounded-xl border p-3 text-center transition',
                            'border-brand-600 bg-brand-600 text-white shadow-md' => $selectedPaymentChoice === 'qris' || $pendingQris !== null,
                            'border-gray-300 bg-white text-gray-800 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200' => $selectedPaymentChoice !== 'qris' && $pendingQris === null,
                        ])
                    >
                        <span class="text-xs font-bold uppercase">Bayar QRIS (Midtrans)</span>
                        <span class="mt-0.5 text-[10px] opacity-90">Otomatis Lunas &amp; Struk</span>
                    </button>

                    <button
                        type="button"
                        wire:click="chooseCashierPayment"
                        @class([
                            'flex flex-col items-center justify-center rounded-xl border p-3 text-center transition',
                            'border-gray-900 bg-gray-900 text-white shadow-md dark:border-white dark:bg-white dark:text-gray-900' => $selectedPaymentChoice === 'cash' && $pendingQris === null,
                            'border-gray-300 bg-white text-gray-800 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200' => $selectedPaymentChoice !== 'cash' || $pendingQris !== null,
                        ])
                    >
                        <span class="text-xs font-bold uppercase">Bayar di Kasir</span>
                        <span class="mt-0.5 text-[10px] opacity-80">Tunai / Debit / Kredit</span>
                    </button>
                </div>

                @if ($pendingQris && ! empty($pendingQris->payload['qr_string']))
                    <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-4 text-center dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-xs font-bold uppercase tracking-wider text-brand-600 dark:text-brand-400">QRIS Midtrans</p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $pendingQris->invoice_number }}</p>
                        <div class="mx-auto my-3 flex w-fit flex-col items-center rounded-xl border border-gray-200 bg-white p-3">
                            @if ($pendingQris->payment_url)
                                <img src="{{ $pendingQris->payment_url }}" alt="QRIS Midtrans" class="h-48 w-48 object-contain" />
                            @else
                                {!! SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(190)->margin(1)->generate($pendingQris->payload['qr_string']) !!}
                            @endif
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Pindai kode QRIS di atas menggunakan aplikasi e-wallet atau mobile banking Anda.</p>

                        @if (! config('midtrans.is_production') && ($pendingQris->payment_url || ! empty($pendingQris->payload['qris_url'])))
                            @php($qrisImageUrl = $pendingQris->payment_url ?? $pendingQris->payload['qris_url'])
                            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50/70 p-2.5 text-left text-[11px] dark:border-amber-500/30 dark:bg-amber-500/10">
                                <p class="font-bold text-amber-900 dark:text-amber-300">QR Code Image URL (untuk Simulator v2):</p>
                                <input
                                    type="text"
                                    readonly
                                    value="{{ $qrisImageUrl }}"
                                    onclick="this.select()"
                                    class="mt-1 w-full rounded border border-amber-300 bg-white px-2 py-1 font-mono text-[10px] text-gray-800"
                                />
                                <a
                                    href="https://simulator.sandbox.midtrans.com/v2/qris/index"
                                    target="_blank"
                                    class="mt-1.5 block w-full rounded-lg bg-amber-600 px-3 py-1.5 text-center font-bold text-white hover:bg-amber-500"
                                >
                                    Buka Simulator QRIS v2 Midtrans
                                </a>
                            </div>
                        @endif

                        <button
                            type="button"
                            wire:click="checkQrisStatus"
                            class="mt-3 w-full rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow hover:bg-brand-500"
                        >
                            Cek Status Pembayaran
                        </button>
                    </div>
                @elseif ($selectedPaymentChoice === 'qris')
                    <div class="mt-4 rounded-xl border border-brand-200 bg-white p-4 text-center dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-xs text-gray-600 dark:text-gray-300">
                            Klik tombol di bawah ini untuk menampilkan kode QRIS pembayaran sebesar <strong><x-rupiah :amount="$tableSession->total_amount" /></strong>:
                        </p>
                        <button
                            type="button"
                            wire:click="payWithQris"
                            wire:loading.attr="disabled"
                            class="mt-3 w-full rounded-xl bg-brand-600 px-4 py-3 text-xs font-bold uppercase tracking-wider text-white shadow hover:bg-brand-500 disabled:opacity-50"
                        >
                            Tampilkan Kode QRIS Sekarang
                        </button>
                    </div>
                @else
                    <div id="bayar-kasir" class="mt-4 rounded-xl border border-gray-200 bg-white p-4 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <p class="font-bold text-gray-900 dark:text-white">Instruksi Pembayaran di Kasir:</p>
                        <p class="mt-1">
                            Silakan menuju meja kasir dan sebutkan <strong>{{ $diningTable->name }}</strong>
                            @if ($customerName)
                                atas nama <strong>{{ $customerName }}</strong>
                            @endif
                            dengan total tagihan <strong><x-rupiah :amount="$tableSession->total_amount" /></strong>.
                        </p>
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>
