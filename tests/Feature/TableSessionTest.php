<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Actions\RecordCashierPayment;
use App\Enums\PaymentMethod;
use App\Enums\TableStatus;
use App\Exceptions\TableNotAcceptingOrdersException;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_order_on_available_table_opens_new_session_and_occupies_table(): void
    {
        $table = DiningTable::factory()->create(['status' => TableStatus::Available]);
        $menuItem = MenuItem::factory()->create(['price' => 25000]);

        $order = app(PlaceOrder::class)->handle($table, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 2, 'notes' => 'Less ice'],
        ]);

        $table->refresh();

        $this->assertSame(TableStatus::Occupied, $table->status);
        $this->assertNotNull($table->activeSession);
        $this->assertSame($table->activeSession->id, $order->table_session_id);
        $this->assertSame(50000, $order->total_amount);
        $this->assertSame(50000, $table->activeSession->total_amount);
    }

    public function test_additional_order_on_occupied_table_joins_existing_active_session(): void
    {
        $table = DiningTable::factory()->create(['status' => TableStatus::Available]);
        $item1 = MenuItem::factory()->create(['price' => 20000]);
        $item2 = MenuItem::factory()->create(['price' => 15000]);

        $firstOrder = app(PlaceOrder::class)->handle($table, [
            ['menu_item_id' => $item1->id, 'quantity' => 1],
        ]);

        $secondOrder = app(PlaceOrder::class)->handle($table, [
            ['menu_item_id' => $item2->id, 'quantity' => 2],
        ]);

        $this->assertSame($firstOrder->table_session_id, $secondOrder->table_session_id);
        $this->assertSame(1, $table->sessions()->count());
        $this->assertSame(50000, $firstOrder->tableSession->refresh()->total_amount);
    }

    public function test_after_session_is_paid_next_order_opens_new_session_without_requiring_manual_close(): void
    {
        $cashier = User::factory()->kasir()->create();
        $table = DiningTable::factory()->create(['name' => 'Meja 5', 'status' => TableStatus::Available]);
        $menuItem = MenuItem::factory()->create(['price' => 30000]);

        $firstOrder = app(PlaceOrder::class)->handle($table, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);

        app(RecordCashierPayment::class)->handle(
            $firstOrder->tableSession,
            $cashier,
            PaymentMethod::Cash,
            30000,
        );

        $this->assertSame(TableStatus::Available, $table->refresh()->status);

        $secondOrder = app(PlaceOrder::class)->handle($table, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 2],
        ]);

        $this->assertNotSame($firstOrder->table_session_id, $secondOrder->table_session_id);
        $this->assertSame(30000, $firstOrder->tableSession->refresh()->total_amount);
        $this->assertSame(60000, $secondOrder->tableSession->refresh()->total_amount);
    }

    public function test_inactive_table_rejects_orders(): void
    {
        $table = DiningTable::factory()->inactive()->create();
        $menuItem = MenuItem::factory()->create(['price' => 20000]);

        $this->expectException(TableNotAcceptingOrdersException::class);

        app(PlaceOrder::class)->handle($table, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);
    }
}
