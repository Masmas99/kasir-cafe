<?php

namespace Tests\Feature\Cashier;

use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_filter_transactions_by_table_and_payment_status(): void
    {
        $cashier = User::factory()->kasir()->create();
        $tableA = DiningTable::factory()->create(['name' => 'Meja Alpha']);
        $tableB = DiningTable::factory()->create(['name' => 'Meja Beta']);

        TableSession::factory()->for($tableA)->paid()->create(['total_amount' => 45000]);
        TableSession::factory()->for($tableB)->create([
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => 60000,
        ]);

        $this->actingAs($cashier);

        Volt::test('cashier.transactions')
            ->set('paymentStatus', PaymentStatus::Paid->value)
            ->assertSee('Rp 45.000')
            ->assertDontSee('Rp 60.000')
            ->set('paymentStatus', '')
            ->set('tableId', $tableB->id)
            ->assertSee('Rp 60.000')
            ->assertDontSee('Rp 45.000');
    }
}
