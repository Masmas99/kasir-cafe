<?php

namespace Tests\Feature\Customer;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
