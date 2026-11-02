<?php

use App\Enums\UserRole;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Get the sidebar navigation groups visible to the current user.
     *
     * @return array<int, array{label: string, items: array<int, array{label: string, route: string, icon: string}>}>
     */
    public function with(): array
    {
        $groups = [
            [
                'label' => 'Utama',
                'roles' => [UserRole::SuperAdmin, UserRole::Kasir],
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home'],
                ],
            ],
            [
                'label' => 'Kasir',
                'roles' => [UserRole::SuperAdmin, UserRole::Kasir],
                'items' => [
                    ['label' => 'Pesanan Masuk', 'route' => 'cashier.orders', 'icon' => 'clipboard'],
                    ['label' => 'Status Meja', 'route' => 'cashier.tables', 'icon' => 'table'],
                    ['label' => 'Pesanan Manual', 'route' => 'cashier.manual-order', 'icon' => 'plus'],
                    ['label' => 'Transaksi', 'route' => 'cashier.transactions', 'icon' => 'receipt'],
                ],
            ],
            [
                'label' => 'Manajemen',
                'roles' => [UserRole::SuperAdmin],
                'items' => [
                    ['label' => 'Kategori', 'route' => 'admin.categories', 'icon' => 'tag'],
                    ['label' => 'Menu', 'route' => 'admin.menu-items', 'icon' => 'cup'],
                    ['label' => 'Meja & QR', 'route' => 'admin.tables', 'icon' => 'qr'],
                    ['label' => 'Pengguna', 'route' => 'admin.users', 'icon' => 'users'],
                    ['label' => 'Pengaturan', 'route' => 'admin.settings', 'icon' => 'cog'],
                ],
            ],
        ];

        $visibleGroups = collect($groups)
            ->filter(fn (array $group): bool => auth()->user()->hasRole(...$group['roles']))
            ->map(fn (array $group): array => [
                'label' => $group['label'],
                'items' => array_values(array_filter($group['items'], fn (array $item): bool => Route::has($item['route']))),
            ])
            ->filter(fn (array $group): bool => $group['items'] !== [])
            ->values()
            ->all();

        return ['groups' => $visibleGroups];
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div x-data="{ open: false }" x-on:keydown.escape.window="open = false">
    <!-- Mobile Top Bar -->
    <div class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 dark:border-gray-700 dark:bg-gray-800 lg:hidden">
        <div class="flex items-center gap-3">
            <button type="button" x-on:click="open = true" class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" aria-label="Buka menu">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
            <span class="font-semibold text-gray-800 dark:text-gray-100">{{ config('app.name') }}</span>
        </div>
        <x-theme-toggle />
    </div>

    <!-- Mobile Backdrop -->
    <div x-show="open" x-transition.opacity x-on:click="open = false" class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden" style="display: none;"></div>

    <!-- Sidebar -->
    <aside
        x-bind:class="open ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 start-0 z-50 flex w-64 -translate-x-full flex-col border-e border-gray-200 bg-white transition-transform duration-200 dark:border-gray-700 dark:bg-gray-800 lg:translate-x-0"
    >
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-gray-200 px-5 dark:border-gray-700">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
                <x-application-logo class="h-8 w-auto fill-current text-brand-500" />
                <span class="font-semibold text-gray-800 dark:text-gray-100">{{ config('app.name') }}</span>
            </a>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
            @foreach ($groups as $group)
                <div>
                    <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ $group['label'] }}</p>
                    <ul class="space-y-1">
                        @foreach ($group['items'] as $item)
                            @php($isActive = request()->routeIs($item['route']))
                            <li>
                                <a
                                    href="{{ route($item['route']) }}"
                                    wire:navigate
                                    @class([
                                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                                        'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400' => $isActive,
                                        'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white' => ! $isActive,
                                    ])
                                >
                                    <x-icon :name="$item['icon']" />
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="shrink-0 space-y-1 border-t border-gray-200 p-3 dark:border-gray-700">
            <div class="flex items-center justify-between gap-2 px-3 pb-2">
                <div class="min-w-0">
                    <div class="truncate text-sm font-medium text-gray-800 dark:text-gray-100" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                    <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()->role->label() }}</div>
                </div>
                <x-theme-toggle />
            </div>
            <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                <x-icon name="user" />
                Profil &amp; Tema
            </a>
            <button wire:click="logout" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                <x-icon name="logout" />
                Keluar
            </button>
        </div>
    </aside>
</div>
