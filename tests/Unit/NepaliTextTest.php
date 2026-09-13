<?php

namespace Tests\Unit;

use App\Services\Nepal\NepaliText;
use PHPUnit\Framework\TestCase;

class NepaliTextTest extends TestCase
{
    public function test_folds_anusvara_to_chandrabindu(): void
    {
        // U+0902 (anusvara) must fold to U+0901 (chandrabindu).
        $anusvara = "\u{0902}";
        $chandrabindu = "\u{0901}";

        $this->assertSame($chandrabindu, NepaliText::foldForComparison($anusvara));
    }

    public function test_leaves_chandrabindu_unchanged(): void
    {
        $chandrabindu = "\u{0901}";

        $this->assertSame($chandrabindu, NepaliText::foldForComparison($chandrabindu));
    }

    public function test_folds_within_a_full_word_so_both_spellings_compare_equal(): void
    {
        $base = 'काठमाडौ';
        $withChandrabindu = $base."\u{0901}"; // काठमाडौँ
        $withAnusvara = $base."\u{0902}";     // काठमाडौं

        $this->assertNotSame($withChandrabindu, $withAnusvara, 'Precondition: the two spellings must differ before folding.');
        $this->assertSame(
            NepaliText::foldForComparison($withChandrabindu),
            NepaliText::foldForComparison($withAnusvara)
        );
    }

    public function test_does_not_touch_unrelated_characters(): void
    {
        $this->assertSame('kathmandu', NepaliText::foldForComparison('kathmandu'));
    }
}
