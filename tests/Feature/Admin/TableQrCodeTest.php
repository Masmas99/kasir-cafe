<?php

namespace Tests\Feature\Admin;

use App\Models\DiningTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableQrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_download_table_qr_svg(): void
    {
        $table = DiningTable::factory()->create(['name' => 'Meja 7']);

        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.tables.qr.download', $table));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Disposition', 'attachment; filename="qr-meja-7.svg"');

        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_super_admin_can_view_printable_qr_sheet(): void
    {
        $activeTable = DiningTable::factory()->create(['name' => 'Meja 1', 'is_active' => true]);
        $inactiveTable = DiningTable::factory()->inactive()->create(['name' => 'Meja Nonaktif']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.tables.qr.print'))
            ->assertOk()
            ->assertSee($activeTable->name)
            ->assertDontSee($inactiveTable->name);
    }

    public function test_kasir_cannot_download_or_print_qr(): void
    {
        $table = DiningTable::factory()->create();
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)->get(route('admin.tables.qr.download', $table))->assertForbidden();
        $this->actingAs($kasir)->get(route('admin.tables.qr.print'))->assertForbidden();
    }
}
