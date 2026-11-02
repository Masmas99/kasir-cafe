<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';

    /**
     * Get the human readable label for the payment status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum Dibayar',
            self::Pending => 'Menunggu Pembayaran',
            self::Paid => 'Lunas',
            self::Failed => 'Gagal / Kedaluwarsa',
        };
    }

    /**
     * Get the badge color classes for the payment status.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Unpaid => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            self::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-400',
            self::Paid => 'bg-green-100 text-green-800 dark:bg-green-500/10 dark:text-green-400',
            self::Failed => 'bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-400',
        };
    }
}
