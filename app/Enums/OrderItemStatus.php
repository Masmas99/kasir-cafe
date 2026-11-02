<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';

    /**
     * Get the human readable label for the item status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
