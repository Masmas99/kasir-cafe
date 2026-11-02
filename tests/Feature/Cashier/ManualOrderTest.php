<?php

namespace Tests\Feature\Cashier;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ManualOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_order_page_displays_menu_images_and_requires_selecting_a_table(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('menu-items/latte.svg', '<svg></svg>');

        $cashier = User::factory()->kasir()->create();
        $category = Category::factory()->create(['name' => 'Coffee']);
        $menuItem = MenuItem::factory()->for($category)->create([
            'name' => 'Signature Latte',
            'price' => 25000,
            'image_path' => 'menu-items/latte.svg',
        ]);

        $this->actingAs($cashier);

        Volt::test('cashier.manual-order')
            ->assertSee($menuItem->image_url, false)
            ->call('addItem', $menuItem->id)
            ->call('submitManualOrder')
            ->assertHasErrors('dining_table_id');
    }

    public function test_cashier_manual_order_with_cash_immediately_pays_and_shows_receipt(): void
    {
        $cashier = User::factory()->kasir()->create();
        $table = DiningTable::factory()->create(['name' => 'Meja 8']);
        $menuItem = MenuItem::factory()->create(['name' => 'Americano', 'price' => 18000]);

        $this->actingAs($cashier);

        $component = Volt::test('cashier.manual-order')
            ->set('dining_table_id', $table->id)
            ->call('addItem', $menuItem->id)
            ->call('addItem', $menuItem->id)
            ->set('payment_method', PaymentMethod::Cash->value)
            ->set('amount_received', 50000)
            ->call('submitManualOrder')
            ->assertHasNoErrors()
            ->assertSet('step', 'receipt')
            ->assertSee('PEMBAYARAN LUNAS')
            ->assertSee('Americano')
            ->assertSee('Rp 36.000')
            ->assertSee('Rp 14.000');

        $session = $table->sessions()->latest('id')->first();

        $this->assertNotNull($session);
        $this->assertSame(PaymentStatus::Paid, $session->payment_status);
        $this->assertSame(TableStatus::Available, $table->refresh()->status);

        $component->call('startNewOrder')
            ->assertSet('step', 'order');
    }

    public function test_cashier_manual_order_with_qris_displays_midtrans_qris_and_transitions_to_receipt_when_settled(): void
    {
        config([
            'midtrans.server_key' => 'SB-Mid-server-test123',
            'midtrans.is_production' => false,
            'midtrans.sandbox_base_url' => 'https://api.sandbox.midtrans.com',
        ]);

        Http::fake([
            'https://api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '201',
                'transaction_status' => 'pending',
                'payment_type' => 'qris',
                'qr_string' => '00020101021226590014ID.CO.MIDTRANS.MANUALQRIS',
            ], 200),
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'status_code' => '200',
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'transaction_id' => 'mid-tx-settled-1',
            ], 200),
        ]);

        $cashier = User::factory()->kasir()->create();
        $table = DiningTable::factory()->create(['name' => 'Meja 4']);
        $menuItem = MenuItem::factory()->create(['name' => 'Matcha Latte', 'price' => 28000]);

        $this->actingAs($cashier);

        $component = Volt::test('cashier.manual-order')
            ->set('dining_table_id', $table->id)
            ->call('addItem', $menuItem->id)
            ->set('payment_method', PaymentMethod::Qris->value)
            ->call('submitManualOrder')
            ->assertHasNoErrors()
            ->assertSet('step', 'qris')
            ->assertSee('SCAN QRIS UNTUK MEMBAYAR')
            ->assertSee('Rp 28.000');

        $payment = Payment::latest('id')->firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->status);

        // When Midtrans settles the QRIS, checkQrisStatus transitions straight to the receipt
        $component->call('checkQrisStatus')
            ->assertSet('step', 'receipt')
            ->assertSee('PEMBAYARAN LUNAS')
            ->assertSee('QRIS (Midtrans)');

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame(TableStatus::Available, $table->refresh()->status);

        // Paid orders must still appear in Pesanan Masuk (cashier.orders) so kitchen/cashier can prepare and serve them
        Volt::test('cashier.orders')
            ->assertSee('Meja 4')
            ->assertSee('Matcha Latte')
            ->assertSee('Lunas')
            ->assertSee('Cetak Struk');
    }
}
