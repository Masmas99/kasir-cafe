<?php

namespace App\Http\Controllers;

use App\Actions\CompleteMidtransPayment;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Midtrans\MidtransSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MidtransNotificationController extends Controller
{
    /**
     * Handle incoming Midtrans payment status notifications with SHA-512 signature verification and idempotency.
     */
    public function __invoke(Request $request, CompleteMidtransPayment $completeMidtransPayment): JsonResponse
    {
        $orderId = (string) $request->input('order_id', '');
        $statusCode = (string) $request->input('status_code', '');
        $grossAmount = (string) $request->input('gross_amount', '');
        $signatureKey = (string) $request->input('signature_key', '');
        $transactionStatus = strtolower((string) $request->input('transaction_status', ''));
        $fraudStatus = strtolower((string) $request->input('fraud_status', 'accept'));

        if ($orderId === '') {
            return response()->json(['message' => 'Missing order_id.'], Response::HTTP_BAD_REQUEST);
        }

        $isValid = MidtransSignature::verify(
            $signatureKey,
            $orderId,
            $statusCode,
            $grossAmount,
            (string) config('midtrans.server_key'),
        );

        if (! $isValid) {
            return response()->json(['message' => 'Invalid signature.'], Response::HTTP_UNAUTHORIZED);
        }

        return DB::transaction(function () use (
            $request,
            $orderId,
            $transactionStatus,
            $fraudStatus,
            $completeMidtransPayment,
        ): JsonResponse {
            /** @var Payment|null $payment */
            $payment = Payment::query()
                ->with('tableSession.diningTable')
                ->where('invoice_number', $orderId)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                return response()->json(['message' => 'Payment not found.'], Response::HTTP_NOT_FOUND);
            }

            if ($payment->status === PaymentStatus::Paid) {
                return response()->json(['message' => 'Already processed.']);
            }

            $isSuccess = $transactionStatus === 'settlement'
                || ($transactionStatus === 'capture' && $fraudStatus === 'accept');

            if ($isSuccess) {
                $completeMidtransPayment->handle(
                    $payment,
                    $request->all(),
                    (string) $request->input('transaction_id', $payment->invoice_number),
                );

                return response()->json(['message' => 'OK']);
            }

            if (in_array($transactionStatus, ['deny', 'cancel', 'expire', 'failure'], true)) {
                $payment->update([
                    'status' => PaymentStatus::Failed,
                    'payload' => array_merge($payment->payload ?? [], $request->all()),
                ]);

                $session = $payment->tableSession;
                if (! $session->isPaid()) {
                    $session->update([
                        'payment_status' => PaymentStatus::Failed,
                    ]);
                }
            }

            return response()->json(['message' => 'OK']);
        });
    }
}
