@php
    $cafeName = \Illuminate\Support\Facades\Schema::hasTable('settings')
        ? \App\Models\Setting::getValue('cafe_name', config('app.name', 'Kopi Meja Cafe'))
        : config('app.name', 'Kopi Meja Cafe');

    $tables = \Illuminate\Support\Facades\Schema::hasTable('dining_tables')
        ? \App\Models\DiningTable::with('activeSession')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
        : collect();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $cafeName }} — Pesan via QR Meja</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <x-theme-script theme="system" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
        <!-- Top Navigation -->
        <header class="sticky top-0 z-20 border-b border-gray-200 bg-white/90 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3.5 sm:px-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white shadow-sm">
                        <x-icon name="cup" class="h-6 w-6" />
                    </div>
                    <div>
                        <h1 class="text-base font-bold leading-tight text-gray-900 dark:text-white">{{ $cafeName }}</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Pemesanan Mandiri via QR Meja</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <x-theme-toggle :show-label="true" />

                    @auth
                        <a
                            href="{{ route('cashier.orders') }}"
                            class="rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            Panel Kasir
                        </a>
                    @else
                        @if (Route::has('login'))
                            <a
                                href="{{ route('login') }}"
                                class="rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                            >
                                Login Staf
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:py-12">
            <!-- Hero Banner -->
            <div class="rounded-3xl bg-gradient-to-br from-brand-600 to-amber-700 p-6 text-white shadow-lg sm:p-10">
                <div class="max-w-2xl space-y-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold backdrop-blur">
                        <x-icon name="qr" class="h-4 w-4" /> Self-Order &amp; QRIS Payment
                    </span>
                    <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">
                        Pesan Menu Favorit Langsung dari Meja Anda
                    </h2>
                    <p class="text-sm text-amber-100 sm:text-base">
                        Pilih nomor meja Anda di bawah ini atau pindai kode QR yang tertera di meja untuk melihat daftar menu bergambar, memesan, dan membayar langsung dengan QRIS maupun Tunai di Kasir.
                    </p>
                </div>
            </div>

            <!-- Daftar Meja & QR -->
            <section class="mt-8 space-y-4">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Pilih Meja untuk Mulai Memesan</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Klik tombol <strong>Pesan di Meja Ini</strong> atau scan QR Code meja menggunakan kamera HP Anda.</p>
                    </div>
                </div>

                @if ($tables->isEmpty())
                    <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                        Belum ada meja aktif yang tersedia.
                    </div>
                @else
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($tables as $table)
                            <div class="flex flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-brand-500 hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="text-xs font-medium uppercase tracking-wider text-gray-400">Meja Pelanggan</span>
                                            <h4 class="text-lg font-bold text-gray-900 dark:text-white">{{ $table->name }}</h4>
                                        </div>
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $table->status->badgeClasses() }}">
                                            {{ $table->status->label() }}
                                        </span>
                                    </div>

                                    <div class="my-4 flex flex-col items-center justify-center rounded-xl border border-gray-100 bg-white p-4 dark:border-gray-800">
                                        {!! SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(140)->margin(1)->generate($table->orderUrl()) !!}
                                        <span class="mt-2 font-mono text-[11px] text-gray-400">Kode: {{ $table->code }}</span>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <a
                                        href="{{ route('customer.menu', $table->code) }}"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3 text-xs font-bold uppercase tracking-wider text-white shadow hover:bg-brand-500"
                                    >
                                        <x-icon name="cup" class="h-4 w-4" />
                                        Pesan di {{ $table->name }}
                                    </a>

                                    @if ($table->activeSession && ! $table->activeSession->isPaid())
                                        <a
                                            href="{{ route('customer.session', ['diningTable' => $table->code, 'tableSession' => $table->activeSession->token]) }}"
                                            class="block w-full rounded-xl border border-brand-600 bg-brand-50 px-4 py-2 text-center text-xs font-semibold text-brand-700 hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-300"
                                        >
                                            Lihat Pesanan &amp; Tagihan Aktif
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </main>
    </body>
</html>
