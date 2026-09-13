<?php

namespace App\Services\Nepal;

class NepaliNumeral
{
    private const ARABIC = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    private const DEVANAGARI = ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९'];

    /**
     * Convert ASCII digits to Devanagari, leaving all other characters intact.
     */
    public static function toNepali(int|string $value): string
    {
        return str_replace(self::ARABIC, self::DEVANAGARI, (string) $value);
    }

    /**
     * Convert Devanagari digits to ASCII, leaving all other characters intact.
     */
    public static function toArabic(string $value): string
    {
        return str_replace(self::DEVANAGARI, self::ARABIC, $value);
    }
}
