<?php

namespace App\Actions;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrderItem
{
    /**
     * Cancel a single order item with a mandatory reason and recalculate totals.
     *
     * @throws ValidationException
     */
    public function cancelItem(OrderItem $orderItem, User $staff, string $reason): OrderItem
    {
        $trimmedReason = trim($reason);

        if ($trimmedReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan pembatalan wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($orderItem, $staff, $trimmedReason): OrderItem {
            /** @var OrderItem $item */
            $item = OrderItem::query()->with('order.tableSession.diningTable')->lockForUpdate()->findOrFail($orderItem->id);

            if ($item->order->tableSession->isPaid()) {
                throw ValidationException::withMessages([
                    'reason' => 'Item pada sesi yang sudah lunas tidak dapat dibatalkan.',
                ]);
            }

            $item->update([
                'status' => OrderItemStatus::Cancelled,
                'cancellation_reason' => $trimmedReason,
            ]);

            $hasActiveItems = $item->order->items()
                ->where('status', OrderItemStatus::Active)
                ->exists();

            if (! $hasActiveItems) {
                $item->order->update([
                    'status' => OrderStatus::Cancelled,
                    'cancellation_reason' => $trimmedReason,
                ]);
            }

            $item->order->tableSession->recalculateTotal();

            ActivityLog::create([
                'user_id' => $staff->id,
                'action' => 'cancel_order_item',
                'subject_type' => OrderItem::class,
                'subject_id' => $item->id,
                'description' => "Membatalkan item {$item->item_name} pada {$item->order->order_number}",
                'properties' => [
                    'reason' => $trimmedReason,
                    'order_id' => $item->order_id,
                ],
            ]);

            return $item->refresh();
        });
    }

    /**
     * Cancel an entire order and all of its items with a mandatory reason.
     *
     * @throws ValidationException
     */
    public function cancelOrder(Order $order, User $staff, string $reason): Order
    {
        $trimmedReason = trim($reason);

        if ($trimmedReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan pembatalan wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($order, $staff, $trimmedReason): Order {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->with('tableSession.diningTable')->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->tableSession->isPaid()) {
                throw ValidationException::withMessages([
                    'reason' => 'Pesanan pada sesi yang sudah lunas tidak dapat dibatalkan.',
                ]);
            }

            $lockedOrder->items()->update([
                'status' => OrderItemStatus::Cancelled,
                'cancellation_reason' => $trimmedReason,
            ]);

            $lockedOrder->update([
                'status' => OrderStatus::Cancelled,
                'cancellation_reason' => $trimmedReason,
                'total_amount' => 0,
            ]);

            $lockedOrder->tableSession->recalculateTotal();

            ActivityLog::create([
                'user_id' => $staff->id,
                'action' => 'cancel_order',
                'subject_type' => Order::class,
                'subject_id' => $lockedOrder->id,
                'description' => "Membatalkan pesanan {$lockedOrder->order_number}",
                'properties' => [
                    'reason' => $trimmedReason,
                ],
            ]);

            return $lockedOrder->refresh();
        });
    }
}
