<?php

namespace App\Services\Midtrans;

class MidtransSignature
{
    /**
     * Generate the SHA-512 signature key for a Midtrans notification payload.
     */
    public static function generate(
        string $orderId,
        string $statusCode,
        string $grossAmount,
        string $serverKey,
    ): string {
        return hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
    }

    /**
     * Verify an incoming Midtrans notification signature in constant time.
     */
    public static function verify(
        string $providedSignature,
        string $orderId,
        string $statusCode,
        string $grossAmount,
        string $serverKey,
    ): bool {
        if ($providedSignature === '' || $serverKey === '') {
            return false;
        }

        $expected = self::generate($orderId, $statusCode, $grossAmount, $serverKey);

        return hash_equals($expected, $providedSignature);
    }
}
