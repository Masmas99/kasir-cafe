<?php

use App\Actions\RecordCashierPayment;
use App\Enums\PaymentMethod;
use App\Models\DiningTable;
use App\Models\TableSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Status Meja')] class extends Component
{
    public ?int $payingSessionId = null;

    public string $paymentMethod = 'cash';

    public ?int $amountReceived = null;

    public string $reference = '';

    /**
     * Open the payment form for a table's active session.
     */
    public function openPaymentModal(int $sessionId): void
    {
        $session = TableSession::findOrFail($sessionId);
        $this->payingSessionId = $session->id;
        $this->paymentMethod = PaymentMethod::Cash->value;
        $this->amountReceived = $session->total_amount;
        $this->reference = '';
        $this->resetValidation();
    }

    /**
     * Process the cashier payment for the selected session.
     */
    public function processPayment(RecordCashierPayment $recordCashierPayment): void
    {
        $validated = $this->validate([
            'payingSessionId' => ['required', 'integer', 'exists:table_sessions,id'],
            'paymentMethod' => ['required', Rule::enum(PaymentMethod::class)],
            'amountReceived' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $session = TableSession::with('diningTable')->findOrFail($validated['payingSessionId']);

        $recordCashierPayment->handle(
            $session,
            Auth::user(),
            PaymentMethod::from($validated['paymentMethod']),
            (int) $validated['amountReceived'],
            $validated['reference'] !== '' ? $validated['reference'] : null,
        );

        session()->flash('status', "Pembayaran {$session->diningTable->name} berhasil dicatat. Meja berstatus Lunas.");

        $this->cancelPaymentModal();
    }

    /**
     * Close the payment modal.
     */
    public function cancelPaymentModal(): void
    {
        $this->reset('payingSessionId', 'paymentMethod', 'amountReceived', 'reference');
        $this->resetValidation();
    }

    /**
     * Get the data passed to the view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'tables' => DiningTable::with(['activeSession.orders.items'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'cashierMethods' => PaymentMethod::cashierMethods(),
        ];
    }
}; ?>

<div wire:poll.5s>
    <x-page-header title="Status Meja & Pembayaran" description="Proses pembayaran di kasir dan tutup meja setelah pelanggan selesai." />

    <div class="space-y-4 p-4 sm:p-6 lg:p-8">
        <x-flash-message />
        <x-input-error :messages="$errors->get('table')" />
        <x-input-error :messages="$errors->get('payment')" />

        @if ($payingSessionId)
            @php($payingSession = TableSession::with('diningTable')->find($payingSessionId))
            @if ($payingSession)
                <x-card class="border-brand-500 p-6">
                    <h2 class="text-base font-bold">Proses Pembayaran — {{ $payingSession->diningTable->name }}</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Total Tagihan: <strong class="text-brand-600 dark:text-brand-400"><x-rupiah :amount="$payingSession->total_amount" /></strong>
                    </p>

                    <form wire:submit="processPayment" class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="paymentMethod" value="Metode Pembayaran" />
                            <x-select-input wire:model="paymentMethod" id="paymentMethod" class="mt-1 block w-full">
                                @foreach ($cashierMethods as $methodOption)
                                    <option value="{{ $methodOption->value }}">{{ $methodOption->label() }}</option>
                                @endforeach
                            </x-select-input>
                        </div>

                        <div>
                            <x-input-label for="amountReceived" value="Nominal Diterima (Rp)" />
                            <x-text-input wire:model="amountReceived" id="amountReceived" type="number" min="0" class="mt-1 block w-full" required />
                            <x-input-error :messages="$errors->get('amount_received')" class="mt-1" />
                            <x-input-error :messages="$errors->get('amountReceived')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="reference" value="Referensi / Catatan (opsional)" />
                            <x-text-input wire:model="reference" id="reference" class="mt-1 block w-full" placeholder="No. ref QRIS/EDC" />
                        </div>

                        <div class="flex gap-2 sm:col-span-3">
                            <x-primary-button>Tandai Lunas</x-primary-button>
                            <x-secondary-button wire:click="cancelPaymentModal">Batal</x-secondary-button>
                        </div>
                    </form>
                </x-card>
            @endif
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($tables as $table)
                @php($session = $table->activeSession)
                <x-card wire:key="cashier-table-{{ $table->id }}" class="flex flex-col p-4">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="text-base font-bold">{{ $table->name }}</h3>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $table->status->badgeClasses() }}">
                            {{ $table->status->label() }}
                        </span>
                    </div>

                    @if ($session)
                        <div class="my-4 space-y-1 text-sm">
                            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                <span>Pesanan</span>
                                <span>{{ $session->orders->count() }} pesanan</span>
                            </div>
                            <div class="flex justify-between font-bold">
                                <span>Total Tagihan</span>
                                <span class="text-brand-600 dark:text-brand-400"><x-rupiah :amount="$session->total_amount" /></span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>Status Bayar</span>
                                <span>{{ $session->payment_status->label() }}</span>
                            </div>
                        </div>

                        <div class="mt-auto flex flex-wrap gap-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                            @if (! $session->isPaid())
                                <button
                                    type="button"
                                    wire:click="openPaymentModal({{ $session->id }})"
                                    class="flex-1 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-500"
                                >
                                    Proses Bayar
                                </button>
                            @endif

                            <a
                                href="{{ route('cashier.receipts.show', $session) }}"
                                target="_blank"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                Struk
                            </a>
                        </div>
                    @else
                        <p class="my-6 text-center text-xs text-gray-400">Meja kosong &amp; siap digunakan.</p>
                    @endif
                </x-card>
            @endforeach
        </div>
    </div>
</div>
