<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Models\ActivityLog;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class CompleteMidtransPayment
{
    /**
     * Mark a Midtrans payment and its table session as paid and release the table idempotently.
     *
     * @param  array<string, mixed>  $notificationPayload
     */
    public function handle(Payment $payment, array $notificationPayload = [], ?string $reference = null): Payment
    {
        return DB::transaction(function () use ($payment, $notificationPayload, $reference): Payment {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->with('tableSession.diningTable')
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($lockedPayment->status === PaymentStatus::Paid) {
                return $lockedPayment;
            }

            $mergedPayload = array_merge($lockedPayment->payload ?? [], $notificationPayload);

            $lockedPayment->update([
                'status' => PaymentStatus::Paid,
                'amount_received' => $lockedPayment->amount,
                'change_amount' => 0,
                'reference' => $reference ?? (string) ($notificationPayload['transaction_id'] ?? $lockedPayment->invoice_number),
                'payload' => $mergedPayload,
                'paid_at' => now(),
            ]);

            $session = $lockedPayment->tableSession;

            if (! $session->isPaid()) {
                $session->update([
                    'payment_status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                    'closed_at' => now(),
                ]);

                $session->diningTable->update([
                    'status' => TableStatus::Available,
                ]);

                ActivityLog::create([
                    'user_id' => $lockedPayment->recorded_by_user_id,
                    'action' => 'midtrans_payment_succeeded',
                    'subject_type' => Payment::class,
                    'subject_id' => $lockedPayment->id,
                    'description' => "Pembayaran QRIS Midtrans berhasil untuk {$session->diningTable->name} ({$lockedPayment->invoice_number})",
                    'properties' => [
                        'amount' => $lockedPayment->amount,
                        'invoice_number' => $lockedPayment->invoice_number,
                    ],
                ]);
            }

            return $lockedPayment->refresh();
        });
    }
}
