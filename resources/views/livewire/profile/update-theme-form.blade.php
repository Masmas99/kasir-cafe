<?php

use App\Enums\ThemePreference;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $theme = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->theme = Auth::user()->theme->value;
    }

    /**
     * Update the theme preference for the currently authenticated user.
     */
    public function updateTheme(): void
    {
        $validated = $this->validate([
            'theme' => ['required', Rule::enum(ThemePreference::class)],
        ]);

        Auth::user()->update(['theme' => $validated['theme']]);

        $this->dispatch('theme-updated', theme: $validated['theme']);
    }

    /**
     * Get the data passed to the view.
     *
     * @return array{themes: array<int, ThemePreference>}
     */
    public function with(): array
    {
        return ['themes' => ThemePreference::cases()];
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Tema Tampilan
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Pilihan tema disimpan di akun Anda dan diterapkan di semua perangkat.
        </p>
    </header>

    <form wire:submit="updateTheme" class="mt-6 space-y-6">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            @foreach ($themes as $themeOption)
                <label @class([
                    'flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm',
                    'border-brand-500 ring-1 ring-brand-500' => $theme === $themeOption->value,
                    'border-gray-300 dark:border-gray-700' => $theme !== $themeOption->value,
                ])>
                    <input type="radio" wire:model.live="theme" name="theme" value="{{ $themeOption->value }}" class="text-brand-600 focus:ring-brand-500" />
                    <span class="text-gray-800 dark:text-gray-200">{{ $themeOption->label() }}</span>
                </label>
            @endforeach
        </div>
        <x-input-error :messages="$errors->get('theme')" class="mt-2" />

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            <x-action-message class="me-3" on="theme-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
