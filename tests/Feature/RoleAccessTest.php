<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:super_admin'])
            ->get('/_test/super-admin-only', fn () => 'ok');

        Route::middleware(['web', 'auth', 'role:super_admin,kasir'])
            ->get('/_test/staff-only', fn () => 'ok');
    }

    public function test_new_users_default_to_the_kasir_role(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Kasir, $user->role);
        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_super_admin_can_access_super_admin_routes(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/_test/super-admin-only')
            ->assertOk();
    }

    public function test_kasir_cannot_access_super_admin_routes(): void
    {
        $this->actingAs(User::factory()->kasir()->create())
            ->get('/_test/super-admin-only')
            ->assertForbidden();
    }

    public function test_both_roles_can_access_staff_routes(): void
    {
        $this->actingAs(User::factory()->kasir()->create())
            ->get('/_test/staff-only')
            ->assertOk();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/_test/staff-only')
            ->assertOk();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/_test/staff-only')->assertRedirect(route('login'));
    }

    public function test_sidebar_shows_the_role_label(): void
    {
        $this->actingAs(User::factory()->kasir()->create());

        Volt::test('layout.navigation')
            ->assertSee('Kasir')
            ->assertSee('Dashboard');
    }
}
