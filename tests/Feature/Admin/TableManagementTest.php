<?php

namespace Tests\Feature\Admin;

use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TableManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_cannot_access_table_management(): void
    {
        $this->actingAs(User::factory()->kasir()->create())
            ->get(route('admin.tables'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_edit_regenerate_qr_and_delete_table(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Volt::test('admin.tables')
            ->set('name', 'Meja 5')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $table = DiningTable::where('name', 'Meja 5')->firstOrFail();
        $originalCode = $table->code;

        $this->assertSame(TableStatus::Available, $table->status);
        $this->assertNotEmpty($originalCode);

        Volt::test('admin.tables')
            ->call('edit', $table->id)
            ->set('name', 'Meja 5A')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Meja 5A', $table->refresh()->name);

        Volt::test('admin.tables')
            ->call('regenerateCode', $table->id);

        $this->assertNotSame($originalCode, $table->refresh()->code);

        Volt::test('admin.tables')
            ->call('delete', $table->id);

        $this->assertDatabaseMissing('dining_tables', ['id' => $table->id]);
    }

    public function test_occupied_or_paid_table_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $table = DiningTable::factory()->create(['status' => TableStatus::Occupied]);

        Volt::test('admin.tables')
            ->call('delete', $table->id);

        $this->assertDatabaseHas('dining_tables', ['id' => $table->id]);
    }
}
