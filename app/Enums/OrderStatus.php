<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';

    /**
     * Get the human readable label for the order status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Processing => 'Diproses',
            self::Ready => 'Siap',
            self::Served => 'Disajikan',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Get the badge color classes for the order status.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-400',
            self::Processing => 'bg-blue-100 text-blue-800 dark:bg-blue-500/10 dark:text-blue-400',
            self::Ready => 'bg-purple-100 text-purple-800 dark:bg-purple-500/10 dark:text-purple-400',
            self::Served => 'bg-green-100 text-green-800 dark:bg-green-500/10 dark:text-green-400',
            self::Cancelled => 'bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-400',
        };
    }
}
