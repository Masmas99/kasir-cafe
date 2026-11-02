<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_cannot_access_user_management(): void
    {
        $this->actingAs(User::factory()->kasir()->create())
            ->get(route('admin.users'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_edit_and_delete_cashier_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Volt::test('admin.users')
            ->set('name', 'Kasir Pagi')
            ->set('email', 'pagi@kasir.test')
            ->set('role', UserRole::Kasir->value)
            ->set('password', 'Password123!')
            ->call('save')
            ->assertHasNoErrors();

        $cashier = User::where('email', 'pagi@kasir.test')->firstOrFail();
        $this->assertSame(UserRole::Kasir, $cashier->role);

        Volt::test('admin.users')
            ->call('edit', $cashier->id)
            ->set('name', 'Kasir Shift Pagi')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Kasir Shift Pagi', $cashier->refresh()->name);

        // Cannot delete self
        Volt::test('admin.users')
            ->call('delete', $admin->id);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);

        // Can delete other user
        Volt::test('admin.users')
            ->call('delete', $cashier->id);

        $this->assertDatabaseMissing('users', ['id' => $cashier->id]);
    }
}
