@props(['theme' => 'system'])

{{-- Applies the theme class before paint to avoid a flash of the wrong theme. --}}
<script>
    window.applyTheme = function (theme, persist = false) {
        try {
            if (persist) {
                localStorage.setItem('kasir_meja_theme', theme);
            }
        } catch (e) {}

        let storedTheme = null;
        try {
            storedTheme = localStorage.getItem('kasir_meja_theme');
        } catch (e) {}

        const resolvedTheme = persist
            ? theme
            : (storedTheme || theme || 'system');

        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDark = resolvedTheme === 'dark' || (resolvedTheme === 'system' && prefersDark);

        document.documentElement.dataset.theme = resolvedTheme;
        document.documentElement.classList.toggle('dark', isDark);
        document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';

        window.dispatchEvent(new CustomEvent('theme-changed', {
            detail: { theme: resolvedTheme, isDark: isDark },
        }));
    };

    window.toggleTheme = function () {
        const isCurrentlyDark = document.documentElement.classList.contains('dark');
        const nextTheme = isCurrentlyDark ? 'light' : 'dark';
        window.applyTheme(nextTheme, true);

        return nextTheme;
    };

    window.applyTheme(@js($theme));

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
        window.applyTheme(document.documentElement.dataset.theme || @js($theme));
    });

    window.addEventListener('theme-updated', function (event) {
        window.applyTheme(event.detail.theme, true);
    });

    document.addEventListener('livewire:navigated', function () {
        let storedTheme = null;
        try {
            storedTheme = localStorage.getItem('kasir_meja_theme');
        } catch (e) {}

        window.applyTheme(storedTheme || document.documentElement.dataset.theme || @js($theme));
    });
</script>
