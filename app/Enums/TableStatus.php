<?php

namespace App\Enums;

enum TableStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Paid = 'paid';

    /**
     * Get the human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Occupied => 'Terisi',
            self::Paid => 'Lunas (menunggu ditutup)',
        };
    }

    /**
     * Get the badge color classes for the status.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Available => 'bg-green-100 text-green-800 dark:bg-green-500/10 dark:text-green-400',
            self::Occupied => 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-400',
            self::Paid => 'bg-blue-100 text-blue-800 dark:bg-blue-500/10 dark:text-blue-400',
        };
    }
}
