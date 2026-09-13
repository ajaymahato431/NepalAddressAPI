<?php

namespace Tests\Feature;

use Tests\TestCase;

class QueryParameterTest extends TestCase
{
    public function test_rejects_unknown_lang(): void
    {
        $this->getJson('/api/districts?lang=fr')
            ->assertStatus(422)
            ->assertJsonStructure(['error', 'accepted']);
    }

    public function test_rejects_unknown_case(): void
    {
        $this->getJson('/api/districts?case=shouty')
            ->assertStatus(422)
            ->assertJsonStructure(['error', 'accepted']);
    }

    public function test_accepts_every_valid_lang(): void
    {
        foreach (['en', 'np', 'both'] as $lang) {
            $this->getJson("/api/districts?lang={$lang}")->assertStatus(200);
        }
    }

    public function test_flat_mode_defaults_to_english(): void
    {
        $districts = $this->getJson('/api/districts')->json('districts');

        $this->assertIsString($districts[0]);
        $this->assertContains('kathmandu', $districts);
    }

    public function test_districts_are_returned_alphabetically(): void
    {
        $districts = $this->getJson('/api/districts')->json('districts');
        $sorted = $districts;
        sort($sorted);

        $this->assertSame($sorted, $districts, '/api/districts must stay alphabetical');
        $this->assertSame('achham', $districts[0]);
    }

    public function test_detailed_mode_defaults_to_both_languages(): void
    {
        $districts = $this->getJson('/api/districts?detailed=true')->json('districts');

        $this->assertIsArray($districts[0]);
        $this->assertArrayHasKey('name', $districts[0]);
        $this->assertArrayHasKey('name_np', $districts[0]);
    }

    public function test_lang_np_returns_devanagari_strings(): void
    {
        $districts = $this->getJson('/api/districts?lang=np')->json('districts');

        $this->assertIsString($districts[0]);
        $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $districts[0]);
    }

    public function test_lang_both_returns_paired_objects(): void
    {
        $districts = $this->getJson('/api/districts?lang=both')->json('districts');

        $this->assertArrayHasKey('name', $districts[0]);
        $this->assertArrayHasKey('name_np', $districts[0]);
    }

    public function test_detailed_districts_carry_ward_totals(): void
    {
        $districts = $this->getJson('/api/districts?detailed=true')->json('districts');

        $kathmandu = collect($districts)->firstWhere('slug', 'kathmandu');
        $this->assertSame(11, $kathmandu['total_municipalities']);
        $this->assertSame(138, $kathmandu['total_wards']);
    }

    public function test_detailed_accepts_truthy_spellings(): void
    {
        foreach (['true', '1'] as $value) {
            $districts = $this->getJson("/api/districts?detailed={$value}")->json('districts');
            $this->assertIsArray($districts[0], "detailed={$value} did not enable detailed mode");
        }

        foreach (['false', '0'] as $value) {
            $districts = $this->getJson("/api/districts?detailed={$value}")->json('districts');
            $this->assertIsString($districts[0], "detailed={$value} should stay flat");
        }
    }

    public function test_every_endpoint_accepts_lang_np(): void
    {
        $uris = [
            '/api/provinces?lang=np',
            '/api/districts?lang=np',
            '/api/districts/bagmati?lang=np',
            '/api/municipals/chitwan?lang=np',
            '/api/stats?lang=np',
            '/api/categories?lang=np',
            '/api/wards/chitwan/bharatpur?lang=np',
            '/api/municipality/chitwan/bharatpur?lang=np',
        ];

        foreach ($uris as $uri) {
            $this->getJson($uri)->assertStatus(200, "{$uri} failed");
        }
    }

    public function test_stats_reports_ward_totals_and_categories(): void
    {
        $response = $this->getJson('/api/stats');

        $response->assertStatus(200)
            ->assertJson([
                'total_provinces' => 7,
                'total_districts' => 77,
                'total_municipalities' => 753,
                'total_wards' => 6743,
            ]);

        $this->assertCount(4, $response->json('municipalities_by_category'));
        $this->assertSame(
            753,
            array_sum(array_column($response->json('municipalities_by_category'), 'count'))
        );
        $this->assertSame(
            6743,
            array_sum(array_column($response->json('provinces_breakdown'), 'wards_count'))
        );
    }
}
