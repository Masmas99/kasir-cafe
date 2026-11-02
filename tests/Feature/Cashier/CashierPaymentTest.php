<?php

namespace Tests\Feature\Cashier;

use App\Actions\PlaceOrder;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CashierPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_record_payment_and_complete_session(): void
    {
        $cashier = User::factory()->kasir()->create();
        $table = DiningTable::factory()->create(['name' => 'Meja 3']);
        $menuItem = MenuItem::factory()->create(['price' => 35000]);

        $order = app(PlaceOrder::class)->handle($table, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 2],
        ]);

        $session = $order->tableSession;

        $this->actingAs($cashier);

        Volt::test('cashier.tables')
            ->call('openPaymentModal', $session->id)
            ->set('paymentMethod', PaymentMethod::Cash->value)
            ->set('amountReceived', 100000)
            ->call('processPayment')
            ->assertHasNoErrors();

        $this->assertSame(PaymentStatus::Paid, $session->refresh()->payment_status);
        $this->assertSame(TableStatus::Available, $table->refresh()->status);

        $this->assertDatabaseHas('payments', [
            'table_session_id' => $session->id,
            'recorded_by_user_id' => $cashier->id,
            'method' => PaymentMethod::Cash->value,
            'amount' => 70000,
            'amount_received' => 100000,
            'change_amount' => 30000,
        ]);

        $this->actingAs($cashier)
            ->get(route('cashier.receipts.show', $session))
            ->assertOk()
            ->assertSee('Meja 3')
            ->assertSee('70.000');
    }

    public function test_underpayment_is_rejected(): void
    {
        $cashier = User::factory()->kasir()->create();
        $table = DiningTable::factory()->create();
        $menuItem = MenuItem::factory()->create(['price' => 40000]);

        $order = app(PlaceOrder::class)->handle($table, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);

        $this->actingAs($cashier);

        Volt::test('cashier.tables')
            ->call('openPaymentModal', $order->table_session_id)
            ->set('amountReceived', 20000)
            ->call('processPayment')
            ->assertHasErrors('amount_received');

        $this->assertSame(PaymentStatus::Unpaid, $order->tableSession->refresh()->payment_status);
    }
}
