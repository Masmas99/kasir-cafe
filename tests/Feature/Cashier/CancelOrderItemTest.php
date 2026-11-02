<?php

namespace Tests\Feature\Cashier;

use App\Actions\PlaceOrder;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CancelOrderItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancellation_requires_a_reason(): void
    {
        $cashier = User::factory()->kasir()->create();
        $table = DiningTable::factory()->create();
        $menuItem = MenuItem::factory()->create(['price' => 20000]);

        $order = app(PlaceOrder::class)->handle($table, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);

        $item = $order->items->first();

        $this->actingAs($cashier);

        Volt::test('cashier.orders')
            ->call('promptCancelItem', $item->id)
            ->set('cancellationReason', '')
            ->call('confirmCancellation')
            ->assertHasErrors('cancellationReason');

        $this->assertSame(OrderItemStatus::Active, $item->refresh()->status);
    }

    public function test_cancelling_item_updates_session_total_and_logs_activity(): void
    {
        $cashier = User::factory()->kasir()->create();
        $table = DiningTable::factory()->create();
        $item1 = MenuItem::factory()->create(['name' => 'Latte', 'price' => 25000]);
        $item2 = MenuItem::factory()->create(['name' => 'Croissant', 'price' => 20000]);

        $order = app(PlaceOrder::class)->handle($table, [
            ['menu_item_id' => $item1->id, 'quantity' => 1],
            ['menu_item_id' => $item2->id, 'quantity' => 1],
        ]);

        $croissantOrderItem = $order->items()->where('menu_item_id', $item2->id)->firstOrFail();

        $this->actingAs($cashier);

        Volt::test('cashier.orders')
            ->call('promptCancelItem', $croissantOrderItem->id)
            ->set('cancellationReason', 'Stok croissant habis')
            ->call('confirmCancellation')
            ->assertHasNoErrors();

        $this->assertSame(OrderItemStatus::Cancelled, $croissantOrderItem->refresh()->status);
        $this->assertSame('Stok croissant habis', $croissantOrderItem->cancellation_reason);
        $this->assertSame(25000, $order->refresh()->total_amount);
        $this->assertSame(25000, $order->tableSession->refresh()->total_amount);

        Volt::test('cashier.orders')
            ->call('updateStatus', $order->id, OrderStatus::Processing->value)
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Processing, $order->refresh()->status);
    }
}
