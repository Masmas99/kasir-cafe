<?php

namespace App\Actions;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Midtrans\MidtransClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateMidtransQrisPayment
{
    /**
     * Create or return a pending Midtrans QRIS payment for a table session.
     *
     * @throws ValidationException
     */
    public function handle(
        TableSession $tableSession,
        MidtransClient $midtransClient,
        ?User $recordedBy = null,
    ): Payment {
        return DB::transaction(function () use ($tableSession, $midtransClient, $recordedBy): Payment {
            /** @var TableSession $session */
            $session = TableSession::query()->lockForUpdate()->findOrFail($tableSession->id);

            $session->recalculateTotal();
            $session->refresh();

            if ($session->isPaid()) {
                throw ValidationException::withMessages([
                    'payment' => 'Tagihan sesi meja ini sudah lunas.',
                ]);
            }

            if (! $session->isOpen() || $session->total_amount <= 0) {
                throw ValidationException::withMessages([
                    'payment' => 'Tidak ada tagihan aktif untuk dibayar.',
                ]);
            }

            /** @var Payment|null $existingPending */
            $existingPending = $session->payments()
                ->where('method', PaymentMethod::Qris)
                ->where('status', PaymentStatus::Pending)
                ->where('amount', $session->total_amount)
                ->latest('id')
                ->first();

            if (
                $existingPending !== null
                && ! empty($existingPending->payload['qr_string'])
                && ! str_starts_with((string) $existingPending->payload['qr_string'], 'http')
            ) {
                $remoteStatus = $midtransClient->getTransactionStatus($existingPending->invoice_number);

                if ($remoteStatus !== null) {
                    $txStatus = strtolower((string) ($remoteStatus['transaction_status'] ?? ''));
                    $fraudStatus = strtolower((string) ($remoteStatus['fraud_status'] ?? 'accept'));

                    if ($txStatus === 'settlement' || ($txStatus === 'capture' && $fraudStatus === 'accept')) {
                        return app(CompleteMidtransPayment::class)->handle(
                            $existingPending,
                            $remoteStatus,
                            (string) ($remoteStatus['transaction_id'] ?? $existingPending->invoice_number),
                        );
                    }

                    if (in_array($txStatus, ['expire', 'cancel', 'deny', 'failure'], true)) {
                        $existingPending->update(['status' => PaymentStatus::Failed]);
                        $existingPending = null;
                    }
                }

                if ($existingPending !== null) {
                    return $existingPending;
                }
            }

            $payment = Payment::create([
                'table_session_id' => $session->id,
                'recorded_by_user_id' => $recordedBy?->id,
                'method' => PaymentMethod::Qris,
                'status' => PaymentStatus::Pending,
                'amount' => $session->total_amount,
                'amount_received' => $session->total_amount,
                'change_amount' => 0,
            ]);

            $result = $midtransClient->createQrisCharge($payment);

            $payment->update([
                'payment_url' => $result['qr_url'],
                'payload' => array_merge($result['response'], [
                    'qr_string' => $result['qr_string'],
                    'qr_url' => $result['qr_url'],
                ]),
            ]);

            $session->update([
                'payment_status' => PaymentStatus::Pending,
            ]);

            return $payment->refresh();
        });
    }
}
