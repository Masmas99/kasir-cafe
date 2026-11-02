<?php

namespace App\Enums;

enum ThemePreference: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';

    /**
     * Get the human readable label for the theme.
     */
    public function label(): string
    {
        return match ($this) {
            self::Light => 'Terang',
            self::Dark => 'Gelap',
            self::System => 'Ikuti Perangkat',
        };
    }
}
