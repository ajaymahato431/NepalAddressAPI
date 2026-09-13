<?php

namespace Tests\Feature;

use Tests\TestCase;

class NepaliSearchTest extends TestCase
{
    public function test_finds_a_district_by_its_nepali_name(): void
    {
        $response = $this->getJson('/api/search?q='.urlencode('काठमाडौँ'));

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
        $this->assertContains('district', array_column($response->json('results'), 'type'));
    }

    public function test_finds_a_district_by_either_nasalization_mark(): void
    {
        // The dataset stores Kathmandu with chandrabindu (U+0901); a user typing
        // the anusvara form (U+0902) must still find it.
        $chandrabindu = $this->getJson('/api/search?q='.urlencode("काठमाडौ\u{0901}"));
        $anusvara = $this->getJson('/api/search?q='.urlencode("काठमाडौ\u{0902}"));

        $chandrabindu->assertStatus(200);
        $anusvara->assertStatus(200);

        $this->assertGreaterThanOrEqual(1, $anusvara->json('total'));
        $this->assertSame($chandrabindu->json('total'), $anusvara->json('total'));
    }

    public function test_finds_a_province_by_its_nepali_name(): void
    {
        $response = $this->getJson('/api/search?q='.urlencode('बाग्मती'));

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    public function test_nepali_search_can_return_nepali_results(): void
    {
        $response = $this->getJson('/api/search?q='.urlencode('काठमाडौँ').'&lang=np');

        $response->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/\p{Devanagari}/u',
            $response->json('results.0.name')
        );
    }

    public function test_english_search_still_works(): void
    {
        $response = $this->getJson('/api/search?q=bharatpur');

        $response->assertStatus(200);
        $this->assertSame('bharatpur metropolitan city', $response->json('results.0.name'));
        $this->assertSame('municipality', $response->json('results.0.type'));
        $this->assertSame('chitwan', $response->json('results.0.district'));
        $this->assertSame('bagmati', $response->json('results.0.province'));
    }

    public function test_detailed_search_results_carry_wards(): void
    {
        $response = $this->getJson('/api/search?q=bharatpur&detailed=true');

        $response->assertStatus(200);
        $this->assertArrayHasKey('wards', $response->json('results.0.name'));
    }

    public function test_search_respects_the_limit(): void
    {
        $response = $this->getJson('/api/search?q=a&limit=5');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, count($response->json('results')));
    }

    public function test_search_is_fast(): void
    {
        $start = microtime(true);
        $this->getJson('/api/search?q=rural')->assertStatus(200);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(2.0, $elapsed, 'Search took over 2s; the index is not being used');
    }
}
