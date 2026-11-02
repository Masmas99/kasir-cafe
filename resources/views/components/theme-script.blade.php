@props(['theme' => 'system'])

{{-- Applies the theme class before paint to avoid a flash of the wrong theme. --}}
<script>
    window.applyTheme = function (theme) {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDark = theme === 'dark' || (theme === 'system' && prefersDark);

        document.documentElement.dataset.theme = theme;
        document.documentElement.classList.toggle('dark', isDark);
    };

    window.applyTheme(@js($theme));

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
        window.applyTheme(document.documentElement.dataset.theme);
    });

    window.addEventListener('theme-updated', function (event) {
        window.applyTheme(event.detail.theme);
    });
</script>
