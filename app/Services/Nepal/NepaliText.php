<?php

namespace App\Services\Nepal;

class NepaliText
{
    /**
     * Fold anusvara (U+0902) to chandrabindu (U+0901) so the two common
     * spellings of nasalized vowels compare equal. The dataset itself is
     * inconsistent (33 records carry U+0901, 40 carry U+0902), and user
     * input may use either mark. Narrow on purpose: only this one fold,
     * nothing broader. Does not alter stored values, only comparison.
     */
    public static function foldForComparison(string $text): string
    {
        return str_replace("\u{0902}", "\u{0901}", $text);
    }
}
