<?php

namespace App\Actions;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordCashierPayment
{
    /**
     * Record a payment at the cashier desk, complete the session, and make the table available.
     *
     * @throws ValidationException
     */
    public function handle(
        TableSession $tableSession,
        User $cashier,
        PaymentMethod $method,
        int $amountReceived,
        ?string $reference = null,
    ): Payment {
        return DB::transaction(function () use ($tableSession, $cashier, $method, $amountReceived, $reference): Payment {
            /** @var TableSession $session */
            $session = TableSession::query()->with('diningTable')->lockForUpdate()->findOrFail($tableSession->id);

            $session->recalculateTotal();
            $session->refresh();

            if ($session->isPaid()) {
                throw ValidationException::withMessages([
                    'payment' => 'Tagihan sesi meja ini sudah lunas.',
                ]);
            }

            if ($session->total_amount <= 0) {
                throw ValidationException::withMessages([
                    'payment' => 'Tidak ada tagihan aktif yang perlu dibayar.',
                ]);
            }

            if ($amountReceived < $session->total_amount) {
                throw ValidationException::withMessages([
                    'amount_received' => 'Nominal pembayaran kurang dari total tagihan.',
                ]);
            }

            $changeAmount = max(0, $amountReceived - $session->total_amount);

            $payment = Payment::create([
                'table_session_id' => $session->id,
                'recorded_by_user_id' => $cashier->id,
                'method' => $method,
                'status' => PaymentStatus::Paid,
                'amount' => $session->total_amount,
                'amount_received' => $amountReceived,
                'change_amount' => $changeAmount,
                'reference' => $reference,
                'paid_at' => now(),
            ]);

            $session->update([
                'payment_status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'closed_at' => now(),
                'closed_by' => $cashier->id,
            ]);

            $session->diningTable->update([
                'status' => TableStatus::Available,
            ]);

            ActivityLog::create([
                'user_id' => $cashier->id,
                'action' => 'record_payment',
                'subject_type' => TableSession::class,
                'subject_id' => $session->id,
                'description' => "Mencatat pembayaran {$method->label()} untuk {$session->diningTable->name}",
                'properties' => [
                    'amount' => $payment->amount,
                    'amount_received' => $payment->amount_received,
                    'change_amount' => $payment->change_amount,
                    'method' => $method->value,
                ],
            ]);

            return $payment;
        });
    }
}
