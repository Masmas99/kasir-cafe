<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Cetak QR Meja - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white p-6 font-sans text-gray-900">
        <div class="mb-6 flex items-center justify-between print:hidden">
            <h1 class="text-lg font-semibold">Cetak QR Meja</h1>
            <button onclick="window.print()" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">Cetak</button>
        </div>

        <div class="grid grid-cols-2 gap-6 sm:grid-cols-3">
            @foreach ($tables as $table)
                <div class="flex flex-col items-center rounded-xl border-2 border-gray-900 p-6 text-center break-inside-avoid">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ config('app.name') }}</p>
                    <h2 class="mt-1 text-2xl font-bold">{{ $table->name }}</h2>
                    <div class="my-4">
                        {!! SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(180)->margin(1)->generate($table->orderUrl()) !!}
                    </div>
                    <p class="text-xs text-gray-600">Pindai untuk melihat menu &amp; memesan</p>
                </div>
            @endforeach
        </div>
    </body>
</html>
