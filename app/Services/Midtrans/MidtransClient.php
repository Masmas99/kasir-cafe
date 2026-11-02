<?php

namespace App\Services\Midtrans;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransClient
{
    /**
     * Create a Midtrans QRIS charge (via Core API, falling back to Snap API when Core API channel is not enabled).
     *
     * @return array{qr_string: string, qr_url: string|null, response: array<string, mixed>}
     */
    public function createQrisCharge(Payment $payment): array
    {
        $serverKey = trim((string) config('midtrans.server_key'));

        if ($serverKey === '') {
            $sandboxQrString = '00020101021226590014ID.CO.MIDTRANS.SANDBOX0215'.$payment->invoice_number.'5303360540'.strlen((string) $payment->amount).$payment->amount.'5802ID5914KASIR MEJA POS6304DEMO';

            return [
                'qr_string' => $sandboxQrString,
                'qr_url' => null,
                'response' => [
                    'status_code' => '201',
                    'transaction_status' => 'pending',
                    'payment_type' => 'qris',
                    'order_id' => $payment->invoice_number,
                    'gross_amount' => (string) $payment->amount,
                    'qr_string' => $sandboxQrString,
                ],
            ];
        }

        $baseUrl = config('midtrans.is_production')
            ? (string) config('midtrans.production_base_url')
            : (string) config('midtrans.sandbox_base_url');

        $payload = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $payment->invoice_number,
                'gross_amount' => $payment->amount,
            ],
            'qris' => [
                'acquirer' => 'gopay',
            ],
        ];

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->post(rtrim($baseUrl, '/').'/v2/charge', $payload);

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        $statusCode = (string) ($data['status_code'] ?? '');

        if ($response->successful() && in_array($statusCode, ['', '200', '201'], true)) {
            $qrString = (string) ($data['qr_string'] ?? '');
            $qrUrl = null;

            foreach ((array) ($data['actions'] ?? []) as $action) {
                if (($action['name'] ?? '') === 'generate-qr-code' && ! empty($action['url'])) {
                    $qrUrl = (string) $action['url'];
                    break;
                }
            }

            if ($qrString !== '' || $qrUrl !== null) {
                return [
                    'qr_string' => $qrString !== '' ? $qrString : (string) $qrUrl,
                    'qr_url' => $qrUrl,
                    'response' => $data,
                ];
            }
        }

        // Fallback to Midtrans Snap API when Core API QRIS channel is not activated (402)
        return $this->createSnapQrisTransaction($payment, $serverKey);
    }

    /**
     * Create a Midtrans Snap transaction and return its payment link as the scannable QR payload.
     *
     * @return array{qr_string: string, qr_url: string|null, response: array<string, mixed>}
     */
    protected function createSnapQrisTransaction(Payment $payment, string $serverKey): array
    {
        $snapUrl = config('midtrans.is_production')
            ? (string) config('midtrans.production_snap_url')
            : (string) config('midtrans.sandbox_snap_url');

        $snapResponse = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->post($snapUrl, [
                'transaction_details' => [
                    'order_id' => $payment->invoice_number,
                    'gross_amount' => $payment->amount,
                ],
            ]);

        if (! $snapResponse->successful()) {
            throw new RuntimeException('Gagal menghubungi layanan pembayaran Midtrans.');
        }

        /** @var array<string, mixed> $snapData */
        $snapData = $snapResponse->json() ?? [];
        $redirectUrl = (string) ($snapData['redirect_url'] ?? '');

        if ($redirectUrl === '') {
            throw new RuntimeException('URL pembayaran Midtrans tidak ditemukan.');
        }

        return [
            'qr_string' => $redirectUrl,
            'qr_url' => null,
            'response' => array_merge($snapData, [
                'order_id' => $payment->invoice_number,
                'gross_amount' => (string) $payment->amount,
                'snap_redirect_url' => $redirectUrl,
            ]),
        ];
    }

    /**
     * Query the transaction status of an invoice from Midtrans Core API.
     *
     * @return array<string, mixed>|null
     */
    public function getTransactionStatus(string $invoiceNumber): ?array
    {
        $serverKey = trim((string) config('midtrans.server_key'));

        if ($serverKey === '') {
            return null;
        }

        $baseUrl = config('midtrans.is_production')
            ? (string) config('midtrans.production_base_url')
            : (string) config('midtrans.sandbox_base_url');

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->timeout(10)
            ->get(rtrim($baseUrl, '/').'/v2/'.urlencode($invoiceNumber).'/status');

        if (! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }
}
