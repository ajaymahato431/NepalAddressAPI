<?php

namespace Tests\Unit;

use App\Services\Nepal\NepaliNumeral;
use PHPUnit\Framework\TestCase;

class NepaliNumeralTest extends TestCase
{
    public function test_converts_single_digits(): void
    {
        $this->assertSame('०', NepaliNumeral::toNepali(0));
        $this->assertSame('९', NepaliNumeral::toNepali(9));
    }

    public function test_converts_multi_digit_numbers(): void
    {
        $this->assertSame('२९', NepaliNumeral::toNepali(29));
        $this->assertSame('६७४३', NepaliNumeral::toNepali(6743));
    }

    public function test_preserves_decimal_points(): void
    {
        $this->assertSame('४३२.९५', NepaliNumeral::toNepali('432.95'));
    }

    public function test_converts_back_to_arabic(): void
    {
        $this->assertSame('29', NepaliNumeral::toArabic('२९'));
        $this->assertSame('432.95', NepaliNumeral::toArabic('४३२.९५'));
    }

    public function test_round_trips_every_number_up_to_1000(): void
    {
        for ($i = 0; $i <= 1000; $i++) {
            $this->assertSame(
                (string) $i,
                NepaliNumeral::toArabic(NepaliNumeral::toNepali($i)),
                "Round trip failed for {$i}"
            );
        }
    }

    public function test_leaves_non_numeric_text_alone(): void
    {
        $this->assertSame('काठमाडौं', NepaliNumeral::toNepali('काठमाडौं'));
    }
}
