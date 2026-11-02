<?php

use App\Actions\PlaceOrder;
use App\Enums\TableStatus;
use App\Exceptions\TableNotAcceptingOrdersException;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.customer')] class extends Component
{
    public DiningTable $diningTable;

    public ?int $selectedCategory = null;

    /**
     * Cart items keyed by menu_item_id.
     *
     * @var array<int, array{quantity: int, notes: string}>
     */
    public array $cart = [];

    public ?string $errorMessage = null;

    /**
     * Mount the component with the scanned dining table.
     */
    public function mount(DiningTable $diningTable): void
    {
        $this->diningTable = $diningTable;
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
    }

    /**
     * Submit the customer's cart as a direct order for this table.
     */
    public function submitOrder(PlaceOrder $placeOrder): void
    {
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
            $order = $placeOrder->handle($this->diningTable, $items);
        } catch (TableNotAcceptingOrdersException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->cart = [];

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

        $categories = Category::with(['menuItems' => fn ($query) => $query->orderBy('name')])
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

<div class="pb-32">
    <!-- Top Sticky Header with Table Identity -->
    <header class="sticky top-0 z-20 border-b border-gray-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ config('app.name') }}</p>
                <h1 class="text-lg font-bold text-gray-900 dark:text-white">{{ $diningTable->name }}</h1>
            </div>

            <div class="flex items-center gap-2">
                @if ($activeSession && ! $isTableBlocked)
                    <a href="{{ route('customer.session', ['diningTable' => $diningTable->code, 'tableSession' => $activeSession->token]) }}" wire:navigate class="rounded-lg bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                        Lihat Pesanan Aktif
                    </a>
                @endif
                <span class="rounded-full bg-brand-100 px-3 py-1 text-xs font-semibold text-brand-800 dark:bg-brand-500/20 dark:text-brand-300">
                    Dine-In
                </span>
            </div>
        </div>

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
                                <div wire:key="item-{{ $menuItem->id }}" class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
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
                                                    @unless ($menuItem->is_available)
                                                        <span class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-500/10 dark:text-red-400">Habis</span>
                                                    @endunless
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
                                                            <button type="button" wire:click="decrementFromCart({{ $menuItem->id }})" class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-300 text-sm font-bold dark:border-gray-700">-</button>
                                                            <span class="w-5 text-center text-sm font-semibold">{{ $qty }}</span>
                                                            <button type="button" wire:click="addToCart({{ $menuItem->id }})" class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">+</button>
                                                        </div>
                                                    @else
                                                        <button type="button" wire:click="addToCart({{ $menuItem->id }})" class="rounded-lg bg-gray-900 px-3 py-1 text-xs font-semibold text-white dark:bg-white dark:text-gray-900">
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
                                        <div class="mt-2 border-t border-gray-100 pt-2 dark:border-gray-800">
                                            <input
                                                type="text"
                                                wire:model.blur="cart.{{ $menuItem->id }}.notes"
                                                placeholder="Catatan item (misal: less sugar, tanpa es)..."
                                                class="w-full rounded-lg border-gray-200 px-2.5 py-1.5 text-xs focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
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

    @if ($cartCount > 0 && ! $isTableBlocked)
        <div class="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-lg border-t border-gray-200 bg-white p-4 shadow-lg dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">{{ $cartCount }} item dipilih</span>
                <span class="text-base font-bold text-gray-900 dark:text-white"><x-rupiah :amount="$cartTotal" /></span>
            </div>
            <button
                type="button"
                wire:click="submitOrder"
                wire:loading.attr="disabled"
                class="w-full rounded-xl bg-brand-600 py-3 text-sm font-bold text-white shadow hover:bg-brand-500 disabled:opacity-50"
            >
                Kirim Pesanan ke Kasir
            </button>
        </div>
    @endif
</div>
