<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qris = 'qris';
    case Debit = 'debit';

    /**
     * Get the human readable label for the payment method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Qris => 'QRIS (Midtrans)',
            self::Debit => 'Kartu Debit',
        };
    }

    /**
     * Get the payment methods available for manual recording at the cashier desk.
     *
     * @return array<int, self>
     */
    public static function cashierMethods(): array
    {
        return [
            self::Cash,
            self::Qris,
            self::Debit,
        ];
    }
}
