<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Struk {{ $session->diningTable->name }} - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-100 p-6 font-mono text-gray-900 print:bg-white print:p-0">
        <div class="mx-auto max-w-sm rounded-xl bg-white p-6 shadow print:shadow-none">
            <div class="mb-4 flex justify-end print:hidden">
                <button onclick="window.print()" class="rounded bg-gray-900 px-3 py-1.5 font-sans text-xs font-semibold text-white">Cetak Struk</button>
            </div>

            <div class="border-b border-dashed border-gray-400 pb-3 text-center">
                <h1 class="text-base font-bold uppercase">{{ config('app.name') }}</h1>
                <p class="text-xs text-gray-600">{{ $session->diningTable->name }} &bull; {{ $session->opened_at->format('d/m/Y H:i') }}</p>
            </div>

            <div class="divide-y divide-dashed divide-gray-300 py-3 text-xs">
                @foreach ($session->orders as $order)
                    @if ($order->status->value !== 'cancelled')
                        <div class="py-2">
                            <p class="font-semibold">{{ $order->order_number }}</p>
                            @foreach ($order->items as $item)
                                @if ($item->status->value === 'active')
                                    <div class="mt-1 flex justify-between">
                                        <span>{{ $item->quantity }}x {{ $item->item_name }}</span>
                                        <x-rupiah :amount="$item->subtotal" />
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="space-y-1 border-t border-dashed border-gray-400 pt-3 text-xs">
                <div class="flex justify-between text-sm font-bold">
                    <span>TOTAL</span>
                    <x-rupiah :amount="$session->total_amount" />
                </div>

                @if ($latestPayment)
                    <div class="flex justify-between">
                        <span>Metode ({{ $latestPayment->method->label() }})</span>
                        <x-rupiah :amount="$latestPayment->amount_received ?? $latestPayment->amount" />
                    </div>
                    <div class="flex justify-between">
                        <span>Kembalian</span>
                        <x-rupiah :amount="$latestPayment->change_amount" />
                    </div>
                @endif

                <div class="pt-2 text-center text-gray-500">
                    Status: {{ $session->payment_status->label() }}
                </div>
            </div>
        </div>
    </body>
</html>
