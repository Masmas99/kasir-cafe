<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Actions\RecordCashierPayment;
use App\Enums\PaymentMethod;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DashboardStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_today_sales_orders_and_active_tables(): void
    {
        $cashier = User::factory()->kasir()->create();
        $table1 = DiningTable::factory()->create(['name' => 'Meja 1']);
        $table2 = DiningTable::factory()->create(['name' => 'Meja 2']);
        $menuItem = MenuItem::factory()->create(['price' => 25000]);

        $order1 = app(PlaceOrder::class)->handle($table1, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 2],
        ]);

        app(RecordCashierPayment::class)->handle(
            $order1->tableSession,
            $cashier,
            PaymentMethod::Cash,
            50000,
        );

        app(PlaceOrder::class)->handle($table2, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);

        $this->actingAs($cashier)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Rp 50.000')
            ->assertSee('2 Pesanan')
            ->assertSee('1 / 2 Meja');

        Volt::test('dashboard.overview')
            ->set('period', 'weekly')
            ->assertSet('period', 'weekly')
            ->set('period', 'monthly')
            ->assertSet('period', 'monthly')
            ->set('search', 'Meja 1')
            ->assertSee('Meja 1');
    }
}
