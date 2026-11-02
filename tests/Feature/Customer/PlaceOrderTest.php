<?php

namespace Tests\Feature\Customer;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_scan_qr_and_place_order_without_login(): void
    {
        $table = DiningTable::factory()->create(['name' => 'Meja 5']);
        $category = Category::factory()->create(['name' => 'Espresso Based']);
        $menuItem = MenuItem::factory()->for($category)->create([
            'name' => 'Caffe Latte',
            'price' => 28000,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Meja 5')
            ->assertSee(route('customer.menu', $table->code), false);

        $this->get(route('customer.menu', $table->code))
            ->assertOk()
            ->assertSee('Meja 5')
            ->assertSee('Caffe Latte');

        $component = Volt::test('customer.menu', ['diningTable' => $table])
            ->call('addToCart', $menuItem->id)
            ->call('addToCart', $menuItem->id)
            ->set("cart.{$menuItem->id}.notes", 'Extra hot')
            ->call('submitOrder');

        $session = $table->refresh()->activeSession;

        $this->assertNotNull($session);
        $this->assertSame(56000, $session->total_amount);

        $component->assertRedirect(route('customer.session', [
            'diningTable' => $table->code,
            'tableSession' => $session->token,
        ]));

        $this->get(route('customer.session', [
            'diningTable' => $table->code,
            'tableSession' => $session->token,
        ]))
            ->assertOk()
            ->assertSee('Caffe Latte')
            ->assertSee('Extra hot')
            ->assertSee('Menunggu');
    }

    public function test_customer_cannot_add_sold_out_item_to_cart(): void
    {
        $table = DiningTable::factory()->create();
        $soldOutItem = MenuItem::factory()->soldOut()->create(['name' => 'Matcha Habis']);

        Volt::test('customer.menu', ['diningTable' => $table])
            ->call('addToCart', $soldOutItem->id)
            ->assertSet('cart', [])
            ->assertSee('Menu ini sedang habis');
    }

    public function test_customer_can_place_order_and_immediately_pay_with_qris(): void
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
                'qr_string' => '00020101021226590014ID.CO.MIDTRANS.CUSTQRIS',
                'actions' => [
                    [
                        'name' => 'generate-qr-code',
                        'url' => 'https://merchants-app.sbx.midtrans.com/v4/qris/gopay/cust1/qr-code',
                    ],
                ],
            ], 200),
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'status_code' => '201',
                'transaction_status' => 'pending',
            ], 200),
        ]);

        $table = DiningTable::factory()->create(['name' => 'Meja 2']);
        $menuItem = MenuItem::factory()->create(['name' => 'Es Kopi Susu', 'price' => 22000]);

        $component = Volt::test('customer.menu', ['diningTable' => $table])
            ->call('addToCart', $menuItem->id)
            ->call('submitOrderWithQris');

        $session = $table->refresh()->activeSession;
        $this->assertNotNull($session);

        $component->assertRedirect(route('customer.session', [
            'diningTable' => $table->code,
            'tableSession' => $session->token,
        ]));

        $this->get(route('customer.session', [
            'diningTable' => $table->code,
            'tableSession' => $session->token,
        ]))
            ->assertOk()
            ->assertSee('QRIS Midtrans')
            ->assertSee('https://merchants-app.sbx.midtrans.com/v4/qris/gopay/cust1/qr-code', false);
    }

    public function test_customer_checkout_flow_saves_name_and_whatsapp_then_proceeds_to_detail_and_payment(): void
    {
        $table = DiningTable::factory()->create(['name' => 'Meja 3']);
        $menuItem = MenuItem::factory()->create(['name' => 'Iced Matcha Latte', 'price' => 28000]);

        $component = Volt::test('customer.menu', ['diningTable' => $table])
            ->call('addToCart', $menuItem->id)
            ->assertDispatched('item-added-to-cart', name: 'Iced Matcha Latte')
            ->call('openCheckout')
            ->assertSet('checkoutOpen', true)
            ->set('customer_name', '')
            ->set('customer_phone', '')
            ->call('proceedToOrderDetail')
            ->assertHasErrors(['customer_name', 'customer_phone'])
            ->set('customer_name', 'Budi Santoso')
            ->set('customer_phone', '081234567890')
            ->call('proceedToOrderDetail')
            ->assertHasNoErrors();

        $session = $table->refresh()->activeSession;
        $this->assertNotNull($session);
        $this->assertSame('Budi Santoso', $session->customer_name);
        $this->assertSame('081234567890', $session->customer_phone);

        $component->assertRedirect(route('customer.session', [
            'diningTable' => $table->code,
            'tableSession' => $session->token,
        ]));

        $this->get(route('customer.session', [
            'diningTable' => $table->code,
            'tableSession' => $session->token,
        ]))
            ->assertOk()
            ->assertSee('Detail Pesanan')
            ->assertSee('Budi Santoso')
            ->assertSee('081234567890')
            ->assertSee('Iced Matcha Latte')
            ->assertSee('Pilih Metode Pembayaran');
    }
}
