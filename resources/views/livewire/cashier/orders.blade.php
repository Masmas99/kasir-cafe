<?php

use App\Actions\CancelOrderItem;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Pesanan Masuk')] class extends Component
{
    #[Url]
    public string $statusFilter = '';

    public ?int $cancellingItemId = null;

    public ?int $cancellingOrderId = null;

    public string $cancellationReason = '';

    public int $lastHighestOrderId = 0;

    /**
     * Mount the component and record the latest order ID for new-order notifications.
     */
    public function mount(): void
    {
        $this->lastHighestOrderId = (int) Order::max('id');
    }

    /**
     * Update an order's preparation status.
     */
    public function updateStatus(Order $order, string $newStatus): void
    {
        $validated = validator(
            ['status' => $newStatus],
            ['status' => ['required', Rule::enum(OrderStatus::class)]],
        )->validate();

        $order->update(['status' => $validated['status']]);

        session()->flash('status', "Status {$order->order_number} diubah menjadi ".OrderStatus::from($validated['status'])->label().'.');
    }

    /**
     * Prompt for a cancellation reason for a single order item.
     */
    public function promptCancelItem(int $orderItemId): void
    {
        $this->cancellingOrderId = null;
        $this->cancellingItemId = $orderItemId;
        $this->cancellationReason = '';
        $this->resetValidation();
    }

    /**
     * Prompt for a cancellation reason for an entire order.
     */
    public function promptCancelOrder(int $orderId): void
    {
        $this->cancellingItemId = null;
        $this->cancellingOrderId = $orderId;
        $this->cancellationReason = '';
        $this->resetValidation();
    }

    /**
     * Confirm and execute the item or order cancellation.
     */
    public function confirmCancellation(CancelOrderItem $cancelOrderItem): void
    {
        $this->validate([
            'cancellationReason' => ['required', 'string', 'max:255'],
        ], [
            'cancellationReason.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        if ($this->cancellingItemId !== null) {
            $item = OrderItem::findOrFail($this->cancellingItemId);
            $cancelOrderItem->cancelItem($item, Auth::user(), $this->cancellationReason);
            session()->flash('status', "Item {$item->item_name} dibatalkan.");
        } elseif ($this->cancellingOrderId !== null) {
            $order = Order::findOrFail($this->cancellingOrderId);
            $cancelOrderItem->cancelOrder($order, Auth::user(), $this->cancellationReason);
            session()->flash('status', "Pesanan {$order->order_number} dibatalkan.");
        }

        $this->closeCancelPrompt();
    }

    /**
     * Close the cancellation modal/prompt.
     */
    public function closeCancelPrompt(): void
    {
        $this->reset('cancellingItemId', 'cancellingOrderId', 'cancellationReason');
        $this->resetValidation();
    }

    /**
     * Get the data passed to the view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $currentHighestId = (int) Order::max('id');
        if ($currentHighestId > $this->lastHighestOrderId) {
            $this->dispatch('new-order-arrived');
            $this->lastHighestOrderId = $currentHighestId;
        }

        $orders = Order::with(['diningTable', 'tableSession', 'items'])
            ->whereHas('tableSession', fn ($query) => $query->whereNull('closed_at'))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->latest()
            ->get();

        return [
            'orders' => $orders,
            'statuses' => OrderStatus::cases(),
            'ItemStatus' => OrderItemStatus::class,
        ];
    }
}; ?>

<div
    wire:poll.5s
    x-data
    x-on:new-order-arrived.window="
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.value = 0.1;
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.25);
        } catch (e) {}
    "
>
    <x-page-header title="Pesanan Masuk" description="Pantau pesanan dari QR meja maupun input manual secara real-time.">
        <x-slot name="actions">
            <a href="{{ route('cashier.manual-order') }}" wire:navigate class="inline-flex items-center gap-2 rounded-md bg-brand-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-brand-500">
                + Pesanan Manual
            </a>
        </x-slot>
    </x-page-header>

    <div class="space-y-4 p-4 sm:p-6 lg:p-8">
        <x-flash-message />

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                wire:click="$set('statusFilter', '')"
                @class([
                    'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                    'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => $statusFilter === '',
                    'bg-white text-gray-700 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700' => $statusFilter !== '',
                ])
            >
                Semua Status
            </button>
            @foreach ($statuses as $statusOption)
                <button
                    type="button"
                    wire:click="$set('statusFilter', '{{ $statusOption->value }}')"
                    @class([
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                        'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => $statusFilter === $statusOption->value,
                        'bg-white text-gray-700 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700' => $statusFilter !== $statusOption->value,
                    ])
                >
                    {{ $statusOption->label() }}
                </button>
            @endforeach
        </div>

        @if ($cancellingItemId !== null || $cancellingOrderId !== null)
            <x-card class="border-red-300 p-4 dark:border-red-500/40">
                <h3 class="text-sm font-semibold text-red-700 dark:text-red-400">
                    {{ $cancellingItemId ? 'Batalkan Item Pesanan' : 'Batalkan Seluruh Pesanan' }}
                </h3>
                <form wire:submit="confirmCancellation" class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-start">
                    <div class="flex-1">
                        <x-text-input wire:model="cancellationReason" placeholder="Alasan pembatalan (wajib, misal: stok habis)..." class="w-full text-sm" required />
                        <x-input-error :messages="$errors->get('cancellationReason')" class="mt-1" />
                        <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                    </div>
                    <div class="flex gap-2">
                        <x-danger-button>Konfirmasi Batal</x-danger-button>
                        <x-secondary-button wire:click="closeCancelPrompt">Tutup</x-secondary-button>
                    </div>
                </form>
            </x-card>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($orders as $order)
                <x-card wire:key="cashier-order-{{ $order->id }}" class="flex flex-col p-4">
                    <div class="flex items-start justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-base font-bold">{{ $order->diningTable->name }}</span>
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium uppercase text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                    {{ $order->source }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500">{{ $order->order_number }} &bull; {{ $order->created_at->format('H:i') }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $order->status->badgeClasses() }}">
                            {{ $order->status->label() }}
                        </span>
                    </div>

                    <ul class="my-3 flex-1 space-y-2 text-sm">
                        @foreach ($order->items as $item)
                            <li class="flex items-start justify-between gap-2">
                                <div>
                                    <span @class(['font-medium', 'line-through text-gray-400' => $item->status === $ItemStatus::Cancelled])>
                                        {{ $item->quantity }}x {{ $item->item_name }}
                                    </span>
                                    @if ($item->notes)
                                        <p class="text-xs text-amber-600 dark:text-amber-400">Catatan: {{ $item->notes }}</p>
                                    @endif
                                    @if ($item->status === $ItemStatus::Cancelled && $item->cancellation_reason)
                                        <p class="text-xs text-red-600 dark:text-red-400">Alasan: {{ $item->cancellation_reason }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-600 dark:text-gray-400"><x-rupiah :amount="$item->subtotal" /></span>
                                    @if ($item->status === $ItemStatus::Active && ! $order->tableSession->isPaid())
                                        <button type="button" wire:click="promptCancelItem({{ $item->id }})" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">
                                            Batal
                                        </button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="border-t border-gray-100 pt-3 dark:border-gray-700">
                        <div class="mb-3 flex items-center justify-between text-sm font-semibold">
                            <span>Total Pesanan</span>
                            <x-rupiah :amount="$order->total_amount" />
                        </div>

                        @if ($order->status->value !== 'cancelled')
                            <div class="flex flex-wrap gap-1.5">
                                @foreach (['pending' => 'Menunggu', 'processing' => 'Diproses', 'ready' => 'Siap', 'served' => 'Disajikan'] as $stKey => $stLabel)
                                    <button
                                        type="button"
                                        wire:click="updateStatus({{ $order->id }}, '{{ $stKey }}')"
                                        @class([
                                            'rounded-md px-2.5 py-1 text-xs font-semibold transition',
                                            'bg-brand-600 text-white' => $order->status->value === $stKey,
                                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300' => $order->status->value !== $stKey,
                                        ])
                                    >
                                        {{ $stLabel }}
                                    </button>
                                @endforeach

                                @if (! $order->tableSession->isPaid())
                                    <button
                                        type="button"
                                        wire:click="promptCancelOrder({{ $order->id }})"
                                        class="ms-auto rounded-md bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 dark:bg-red-500/10 dark:text-red-400"
                                    >
                                        Batalkan Pesanan
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </x-card>
            @empty
                <x-card class="p-8 text-center text-sm text-gray-500 dark:text-gray-400 md:col-span-2 xl:col-span-3">
                    Belum ada pesanan masuk pada sesi aktif.
                </x-card>
            @endforelse
        </div>
    </div>
</div>
