<?php

namespace App\Actions;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Exceptions\TableNotAcceptingOrdersException;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceOrder
{
    /**
     * Place an order for a dining table within an active or newly opened table session.
     *
     * @param  array<int, array{menu_item_id: int, quantity: int, notes?: string|null}>  $items
     *
     * @throws TableNotAcceptingOrdersException
     * @throws ValidationException
     */
    public function handle(
        DiningTable $diningTable,
        array $items,
        ?User $placedBy = null,
        ?string $customerName = null,
        ?string $customerPhone = null,
    ): Order {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Keranjang pesanan tidak boleh kosong.',
            ]);
        }

        return DB::transaction(function () use ($diningTable, $items, $placedBy, $customerName, $customerPhone): Order {
            /** @var DiningTable $lockedTable */
            $lockedTable = DiningTable::query()->lockForUpdate()->findOrFail($diningTable->id);

            if (! $lockedTable->is_active) {
                throw new TableNotAcceptingOrdersException('Meja ini sedang tidak aktif. Silakan hubungi kasir.');
            }

            $session = $lockedTable->activeSession()->lockForUpdate()->first();

            if ($session !== null && $session->isPaid()) {
                $session->update(['closed_at' => now()]);
                $session = null;
            }

            $cleanName = $customerName !== null && trim($customerName) !== '' ? trim($customerName) : null;
            $cleanPhone = $customerPhone !== null && trim($customerPhone) !== '' ? trim($customerPhone) : null;

            if ($session === null) {
                $session = TableSession::create([
                    'dining_table_id' => $lockedTable->id,
                    'customer_name' => $cleanName,
                    'customer_phone' => $cleanPhone,
                    'payment_status' => PaymentStatus::Unpaid,
                    'total_amount' => 0,
                    'opened_at' => now(),
                ]);
            } elseif ($cleanName !== null || $cleanPhone !== null) {
                $session->update(array_filter([
                    'customer_name' => $cleanName ?? $session->customer_name,
                    'customer_phone' => $cleanPhone ?? $session->customer_phone,
                ], fn ($v) => $v !== null));
            }

            if ($lockedTable->status !== TableStatus::Occupied) {
                $lockedTable->update(['status' => TableStatus::Occupied]);
            }

            $menuItemIds = array_unique(array_map(fn (array $item): int => (int) $item['menu_item_id'], $items));
            $menuItems = MenuItem::query()
                ->whereIn('id', $menuItemIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $preparedItems = [];
            $orderTotal = 0;

            foreach ($items as $row) {
                $quantity = (int) ($row['quantity'] ?? 0);

                if ($quantity < 1) {
                    continue;
                }

                /** @var MenuItem|null $menuItem */
                $menuItem = $menuItems->get((int) $row['menu_item_id']);

                if ($menuItem === null || ! $menuItem->is_available) {
                    $name = $menuItem?->name ?? 'Menu';

                    throw ValidationException::withMessages([
                        'items' => "Menu \"{$name}\" sedang habis dan tidak dapat dipesan.",
                    ]);
                }

                $subtotal = $menuItem->price * $quantity;
                $orderTotal += $subtotal;

                $preparedItems[] = [
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'unit_price' => $menuItem->price,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                    'notes' => isset($row['notes']) && trim((string) $row['notes']) !== '' ? trim((string) $row['notes']) : null,
                    'status' => OrderItemStatus::Active,
                ];
            }

            if ($preparedItems === []) {
                throw ValidationException::withMessages([
                    'items' => 'Keranjang pesanan tidak boleh kosong.',
                ]);
            }

            $order = Order::create([
                'table_session_id' => $session->id,
                'dining_table_id' => $lockedTable->id,
                'placed_by_user_id' => $placedBy?->id,
                'customer_name' => $cleanName ?? $session->customer_name,
                'customer_phone' => $cleanPhone ?? $session->customer_phone,
                'source' => $placedBy !== null ? 'cashier' : 'qr',
                'status' => OrderStatus::Pending,
                'total_amount' => $orderTotal,
            ]);

            foreach ($preparedItems as $itemData) {
                $order->items()->create($itemData);
            }

            $session->recalculateTotal();

            return $order->load(['items', 'tableSession', 'diningTable']);
        });
    }
}
