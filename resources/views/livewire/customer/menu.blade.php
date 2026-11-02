<?php

use App\Actions\CreateMidtransQrisPayment;
use App\Actions\PlaceOrder;
use App\Exceptions\TableNotAcceptingOrdersException;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Services\Midtrans\MidtransClient;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.customer')] class extends Component
{
    public DiningTable $diningTable;

    public string $search = '';

    public ?int $selectedCategory = null;

    /**
     * Cart items keyed by menu_item_id.
     *
     * @var array<int, array{quantity: int, notes: string}>
     */
    public array $cart = [];

    public bool $checkoutOpen = false;

    public string $customer_name = '';

    public string $customer_phone = '';

    public ?string $errorMessage = null;

    /**
     * Mount the component with the scanned dining table.
     */
    public function mount(DiningTable $diningTable): void
    {
        $this->diningTable = $diningTable;

        $activeSession = $diningTable->activeSession;
        if ($activeSession) {
            $this->customer_name = (string) ($activeSession->customer_name ?? '');
            $this->customer_phone = (string) ($activeSession->customer_phone ?? '');
        }
    }

    /**
     * Increment a menu item in the cart if it is available.
     */
    public function addToCart(int $menuItemId): void
    {
        $this->errorMessage = null;

        $menuItem = MenuItem::find($menuItemId);

        if ($menuItem === null || ! $menuItem->is_available) {
            $this->errorMessage = 'Menu ini sedang habis dan tidak dapat ditambahkan.';

            return;
        }

        $current = $this->cart[$menuItemId] ?? ['quantity' => 0, 'notes' => ''];
        $current['quantity']++;
        $this->cart[$menuItemId] = $current;

        $this->dispatch('item-added-to-cart', name: $menuItem->name);
    }

    /**
     * Decrement or remove a menu item from the cart.
     */
    public function decrementFromCart(int $menuItemId): void
    {
        if (! isset($this->cart[$menuItemId])) {
            return;
        }

        $this->cart[$menuItemId]['quantity']--;

        if ($this->cart[$menuItemId]['quantity'] <= 0) {
            unset($this->cart[$menuItemId]);
        }

        if ($this->cart === []) {
            $this->checkoutOpen = false;
        }
    }

    /**
     * Open the customer info & cart review sheet.
     */
    public function openCheckout(): void
    {
        if ($this->cart === []) {
            return;
        }

        $this->resetValidation();
        $this->checkoutOpen = true;
    }

    /**
     * Close the customer info & cart review sheet.
     */
    public function closeCheckout(): void
    {
        $this->checkoutOpen = false;
    }

    /**
     * Validate customer name & WhatsApp number, place the order, and redirect to Order Detail & Payment page.
     */
    public function proceedToOrderDetail(PlaceOrder $placeOrder): void
    {
        $this->validate([
            'customer_name' => ['required', 'string', 'min:2', 'max:100'],
            'customer_phone' => ['required', 'string', 'min:8', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
        ], [
            'customer_name.required' => 'Nama pemesan wajib diisi.',
            'customer_name.min' => 'Nama pemesan minimal 2 karakter.',
            'customer_phone.required' => 'Nomor WhatsApp wajib diisi.',
            'customer_phone.min' => 'Nomor WhatsApp minimal 8 digit.',
            'customer_phone.regex' => 'Format nomor WhatsApp tidak valid.',
        ]);

        $this->processCustomerOrder($placeOrder, payWithQris: false);
    }

    /**
     * Submit the customer's cart as a direct order for this table.
     */
    public function submitOrder(PlaceOrder $placeOrder): void
    {
        $this->processCustomerOrder($placeOrder, payWithQris: false);
    }

    /**
     * Submit the customer's cart and immediately generate a Midtrans QRIS payment.
     */
    public function submitOrderWithQris(
        PlaceOrder $placeOrder,
        CreateMidtransQrisPayment $createMidtransQrisPayment,
        MidtransClient $midtransClient,
    ): void {
        $this->processCustomerOrder($placeOrder, payWithQris: true, createMidtransQrisPayment: $createMidtransQrisPayment, midtransClient: $midtransClient);
    }

    /**
     * Internal handler to place the customer order and optionally trigger QRIS charge.
     */
    protected function processCustomerOrder(
        PlaceOrder $placeOrder,
        bool $payWithQris = false,
        ?CreateMidtransQrisPayment $createMidtransQrisPayment = null,
        ?MidtransClient $midtransClient = null,
    ): void {
        $this->errorMessage = null;
        $this->diningTable->refresh();

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

        try {
            $order = $placeOrder->handle(
                $this->diningTable,
                $items,
                placedBy: null,
                customerName: $this->customer_name,
                customerPhone: $this->customer_phone,
            );
        } catch (TableNotAcceptingOrdersException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        if ($payWithQris && $createMidtransQrisPayment && $midtransClient) {
            try {
                $createMidtransQrisPayment->handle($order->tableSession, $midtransClient);
            } catch (\Throwable $e) {
                session()->flash('error', $e->getMessage());
            }
        }

        $this->cart = [];
        $this->checkoutOpen = false;

        session()->flash('status', 'Pesanan berhasil dibuat! Silakan periksa detail pesanan dan pilih metode pembayaran di bawah ini.');

        $this->redirect(route('customer.session', [
            'diningTable' => $this->diningTable->code,
            'tableSession' => $order->tableSession->token,
        ]), navigate: true);
    }

    /**
     * Get the data passed to the view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $this->diningTable->refresh();

        $categories = Category::with([
            'menuItems' => fn ($query) => $query
                ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                ->orderBy('name'),
        ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $cartIds = array_map('intval', array_keys($this->cart));
        $cartMenuItems = $cartIds !== []
            ? MenuItem::whereIn('id', $cartIds)->get()->keyBy('id')
            : collect();

        $cartTotal = 0;
        $cartCount = 0;

        foreach ($this->cart as $id => $entry) {
            $item = $cartMenuItems->get((int) $id);
            if ($item) {
                $qty = (int) ($entry['quantity'] ?? 0);
                $cartCount += $qty;
                $cartTotal += $item->price * $qty;
            }
        }

        return [
            'categories' => $categories,
            'cartMenuItems' => $cartMenuItems,
            'cartTotal' => $cartTotal,
            'cartCount' => $cartCount,
            'activeSession' => $this->diningTable->activeSession,
            'isTableBlocked' => ! $this->diningTable->is_active,
        ];
    }
}; ?>

<div
    x-data="{
        cartBounce: false,
        toastText: '',
        toastVisible: false,
        toastTimer: null,
        flyItems: [],
        triggerFly(event, itemName) {
            const rect = event.currentTarget.getBoundingClientRect();
            const startX = rect.left + rect.width / 2;
            const startY = rect.top + rect.height / 2;

            const cartIcon = document.getElementById('customer-cart-target');
            let endX = window.innerWidth / 2;
            let endY = window.innerHeight - 48;

            if (cartIcon) {
                const cartRect = cartIcon.getBoundingClientRect();
                endX = cartRect.left + cartRect.width / 2;
                endY = cartRect.top + cartRect.height / 2;
            }

            const id = Date.now() + Math.random();
            this.flyItems.push({ id, x: startX, y: startY, scale: 1, opacity: 1 });

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    const item = this.flyItems.find(f => f.id === id);
                    if (item) {
                        item.x = endX;
                        item.y = endY;
                        item.scale = 0.35;
                        item.opacity = 0.2;
                    }
                });
            });

            setTimeout(() => {
                this.flyItems = this.flyItems.filter(f => f.id !== id);
                this.cartBounce = true;
                setTimeout(() => { this.cartBounce = false; }, 350);
            }, 520);

            this.toastText = '+1 ' + itemName + ' masuk keranjang';
            this.toastVisible = true;
            if (this.toastTimer) clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => { this.toastVisible = false; }, 1800);
        }
    }"
    class="pb-36"
>
    <!-- Flying Add-to-Cart Particles -->
    <template x-for="fly in flyItems" :key="fly.id">
        <div
            class="pointer-events-none fixed z-50 flex h-8 w-8 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-brand-600 text-xs font-extrabold text-white shadow-lg transition-all duration-500 ease-in-out"
            :style="`left: ${fly.x}px; top: ${fly.y}px; transform: translate(-50%, -50%) scale(${fly.scale}); opacity: ${fly.opacity};`"
        >
            +1
        </div>
    </template>

    <!-- Floating Toast Notification -->
    <div
        x-show="toastVisible"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-3"
        style="display: none;"
        class="pointer-events-none fixed inset-x-0 top-16 z-40 mx-auto flex max-w-xs items-center justify-center gap-2 rounded-full bg-gray-900/95 px-4 py-2 text-xs font-semibold text-white shadow-lg dark:bg-brand-600"
    >
        <x-icon name="cart" class="h-4 w-4 shrink-0 text-brand-400 dark:text-white" />
        <span x-text="toastText" class="truncate"></span>
    </div>

    <!-- Top Sticky Header with Table Identity -->
    <header class="sticky top-0 z-20 border-b border-gray-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <a href="{{ url('/') }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800" title="Ganti Meja">
                    &larr;
                </a>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ config('app.name') }}</p>
                    <h1 class="text-lg font-bold text-gray-900 dark:text-white">{{ $diningTable->name }}</h1>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if ($activeSession && ! $isTableBlocked)
                    <a href="{{ route('customer.session', ['diningTable' => $diningTable->code, 'tableSession' => $activeSession->token]) }}" wire:navigate class="rounded-lg bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                        Detail Pesanan
                    </a>
                @endif
                <span class="rounded-full bg-brand-100 px-3 py-1 text-xs font-semibold text-brand-800 dark:bg-brand-500/20 dark:text-brand-300">
                    Dine-In
                </span>
                <x-theme-toggle />
            </div>
        </div>

        <!-- Stepper Indicator -->
        <div class="mt-3 flex items-center justify-between rounded-xl bg-gray-100 px-3 py-1.5 text-[11px] font-semibold dark:bg-gray-800">
            <span @class(['text-brand-600 dark:text-brand-400 font-bold' => ! $checkoutOpen, 'text-gray-500 dark:text-gray-400' => $checkoutOpen])>
                1. Pilih Menu
            </span>
            <span class="text-gray-300 dark:text-gray-600">&rarr;</span>
            <span @class(['text-brand-600 dark:text-brand-400 font-bold' => $checkoutOpen, 'text-gray-500 dark:text-gray-400' => ! $checkoutOpen])>
                2. Isi Nama &amp; WA
            </span>
            <span class="text-gray-300 dark:text-gray-600">&rarr;</span>
            <span class="text-gray-400 dark:text-gray-500">
                3. Detail &amp; Bayar
            </span>
        </div>

        @if (! $isTableBlocked)
            <div class="mt-3">
                <x-text-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari menu makanan atau minuman..." class="w-full text-xs" />
            </div>
        @endif

        @if ($categories->isNotEmpty() && ! $isTableBlocked)
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                <button
                    type="button"
                    wire:click="$set('selectedCategory', null)"
                    @class([
                        'shrink-0 rounded-full px-3 py-1 text-xs font-medium transition',
                        'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => $selectedCategory === null,
                        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => $selectedCategory !== null,
                    ])
                >
                    Semua
                </button>
                @foreach ($categories as $category)
                    <button
                        type="button"
                        wire:click="$set('selectedCategory', {{ $category->id }})"
                        @class([
                            'shrink-0 rounded-full px-3 py-1 text-xs font-medium transition',
                            'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => $selectedCategory === $category->id,
                            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => $selectedCategory !== $category->id,
                        ])
                    >
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>
        @endif
    </header>

    <div class="space-y-6 p-4">
        @if ($errorMessage)
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                {{ $errorMessage }}
            </div>
        @endif

        @error('items')
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                {{ $message }}
            </div>
        @enderror

        @if (! $diningTable->is_active)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center dark:border-amber-500/30 dark:bg-amber-500/10">
                <h2 class="text-base font-semibold text-amber-900 dark:text-amber-200">Meja Sedang Tidak Aktif</h2>
                <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">Silakan hubungi kasir untuk bantuan pemesanan.</p>
            </div>
        @else
            @foreach ($categories as $category)
                @if ($selectedCategory === null || $selectedCategory === $category->id)
                    <section wire:key="cat-{{ $category->id }}" class="space-y-3">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $category->name }}</h2>

                        <div class="space-y-3">
                            @foreach ($category->menuItems as $menuItem)
                                @php($qty = $cart[$menuItem->id]['quantity'] ?? 0)
                                <div
                                    wire:key="item-{{ $menuItem->id }}"
                                    @class([
                                        'rounded-xl border p-3 transition-all duration-200',
                                        'border-brand-500 bg-brand-50/30 ring-1 ring-brand-500/30 dark:border-brand-500/60 dark:bg-brand-500/5' => $qty > 0,
                                        'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-800/40' => $qty === 0,
                                    ])
                                >
                                    <div class="flex gap-3">
                                        @if ($menuItem->image_url)
                                            <img src="{{ $menuItem->image_url }}" alt="{{ $menuItem->name }}" class="h-20 w-20 shrink-0 rounded-lg object-cover" />
                                        @else
                                            <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-400 dark:bg-gray-800">
                                                <x-icon name="cup" class="h-8 w-8" />
                                            </div>
                                        @endif

                                        <div class="flex flex-1 flex-col justify-between">
                                            <div>
                                                <div class="flex items-start justify-between gap-2">
                                                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $menuItem->name }}</h3>
                                                    @if ($qty > 0)
                                                        <span class="shrink-0 rounded-full bg-brand-600 px-2 py-0.5 text-[11px] font-bold text-white">
                                                            {{ $qty }}x di keranjang
                                                        </span>
                                                    @elseif (! $menuItem->is_available)
                                                        <span class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-500/10 dark:text-red-400">Habis</span>
                                                    @endif
                                                </div>
                                                @if ($menuItem->description)
                                                    <p class="mt-0.5 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{{ $menuItem->description }}</p>
                                                @endif
                                            </div>

                                            <div class="mt-2 flex items-center justify-between">
                                                <span class="text-sm font-bold text-brand-600 dark:text-brand-400"><x-rupiah :amount="$menuItem->price" /></span>

                                                @if ($menuItem->is_available)
                                                    @if ($qty > 0)
                                                        <div class="flex items-center gap-2">
                                                            <button
                                                                type="button"
                                                                wire:click="decrementFromCart({{ $menuItem->id }})"
                                                                class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-300 bg-white text-sm font-bold text-gray-700 active:scale-90 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                                            >
                                                                -
                                                            </button>
                                                            <span class="w-5 text-center text-sm font-bold text-gray-900 dark:text-white">{{ $qty }}</span>
                                                            <button
                                                                type="button"
                                                                x-on:click="triggerFly($event, @js($menuItem->name))"
                                                                wire:click="addToCart({{ $menuItem->id }})"
                                                                class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white shadow transition active:scale-90 hover:bg-brand-500"
                                                            >
                                                                +
                                                            </button>
                                                        </div>
                                                    @else
                                                        <button
                                                            type="button"
                                                            x-on:click="triggerFly($event, @js($menuItem->name))"
                                                            wire:click="addToCart({{ $menuItem->id }})"
                                                            class="inline-flex items-center gap-1 rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition active:scale-95 hover:bg-gray-800 dark:bg-white dark:text-gray-900"
                                                        >
                                                            + Tambah
                                                        </button>
                                                    @endif
                                                @else
                                                    <button type="button" disabled class="cursor-not-allowed rounded-lg bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-400 dark:bg-gray-800 dark:text-gray-600">
                                                        Tidak Tersedia
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    @if ($qty > 0)
                                        <div class="mt-2 border-t border-gray-100 pt-2 dark:border-gray-700">
                                            <input
                                                type="text"
                                                wire:model.blur="cart.{{ $menuItem->id }}.notes"
                                                placeholder="Catatan item (misal: less sugar, tanpa es)..."
                                                class="w-full rounded-lg border-gray-200 bg-white px-2.5 py-1.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                            />
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
        @endif
    </div>

    <!-- MODAL / SHEET TAHAP 2: KERANJANG & ISI NAMA + NOMOR WHATSAPP -->
    @if ($checkoutOpen && $cartCount > 0)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-gray-900/60 backdrop-blur-xs sm:items-center sm:p-4">
            <div class="flex max-h-[90vh] w-full max-w-lg flex-col rounded-t-3xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900 sm:rounded-3xl">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-100 text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">
                            <x-icon name="cart" class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-gray-900 dark:text-white">Keranjang &amp; Data Pemesan</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $diningTable->name }} &bull; {{ $cartCount }} item dipilih</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        wire:click="closeCheckout"
                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    >
                        &#10005;
                    </button>
                </div>

                <!-- Modal Body -->
                <form wire:submit="proceedToOrderDetail" class="flex flex-1 flex-col overflow-hidden">
                    <div class="flex-1 space-y-5 overflow-y-auto p-5">
                        <!-- Form Isi Nama & Nomor WhatsApp -->
                        <div class="space-y-3 rounded-2xl border border-brand-200 bg-brand-50/50 p-4 dark:border-brand-500/30 dark:bg-brand-500/5">
                            <p class="text-xs font-bold uppercase tracking-wider text-brand-700 dark:text-brand-300">
                                Data Pemesan (Wajib Diisi)
                            </p>

                            <div>
                                <x-input-label for="customer_name" value="Nama Pemesan" />
                                <x-text-input
                                    wire:model="customer_name"
                                    id="customer_name"
                                    type="text"
                                    placeholder="Masukkan nama Anda..."
                                    class="mt-1 block w-full text-sm"
                                    required
                                />
                                <x-input-error :messages="$errors->get('customer_name')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label for="customer_phone" value="Nomor WhatsApp" />
                                <x-text-input
                                    wire:model="customer_phone"
                                    id="customer_phone"
                                    type="tel"
                                    placeholder="Contoh: 081234567890"
                                    class="mt-1 block w-full text-sm"
                                    required
                                />
                                <x-input-error :messages="$errors->get('customer_phone')" class="mt-1" />
                            </div>
                        </div>

                        <!-- Daftar Item di Keranjang -->
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Item di Keranjang</h3>
                                <button type="button" wire:click="closeCheckout" class="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-400">
                                    + Tambah Menu Lain
                                </button>
                            </div>

                            <div class="divide-y divide-gray-200 rounded-2xl border border-gray-200 bg-white px-3.5 dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-800/40">
                                @foreach ($cart as $menuItemId => $entry)
                                    @php($item = $cartMenuItems->get((int) $menuItemId))
                                    @if ($item)
                                        <div wire:key="checkout-item-{{ $menuItemId }}" class="py-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $item->name }}</p>
                                                    <p class="text-xs font-medium text-brand-600 dark:text-brand-400">
                                                        <x-rupiah :amount="$item->price * $entry['quantity']" />
                                                        <span class="text-gray-400">({{ $entry['quantity'] }} x <x-rupiah :amount="$item->price" />)</span>
                                                    </p>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        wire:click="decrementFromCart({{ $menuItemId }})"
                                                        class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-300 text-xs font-bold text-gray-700 dark:border-gray-600 dark:text-gray-200"
                                                    >
                                                        -
                                                    </button>
                                                    <span class="w-5 text-center text-xs font-bold text-gray-900 dark:text-white">{{ $entry['quantity'] }}</span>
                                                    <button
                                                        type="button"
                                                        wire:click="addToCart({{ $menuItemId }})"
                                                        class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white"
                                                    >
                                                        +
                                                    </button>
                                                </div>
                                            </div>
                                            <input
                                                type="text"
                                                wire:model.blur="cart.{{ $menuItemId }}.notes"
                                                placeholder="Catatan item (opsional)..."
                                                class="mt-2 w-full rounded-lg border-gray-200 bg-gray-50 px-2.5 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                                            />
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Pesanan ({{ $cartCount }} item)</span>
                            <span class="text-lg font-extrabold text-brand-600 dark:text-brand-400"><x-rupiah :amount="$cartTotal" /></span>
                        </div>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3.5 text-xs font-bold uppercase tracking-wider text-white shadow-lg transition hover:bg-brand-500 disabled:opacity-50"
                        >
                            <span>Lanjut ke Detail Pesanan &amp; Pembayaran</span>
                            <span>&rarr;</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- FLOATING BOTTOM CART BAR -->
    @if ($cartCount > 0 && ! $isTableBlocked)
        <div class="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-lg border-t border-gray-200 bg-white/95 p-3.5 shadow-2xl backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
            <div class="flex items-center justify-between gap-3">
                <button
                    type="button"
                    wire:click="openCheckout"
                    class="flex flex-1 items-center gap-3 text-left"
                >
                    <div
                        id="customer-cart-target"
                        :class="cartBounce ? 'scale-125 -translate-y-1 bg-brand-500' : 'scale-100 bg-brand-600'"
                        class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-md transition-all duration-300"
                    >
                        <x-icon name="cart" class="h-6 w-6" />
                        <span class="absolute -end-1.5 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-gray-900 px-1.5 text-[11px] font-extrabold text-white ring-2 ring-white dark:bg-white dark:text-gray-900 dark:ring-gray-900">
                            {{ $cartCount }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $cartCount }} menu di keranjang</p>
                        <p class="text-base font-extrabold text-gray-900 dark:text-white"><x-rupiah :amount="$cartTotal" /></p>
                    </div>
                </button>

                <button
                    type="button"
                    wire:click="openCheckout"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-3.5 text-xs font-bold uppercase tracking-wider text-white shadow-md transition hover:bg-brand-500 active:scale-95"
                >
                    <span>Keranjang &amp; Pesan</span>
                    <span>&rarr;</span>
                </button>
            </div>
        </div>
    @endif
</div>
