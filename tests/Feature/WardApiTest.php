<?php

namespace Tests\Feature;

use Tests\TestCase;

class WardApiTest extends TestCase
{
    public function test_lists_wards_for_a_municipality(): void
    {
        $response = $this->getJson('/api/wards/chitwan/bharatpur');

        $response->assertStatus(200)
            ->assertJsonStructure(['municipality', 'district', 'total_wards', 'wards']);

        $total = $response->json('total_wards');
        $this->assertSame(29, $total);
        $this->assertCount($total, $response->json('wards'));
        $this->assertSame(1, $response->json('wards.0.ward'));
        $this->assertSame($total, $response->json('wards.'.($total - 1).'.ward'));
    }

    public function test_wards_accept_the_legacy_municipality_name(): void
    {
        $a = $this->getJson('/api/wards/chitwan/bharatpur');
        $b = $this->getJson('/api/wards/chitwan/bharatpur-metropolitan-city');

        $b->assertStatus(200);
        $this->assertSame($a->json('total_wards'), $b->json('total_wards'));
    }

    public function test_wards_in_nepali_use_devanagari_numerals(): void
    {
        $response = $this->getJson('/api/wards/chitwan/bharatpur?lang=np');

        $response->assertStatus(200);
        $this->assertSame('१', $response->json('wards.0.ward'));
        $this->assertSame('२', $response->json('wards.1.ward'));
        $this->assertSame('२९', $response->json('wards.28.ward'));
    }

    public function test_wards_in_both_languages_carry_paired_numerals(): void
    {
        $response = $this->getJson('/api/wards/chitwan/bharatpur?lang=both');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('wards.0.ward'));
        $this->assertSame('१', $response->json('wards.0.ward_np'));
    }

    public function test_unknown_municipality_returns_404(): void
    {
        $this->getJson('/api/wards/chitwan/springfield')
            ->assertStatus(404)
            ->assertJson(['error' => 'Municipality not found']);
    }

    public function test_unknown_district_returns_404(): void
    {
        $this->getJson('/api/wards/gotham/springfield')->assertStatus(404);
    }

    public function test_ward_route_resists_path_traversal(): void
    {
        $response = $this->getJson('/api/wards/../../etc/passwd/x');
        $this->assertContains($response->status(), [404, 400]);
    }

    public function test_municipality_detail(): void
    {
        $response = $this->getJson('/api/municipality/chitwan/bharatpur');

        $response->assertStatus(200)
            ->assertJsonStructure(['municipality', 'district', 'province']);

        $this->assertSame('Bharatpur', $response->json('municipality.name'));
        $this->assertSame('Metropolitan City', $response->json('municipality.category'));
        $this->assertMatchesRegularExpression(
            '/\p{Devanagari}/u',
            $response->json('municipality.name_np')
        );
        $this->assertSame(29, $response->json('municipality.wards'));
    }

    public function test_categories_endpoint(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);
        $categories = $response->json('categories');

        $this->assertCount(4, $categories);
        $this->assertSame('Metropolitan City', $categories[0]['name']);
        $this->assertSame('महानगरपालिका', $categories[0]['name_np']);
        $this->assertSame('MC', $categories[0]['short_code']);
    }

    public function test_every_municipality_has_reachable_wards(): void
    {
        $districts = $this->getJson('/api/districts')->json('districts');
        $checked = 0;
        $wardSum = 0;

        foreach ($districts as $district) {
            $slug = str_replace(' ', '-', $district);
            $municipals = $this->getJson("/api/municipals/{$slug}?detailed=true")->json('municipals');

            foreach ($municipals as $municipality) {
                $response = $this->getJson("/api/wards/{$slug}/{$municipality['slug']}");
                $this->assertSame(
                    200,
                    $response->status(),
                    "Wards unreachable for {$municipality['slug']} in {$slug}"
                );
                $wardSum += $response->json('total_wards');
                $checked++;
            }
        }

        $this->assertSame(753, $checked);
        $this->assertSame(6743, $wardSum);
    }
}
