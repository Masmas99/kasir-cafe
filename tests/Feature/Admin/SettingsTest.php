<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_cannot_access_settings(): void
    {
        $this->actingAs(User::factory()->kasir()->create())
            ->get(route('admin.settings'))
            ->assertForbidden();
    }

    public function test_super_admin_can_update_cafe_settings(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Volt::test('admin.settings')
            ->set('cafe_name', 'Kopi Senja')
            ->set('tax_percent', 11)
            ->set('service_percent', 5)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Kopi Senja', Setting::getValue('cafe_name'));
        $this->assertSame('11', Setting::getValue('tax_percent'));
        $this->assertSame('5', Setting::getValue('service_percent'));
    }
}
