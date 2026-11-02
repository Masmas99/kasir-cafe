<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_cannot_access_category_or_menu_management(): void
    {
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)->get(route('admin.categories'))->assertForbidden();
        $this->actingAs($kasir)->get(route('admin.menu-items'))->assertForbidden();
    }

    public function test_super_admin_can_create_update_and_delete_categories(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Volt::test('admin.categories')
            ->set('name', 'Kopi Panas')
            ->set('sort_order', 1)
            ->call('save')
            ->assertHasNoErrors();

        $category = Category::where('name', 'Kopi Panas')->firstOrFail();

        Volt::test('admin.categories')
            ->call('edit', $category->id)
            ->set('name', 'Signature Coffee')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Signature Coffee', $category->refresh()->name);

        Volt::test('admin.categories')
            ->call('delete', $category->id);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_with_menu_items_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $category = Category::factory()->has(MenuItem::factory())->create();

        Volt::test('admin.categories')
            ->call('delete', $category->id);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_super_admin_can_create_menu_item_with_image_and_toggle_availability(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->superAdmin()->create());
        $category = Category::factory()->create();

        Volt::test('admin.menu-items')
            ->call('create')
            ->set('category_id', $category->id)
            ->set('name', 'Es Kopi Susu Gula Aren')
            ->set('description', 'Espresso dengan susu segar dan gula aren')
            ->set('price', 22000)
            ->set('is_available', true)
            ->set('image', UploadedFile::fake()->image('kopi.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $menuItem = MenuItem::where('name', 'Es Kopi Susu Gula Aren')->firstOrFail();

        $this->assertSame(22000, $menuItem->price);
        $this->assertTrue($menuItem->is_available);
        $this->assertNotNull($menuItem->image_path);
        Storage::disk('public')->assertExists($menuItem->image_path);

        Volt::test('admin.menu-items')
            ->call('toggleAvailability', $menuItem->id);

        $this->assertFalse($menuItem->refresh()->is_available);

        Volt::test('admin.menu-items')
            ->call('delete', $menuItem->id);

        $this->assertDatabaseMissing('menu_items', ['id' => $menuItem->id]);
        Storage::disk('public')->assertMissing($menuItem->image_path);
    }
}
