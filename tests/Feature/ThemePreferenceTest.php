<?php

namespace Tests\Feature;

use App\Enums\ThemePreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_contains_the_theme_form(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/profile')
            ->assertOk()
            ->assertSeeVolt('profile.update-theme-form');
    }

    public function test_user_can_save_their_theme_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-theme-form')
            ->assertSet('theme', ThemePreference::System->value)
            ->set('theme', ThemePreference::Dark->value)
            ->call('updateTheme')
            ->assertHasNoErrors()
            ->assertDispatched('theme-updated', theme: ThemePreference::Dark->value);

        $this->assertSame(ThemePreference::Dark, $user->refresh()->theme);
    }

    public function test_invalid_theme_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-theme-form')
            ->set('theme', 'neon')
            ->call('updateTheme')
            ->assertHasErrors('theme');

        $this->assertSame(ThemePreference::System, $user->refresh()->theme);
    }

    public function test_saved_theme_is_rendered_on_every_device(): void
    {
        $user = User::factory()->create(['theme' => ThemePreference::Dark]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('window.applyTheme(\'dark\')', false);
    }
}
