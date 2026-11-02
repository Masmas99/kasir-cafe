<?php

use App\Actions\CompleteMidtransPayment;
use App\Actions\CreateMidtransQrisPayment;
use App\Actions\PlaceOrder;
use App\Actions\RecordCashierPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Exceptions\TableNotAcceptingOrdersException;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\TableSession;
use App\Services\Midtrans\MidtransClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Pesanan Manual')] class extends Component
{
    public ?int $dining_table_id = null;

    public string $search = '';

    public ?int $selectedCategory = null;

    /**
     * Cart keyed by menu_item_id.
     *
     * @var array<int, array{quantity: int, notes: string}>
     */
    public array $cart = [];

    public string $payment_method = 'cash';

    public ?int $amount_received = null;

    /**
     * Current workflow step: 'order' | 'qris' | 'receipt'.
     */
    public string $step = 'order';

    public ?int $activePaymentId = null;

    public ?int $completedSessionId = null;

    /**
     * Add an available menu item to the manual order cart.
     */
    public function addItem(int $menuItemId): void
    {
        $menuItem = MenuItem::find($menuItemId);

        if ($menuItem === null || ! $menuItem->is_available) {
            session()->flash('error', 'Menu sedang habis.');

            return;
        }

        $current = $this->cart[$menuItemId] ?? ['quantity' => 0, 'notes' => ''];
        $current['quantity']++;
        $this->cart[$menuItemId] = $current;

        $this->syncDefaultAmountReceived();
    }

    /**
     * Decrement or remove an item from the manual order cart.
     */
    public function decrementItem(int $menuItemId): void
    {
        if (! isset($this->cart[$menuItemId])) {
            return;
        }

        $this->cart[$menuItemId]['quantity']--;

        if ($this->cart[$menuItemId]['quantity'] <= 0) {
            unset($this->cart[$menuItemId]);
        }

        $this->syncDefaultAmountReceived();
    }

    /**
     * Recalculate default cash received when table or payment method changes.
     */
    public function updatedDiningTableId(): void
    {
        $this->syncDefaultAmountReceived();
    }

    /**
     * Set exact cash amount for quick button.
     */
    public function setExactCash(int $amount): void
    {
        $this->amount_received = $amount;
    }

    /**
     * Keep amount_received synced with total bill when using cash.
     */
    protected function syncDefaultAmountReceived(): void
    {
        $this->amount_received = $this->calculateGrandTotal();
    }

    /**
     * Calculate the total of items currently in the cart plus any existing unpaid bill on the selected table.
     */
    protected function calculateGrandTotal(): int
    {
        $cartIds = array_map('intval', array_keys($this->cart));
        $cartMenuItems = $cartIds !== [] ? MenuItem::whereIn('id', $cartIds)->get()->keyBy('id') : collect();

        $cartTotal = 0;
        foreach ($this->cart as $id => $entry) {
            $item = $cartMenuItems->get((int) $id);
            if ($item) {
                $cartTotal += $item->price * (int) ($entry['quantity'] ?? 0);
            }
        }

        $existingSessionTotal = 0;
        if ($this->dining_table_id) {
            $table = DiningTable::with('activeSession')->find($this->dining_table_id);
            if ($table?->activeSession && ! $table->activeSession->isPaid()) {
                $existingSessionTotal = $table->activeSession->total_amount;
            }
        }

        return $cartTotal + $existingSessionTotal;
    }

    /**
     * Build the normalized items array from the cart.
     *
     * @return array<int, array{menu_item_id: int, quantity: int, notes: string|null}>
     */
    protected function buildCartItemsPayload(): array
    {
        $items = [];
        foreach ($this->cart as $menuItemId => $entry) {
            if (($entry['quantity'] ?? 0) > 0) {
                $items[] = [
                    'menu_item_id' => (int) $menuItemId,
                    'quantity' => (int) $entry['quantity'],
                    'notes' => $entry['notes'] ?? null,
                ];
            }
        }

        return $items;
    }

    /**
     * Submit the manual order and immediately process payment (Cash -> Receipt, QRIS -> Show QRIS).
     */
    public function submitManualOrder(
        PlaceOrder $placeOrder,
        RecordCashierPayment $recordCashierPayment,
        CreateMidtransQrisPayment $createMidtransQrisPayment,
        MidtransClient $midtransClient,
    ): void {
        $validated = $this->validate([
            'dining_table_id' => ['required', 'integer', 'exists:dining_tables,id'],
            'payment_method' => ['required', Rule::in([PaymentMethod::Cash->value, PaymentMethod::Qris->value])],
        ], [
            'dining_table_id.required' => 'Wajib memilih meja untuk pesanan manual.',
        ]);

        $grandTotal = $this->calculateGrandTotal();

        if ($validated['payment_method'] === PaymentMethod::Cash->value) {
            $cashToReceive = $this->amount_received ?? $grandTotal;
            if ($cashToReceive < $grandTotal) {
                $this->addError('amount_received', 'Nominal uang tunai kurang dari total tagihan.');

                return;
            }
        }

        $table = DiningTable::findOrFail($validated['dining_table_id']);
        $items = $this->buildCartItemsPayload();

        try {
            $order = $placeOrder->handle($table, $items, Auth::user());
        } catch (TableNotAcceptingOrdersException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $session = $order->tableSession->refresh();

        if ($validated['payment_method'] === PaymentMethod::Qris->value) {
            try {
                $payment = $createMidtransQrisPayment->handle($session, $midtransClient, Auth::user());
            } catch (\Throwable $e) {
                session()->flash('error', $e->getMessage());

                return;
            }

            $this->cart = [];
            $this->activePaymentId = $payment->id;
            $this->completedSessionId = $session->id;
            $this->step = 'qris';

            return;
        }

        $cashReceived = max($session->total_amount, (int) ($this->amount_received ?? $session->total_amount));

        $payment = $recordCashierPayment->handle(
            $session,
            Auth::user(),
            PaymentMethod::Cash,
            $cashReceived,
        );

        $this->cart = [];
        $this->activePaymentId = $payment->id;
        $this->completedSessionId = $session->id;
        $this->step = 'receipt';

        session()->flash('status', "Pesanan {$order->order_number} & pembayaran Tunai untuk {$table->name} berhasil! Berikut struk pembayaran.");
    }

    /**
     * Poll or manually check whether the pending Midtrans QRIS payment has been settled.
     */
    public function checkQrisStatus(
        MidtransClient $midtransClient,
        CompleteMidtransPayment $completeMidtransPayment,
    ): void {
        if (! $this->activePaymentId) {
            return;
        }

        $payment = Payment::with('tableSession')->find($this->activePaymentId);

        if ($payment === null) {
            return;
        }

        if ($payment->status === PaymentStatus::Paid || $payment->tableSession->isPaid()) {
            $this->step = 'receipt';

            return;
        }

        if ($payment->payment_url === null && ! empty($payment->payload['token'])) {
            try {
                $result = $midtransClient->createQrisCharge($payment);
                $payment->update([
                    'payment_url' => $result['qr_url'],
                    'payload' => array_merge($payment->payload ?? [], $result['response'], [
                        'qr_string' => $result['qr_string'],
                        'qr_url' => $result['qr_url'],
                    ]),
                ]);
                $payment->refresh();
            } catch (\Throwable) {
                // Ignore transient network errors during poll
            }
        }

        $remoteStatus = $midtransClient->getTransactionStatus($payment->invoice_number);

        if ($remoteStatus !== null) {
            $txStatus = strtolower((string) ($remoteStatus['transaction_status'] ?? ''));
            $fraudStatus = strtolower((string) ($remoteStatus['fraud_status'] ?? 'accept'));

            if ($txStatus === 'settlement' || ($txStatus === 'capture' && $fraudStatus === 'accept')) {
                $completeMidtransPayment->handle(
                    $payment,
                    $remoteStatus,
                    (string) ($remoteStatus['transaction_id'] ?? $payment->invoice_number),
                );
                $this->step = 'receipt';
                session()->flash('status', 'Pembayaran QRIS Midtrans terkonfirmasi lunas! Berikut struk pembayaran.');
            }
        }
    }

    /**
     * Reset state to start a fresh manual order.
     */
    public function startNewOrder(): void
    {
        $this->reset('cart', 'dining_table_id', 'amount_received', 'activePaymentId', 'completedSessionId');
        $this->payment_method = PaymentMethod::Cash->value;
        $this->step = 'order';
        $this->resetValidation();
    }

    /**
     * Get the data passed to the view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $tables = DiningTable::with('activeSession')
            ->where('is_active', true)
            ->where('status', '!=', TableStatus::Paid)
            ->orderBy('name')
            ->get();

        $categories = Category::with([
            'menuItems' => fn ($query) => $query
                ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                ->orderBy('name'),
        ])->orderBy('sort_order')->orderBy('name')->get();

        $cartIds = array_map('intval', array_keys($this->cart));
        $cartMenuItems = $cartIds !== [] ? MenuItem::whereIn('id', $cartIds)->get()->keyBy('id') : collect();

        $cartTotal = 0;
        foreach ($this->cart as $id => $entry) {
            $item = $cartMenuItems->get((int) $id);
            if ($item) {
                $cartTotal += $item->price * (int) ($entry['quantity'] ?? 0);
            }
        }

        $existingSessionTotal = 0;
        if ($this->dining_table_id) {
            $selectedTable = $tables->firstWhere('id', $this->dining_table_id);
            if ($selectedTable?->activeSession && ! $selectedTable->activeSession->isPaid()) {
                $existingSessionTotal = $selectedTable->activeSession->total_amount;
            }
        }

        $grandTotal = $cartTotal + $existingSessionTotal;
        $estimatedChange = max(0, ((int) ($this->amount_received ?? $grandTotal)) - $grandTotal);

        $activePayment = $this->activePaymentId
            ? Payment::with('tableSession.diningTable')->find($this->activePaymentId)
            : null;

        $completedSession = $this->completedSessionId
            ? TableSession::with(['diningTable', 'orders.items', 'payments'])->find($this->completedSessionId)
            : null;

        return [
            'tables' => $tables,
            'categories' => $categories,
            'cartMenuItems' => $cartMenuItems,
            'cartTotal' => $cartTotal,
            'existingSessionTotal' => $existingSessionTotal,
            'grandTotal' => $grandTotal,
            'estimatedChange' => $estimatedChange,
            'activePayment' => $activePayment,
            'completedSession' => $completedSession,
            'isSandbox' => ! config('midtrans.is_production'),
        ];
    }
}; ?>

<div>
    <x-page-header title="Input Pesanan Manual & Kasir" description="Pilih menu bergambar, pilih metode pembayaran (Tunai / QRIS Midtrans), dan cetak struk." />

    <div class="p-4 sm:p-6 lg:p-8">
        <x-flash-message class="mb-4" />

        @if ($step === 'qris' && $activePayment)
            <!-- STEP 2A: TAMPILAN PEMBAYARAN QRIS MIDTRANS -->
            <div
                wire:poll.2s.keep-alive="checkQrisStatus"
                x-data
                x-on:focus.window="$wire.checkQrisStatus()"
                x-on:visibilitychange.window="if (!document.hidden) $wire.checkQrisStatus()"
                class="mx-auto max-w-md"
            >
                <x-card class="p-6 text-center space-y-5">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
                        <div class="text-left">
                            <span class="text-xs font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-400">QRIS Midtrans</span>
                            <h2 class="text-lg font-bold">{{ $activePayment->tableSession->diningTable->name }}</h2>
                        </div>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-500/10 dark:text-amber-400">
                            Menunggu Pembayaran
                        </span>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Total Tagihan</p>
                        <p class="text-3xl font-extrabold text-brand-600 dark:text-brand-400">
                            <x-rupiah :amount="$activePayment->amount" />
                        </p>
                        <p class="mt-1 text-xs text-gray-400">No. Invoice: {{ $activePayment->invoice_number }}</p>
                    </div>

                    <div class="mx-auto flex w-fit flex-col items-center rounded-2xl border-2 border-gray-900 bg-white p-5 shadow-sm">
                        @if ($activePayment->payment_url)
                            <img src="{{ $activePayment->payment_url }}" alt="QRIS Midtrans" class="h-56 w-56 object-contain" />
                        @elseif (! empty($activePayment->payload['qr_string']))
                            {!! SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->margin(1)->generate($activePayment->payload['qr_string']) !!}
                        @endif
                        <p class="mt-2 text-xs font-bold tracking-widest text-gray-800">SCAN QRIS UNTUK MEMBAYAR</p>
                    </div>

                    @if ($isSandbox && ($activePayment->payment_url || ! empty($activePayment->payload['qris_url'])))
                        @php($qrisImageUrl = $activePayment->payment_url ?? $activePayment->payload['qris_url'])
                        <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 text-left text-xs dark:border-amber-500/30 dark:bg-amber-500/10">
                            <p class="font-bold text-amber-900 dark:text-amber-300">Panduan Uji Coba Simulator Midtrans Sandbox:</p>
                            <ol class="mt-1 list-decimal space-y-1 ps-4 text-[11px] text-amber-800 dark:text-amber-200">
                                <li>Salin <strong>QR Code Image URL</strong> di bawah ini (atau klik kanan gambar QRIS &rarr; <em>Copy image address</em>):</li>
                            </ol>
                            <input
                                type="text"
                                readonly
                                value="{{ $qrisImageUrl }}"
                                onclick="this.select()"
                                class="mt-1.5 w-full rounded-lg border border-amber-300 bg-white px-2.5 py-1.5 font-mono text-[11px] text-gray-800"
                            />
                            <a
                                href="https://simulator.sandbox.midtrans.com/v2/qris/index"
                                target="_blank"
                                class="mt-2 block w-full rounded-lg bg-amber-600 px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wider text-white hover:bg-amber-500"
                            >
                                Buka Simulator QRIS v2 Midtrans &rarr; Tempel URL &amp; Klik Pay
                            </a>
                        </div>
                    @endif

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Halaman ini otomatis memeriksa status pembayaran setiap beberapa detik dan akan langsung menampilkan struk begitu lunas.
                    </p>

                    <div class="space-y-2 pt-2">
                        <button
                            type="button"
                            wire:click="checkQrisStatus"
                            class="w-full rounded-xl bg-brand-600 px-4 py-3 text-xs font-bold uppercase tracking-wider text-white shadow hover:bg-brand-500"
                        >
                            Cek Status Pembayaran
                        </button>
                    </div>
                </x-card>
            </div>

        @elseif ($step === 'receipt' && $completedSession)
            <!-- STEP 3: TAMPILAN STRUK PEMBAYARAN & SELESAI -->
            @php($latestPayment = $completedSession->payments->sortByDesc('id')->first())
            <div class="mx-auto max-w-md space-y-4">
                <x-card class="p-6 font-mono text-gray-900 dark:text-gray-100">
                    <div class="border-b border-dashed border-gray-400 pb-4 text-center">
                        <span class="inline-block rounded-full bg-green-100 px-3 py-0.5 font-sans text-xs font-bold text-green-800 dark:bg-green-500/20 dark:text-green-300">
                            PEMBAYARAN LUNAS
                        </span>
                        <h2 class="mt-2 text-lg font-bold uppercase">{{ config('app.name') }}</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $completedSession->diningTable->name }} &bull; {{ now()->format('d/m/Y H:i') }}
                        </p>
                        @if ($latestPayment)
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $latestPayment->invoice_number }}</p>
                        @endif
                    </div>

                    <div class="divide-y divide-dashed divide-gray-300 py-3 text-xs dark:divide-gray-700">
                        @foreach ($completedSession->orders as $order)
                            @if ($order->status->value !== 'cancelled')
                                <div class="py-2">
                                    <p class="font-semibold">{{ $order->order_number }}</p>
                                    @foreach ($order->items as $item)
                                        @if ($item->status->value === 'active')
                                            <div class="mt-1 flex justify-between">
                                                <span>{{ $item->quantity }}x {{ $item->item_name }}</span>
                                                <x-rupiah :amount="$item->subtotal" />
                                            </div>
                                            @if ($item->notes)
                                                <p class="text-[11px] text-gray-500">Catatan: {{ $item->notes }}</p>
                                            @endif
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <div class="space-y-1.5 border-t border-dashed border-gray-400 pt-3 text-xs">
                        <div class="flex justify-between text-sm font-bold">
                            <span>TOTAL TAGIHAN</span>
                            <x-rupiah :amount="$completedSession->total_amount" />
                        </div>

                        @if ($latestPayment)
                            <div class="flex justify-between">
                                <span>Metode Pembayaran</span>
                                <span class="font-semibold">{{ $latestPayment->method->label() }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Nominal Dibayar</span>
                                <x-rupiah :amount="$latestPayment->amount_received ?? $latestPayment->amount" />
                            </div>
                            <div class="flex justify-between">
                                <span>Kembalian</span>
                                <x-rupiah :amount="$latestPayment->change_amount" />
                            </div>
                        @endif
                    </div>
                </x-card>

                <div class="grid gap-2 sm:grid-cols-2">
                    <a
                        href="{{ route('cashier.receipts.show', $completedSession) }}"
                        target="_blank"
                        class="flex items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-800 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    >
                        <x-icon name="receipt" class="h-4 w-4" /> Cetak Struk
                    </a>

                    <button
                        type="button"
                        wire:click="startNewOrder"
                        class="flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3 text-xs font-bold uppercase tracking-wider text-white shadow hover:bg-brand-500"
                    >
                        Selesai &amp; Pesanan Baru
                    </button>
                </div>
            </div>

        @else
            <!-- STEP 1: PILIH MENU BERGAMBAR & METODE PEMBAYARAN -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <x-input-error :messages="$errors->get('items')" />

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <x-text-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari menu..." class="w-full sm:max-w-xs" />

                        <div class="flex gap-1.5 overflow-x-auto pb-1">
                            <button
                                type="button"
                                wire:click="$set('selectedCategory', null)"
                                @class([
                                    'shrink-0 rounded-full px-3 py-1 text-xs font-semibold transition',
                                    'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => $selectedCategory === null,
                                    'bg-white text-gray-700 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700' => $selectedCategory !== null,
                                ])
                            >
                                Semua
                            </button>
                            @foreach ($categories as $category)
                                <button
                                    type="button"
                                    wire:click="$set('selectedCategory', {{ $category->id }})"
                                    @class([
                                        'shrink-0 rounded-full px-3 py-1 text-xs font-semibold transition',
                                        'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => $selectedCategory === $category->id,
                                        'bg-white text-gray-700 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700' => $selectedCategory !== $category->id,
                                    ])
                                >
                                    {{ $category->name }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @foreach ($categories as $category)
                        @if (($selectedCategory === null || $selectedCategory === $category->id) && $category->menuItems->isNotEmpty())
                            <div wire:key="manual-cat-{{ $category->id }}" class="space-y-3">
                                <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $category->name }}</h2>
                                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($category->menuItems as $menuItem)
                                        @php($inCartQty = $cart[$menuItem->id]['quantity'] ?? 0)
                                        <x-card wire:key="manual-item-{{ $menuItem->id }}" class="flex flex-col overflow-hidden">
                                            <div class="relative h-36 w-full bg-amber-50 dark:bg-gray-700">
                                                @if ($menuItem->image_url)
                                                    <img src="{{ $menuItem->image_url }}" alt="{{ $menuItem->name }}" class="h-full w-full object-cover" />
                                                @else
                                                    <div class="flex h-full w-full flex-col items-center justify-center text-brand-500 dark:text-brand-400">
                                                        <x-icon name="cup" class="h-12 w-12" />
                                                        <span class="mt-1 text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $category->name }}</span>
                                                    </div>
                                                @endif

                                                @if ($inCartQty > 0)
                                                    <span class="absolute end-2 top-2 rounded-full bg-brand-600 px-2.5 py-0.5 text-xs font-bold text-white shadow">
                                                        {{ $inCartQty }}x dipilih
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="flex flex-1 flex-col justify-between p-3.5">
                                                <div>
                                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $menuItem->name }}</p>
                                                    @if ($menuItem->description)
                                                        <p class="mt-0.5 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{{ $menuItem->description }}</p>
                                                    @endif
                                                </div>

                                                <div class="mt-3 flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
                                                    <span class="text-sm font-bold text-brand-600 dark:text-brand-400">
                                                        <x-rupiah :amount="$menuItem->price" />
                                                    </span>

                                                    @if ($menuItem->is_available)
                                                        @if ($inCartQty > 0)
                                                            <div class="flex items-center gap-1.5">
                                                                <button type="button" wire:click="decrementItem({{ $menuItem->id }})" class="flex h-7 w-7 items-center justify-center rounded-lg border border-gray-300 text-xs font-bold dark:border-gray-600">-</button>
                                                                <span class="w-5 text-center text-xs font-bold">{{ $inCartQty }}</span>
                                                                <button type="button" wire:click="addItem({{ $menuItem->id }})" class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-600 text-xs font-bold text-white">+</button>
                                                            </div>
                                                        @else
                                                            <button type="button" wire:click="addItem({{ $menuItem->id }})" class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-800 dark:bg-white dark:text-gray-900">
                                                                + Tambah
                                                            </button>
                                                        @endif
                                                    @else
                                                        <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-600 dark:bg-red-500/10 dark:text-red-400">Habis</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </x-card>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <!-- PANEL RINGKASAN PESANAN & PILIH METODE PEMBAYARAN -->
                <x-card class="h-fit p-6">
                    <h2 class="text-base font-bold">Ringkasan Pesanan &amp; Pembayaran</h2>

                    <form wire:submit="submitManualOrder" class="mt-4 space-y-4">
                        <div>
                            <x-input-label for="dining_table_id" value="1. Pilih Meja (Wajib)" />
                            <x-select-input wire:model.live="dining_table_id" id="dining_table_id" class="mt-1 block w-full">
                                <option value="">-- Pilih Meja --</option>
                                @foreach ($tables as $table)
                                    <option value="{{ $table->id }}">{{ $table->name }} ({{ $table->status->label() }})</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('dining_table_id')" class="mt-1" />
                        </div>

                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($cart as $menuItemId => $entry)
                                @php($item = $cartMenuItems->get((int) $menuItemId))
                                @if ($item)
                                    <div wire:key="manual-cart-{{ $menuItemId }}" class="py-3 space-y-2">
                                        <div class="flex items-center justify-between text-sm">
                                            <div>
                                                <span class="font-medium">{{ $item->name }}</span>
                                                <p class="text-xs text-gray-500"><x-rupiah :amount="$item->price * $entry['quantity']" /></p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button type="button" wire:click="decrementItem({{ $menuItemId }})" class="h-6 w-6 rounded border border-gray-300 text-xs font-bold dark:border-gray-600">-</button>
                                                <span class="text-xs font-semibold">{{ $entry['quantity'] }}</span>
                                                <button type="button" wire:click="addItem({{ $menuItemId }})" class="h-6 w-6 rounded bg-brand-600 text-xs font-bold text-white">+</button>
                                            </div>
                                        </div>
                                        <input
                                            type="text"
                                            wire:model.blur="cart.{{ $menuItemId }}.notes"
                                            placeholder="Catatan item (opsional)..."
                                            class="w-full rounded border-gray-200 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-900"
                                        />
                                    </div>
                                @endif
                            @empty
                                <p class="py-6 text-center text-xs text-gray-400">Belum ada menu dipilih.</p>
                            @endforelse
                        </div>

                        <div class="space-y-1 border-t border-gray-200 pt-3 text-sm dark:border-gray-700">
                            @if ($existingSessionTotal > 0)
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Tagihan Sesi Sebelumnya</span>
                                    <x-rupiah :amount="$existingSessionTotal" />
                                </div>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Pesanan Baru</span>
                                    <x-rupiah :amount="$cartTotal" />
                                </div>
                            @endif
                            <div class="flex items-center justify-between text-base font-bold">
                                <span>Total Bayar</span>
                                <span class="text-brand-600 dark:text-brand-400"><x-rupiah :amount="$grandTotal" /></span>
                            </div>
                        </div>

                        <!-- PILIH METODE PEMBAYARAN -->
                        <div class="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                            <x-input-label value="2. Pilih Metode Pembayaran" />

                            <div class="grid grid-cols-2 gap-2">
                                <label @class([
                                    'flex cursor-pointer flex-col items-center justify-center rounded-xl border p-3 text-center text-xs font-bold transition',
                                    'border-brand-600 bg-brand-50 text-brand-700 ring-1 ring-brand-600 dark:bg-brand-500/10 dark:text-brand-300' => $payment_method === 'cash',
                                    'border-gray-200 text-gray-700 dark:border-gray-700 dark:text-gray-300' => $payment_method !== 'cash',
                                ])>
                                    <input type="radio" wire:model.live="payment_method" value="cash" class="sr-only" />
                                    <span>Tunai (Cash)</span>
                                    <span class="mt-0.5 text-[10px] font-normal text-gray-500">Langsung Cetak Struk</span>
                                </label>

                                <label @class([
                                    'flex cursor-pointer flex-col items-center justify-center rounded-xl border p-3 text-center text-xs font-bold transition',
                                    'border-brand-600 bg-brand-50 text-brand-700 ring-1 ring-brand-600 dark:bg-brand-500/10 dark:text-brand-300' => $payment_method === 'qris',
                                    'border-gray-200 text-gray-700 dark:border-gray-700 dark:text-gray-300' => $payment_method !== 'qris',
                                ])>
                                    <input type="radio" wire:model.live="payment_method" value="qris" class="sr-only" />
                                    <span>QRIS (Midtrans)</span>
                                    <span class="mt-0.5 text-[10px] font-normal text-gray-500">Tampilkan Kode QRIS</span>
                                </label>
                            </div>

                            @if ($payment_method === 'cash')
                                <div class="space-y-2 rounded-xl bg-gray-50 p-3 dark:bg-gray-900/50">
                                    <div>
                                        <x-input-label for="amount_received" value="Uang Tunai Diterima (Rp)" />
                                        <x-text-input wire:model.live="amount_received" id="amount_received" type="number" min="0" step="1000" class="mt-1 block w-full text-sm" required />
                                        <x-input-error :messages="$errors->get('amount_received')" class="mt-1" />
                                    </div>

                                    @if ($grandTotal > 0)
                                        <div class="flex flex-wrap gap-1.5">
                                            <button type="button" wire:click="setExactCash({{ $grandTotal }})" class="rounded border border-gray-300 bg-white px-2 py-1 text-[11px] font-semibold hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                                                Uang Pas
                                            </button>
                                            @foreach ([50000, 100000] as $quickCash)
                                                @if ($quickCash >= $grandTotal)
                                                    <button type="button" wire:click="setExactCash({{ $quickCash }})" class="rounded border border-gray-300 bg-white px-2 py-1 text-[11px] font-semibold hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                                                        <x-rupiah :amount="$quickCash" />
                                                    </button>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="flex justify-between border-t border-gray-200 pt-2 text-xs font-semibold dark:border-gray-700">
                                        <span>Kembalian</span>
                                        <span class="text-emerald-600 dark:text-emerald-400"><x-rupiah :amount="$estimatedChange" /></span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <x-primary-button class="w-full justify-center py-3">
                            {{ $payment_method === 'qris' ? 'Proses Pesanan & Tampilkan QRIS' : 'Bayar Tunai & Tampilkan Struk' }}
                        </x-primary-button>
                    </form>
                </x-card>
            </div>
        @endif
    </div>
</div>
