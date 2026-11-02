@props(['showLabel' => false])

<button
    type="button"
    x-data="{ isDark: document.documentElement.classList.contains('dark') }"
    x-init="isDark = document.documentElement.classList.contains('dark')"
    x-on:theme-changed.window="isDark = $event.detail.isDark"
    x-on:click="window.toggleTheme()"
    {{ $attributes->merge([
        'class' => 'inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-2.5 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
    ]) }}
    title="Ubah Mode Terang / Gelap"
    aria-label="Ubah Mode Terang / Gelap"
>
    <span x-show="!isDark" class="inline-flex items-center gap-1.5">
        <x-icon name="moon" class="h-4 w-4 text-gray-700 dark:text-gray-300" />
        @if ($showLabel)
            <span>Mode Gelap</span>
        @endif
    </span>

    <span x-show="isDark" style="display: none;" class="inline-flex items-center gap-1.5">
        <x-icon name="sun" class="h-4 w-4 text-amber-400" />
        @if ($showLabel)
            <span>Mode Terang</span>
        @endif
    </span>
</button>
