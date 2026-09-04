<?php

namespace Tests\Feature;

use Tests\TestCase;

class AddressApiTest extends TestCase
{
    public function test_can_get_all_provinces(): void
    {
        $response = $this->getJson('/api/provinces');

        $response->assertStatus(200)
            ->assertHeader('Cache-Control')
            ->assertHeader('ETag')
            ->assertJsonStructure(['provinces']);

        $provinces = $response->json('provinces');
        $this->assertCount(7, $provinces);
        $this->assertContains('koshi', $provinces);
        $this->assertContains('bagmati', $provinces);
        $this->assertContains('gandaki', $provinces);
        $this->assertContains('lumbini', $provinces);
        $this->assertContains('karnali', $provinces);
        $this->assertContains('sudurpaschim', $provinces);
        $this->assertContains('madhesh', $provinces);
    }

    public function test_provinces_case_formatting(): void
    {
        $response = $this->getJson('/api/provinces?case=title');

        $response->assertStatus(200);
        $provinces = $response->json('provinces');
        $this->assertContains('Bagmati', $provinces);
        $this->assertContains('Koshi', $provinces);
    }

    public function test_can_get_all_districts(): void
    {
        $response = $this->getJson('/api/districts');

        $response->assertStatus(200)
            ->assertJsonStructure(['districts']);

        $districts = $response->json('districts');
        $this->assertCount(77, $districts);
        $this->assertContains('kathmandu', $districts);
        $this->assertContains('chitwan', $districts);
        $this->assertContains('sunsari', $districts);
        $this->assertContains('nawalpur', $districts);
        $this->assertContains('parasi', $districts);
        $this->assertContains('eastern rukum', $districts);
        $this->assertContains('western rukum', $districts);
    }

    public function test_can_get_districts_by_province(): void
    {
        // Bagmati
        $response = $this->getJson('/api/districts/bagmati');
        $response->assertStatus(200)
            ->assertJsonStructure(['districts']);
        $this->assertContains('kathmandu', $response->json('districts'));
        $this->assertContains('chitwan', $response->json('districts'));

        // Koshi & pradesh-1 alias
        $koshiResponse = $this->getJson('/api/districts/koshi');
        $koshiResponse->assertStatus(200);
        $this->assertContains('jhapa', $koshiResponse->json('districts'));

        $pradesh1Response = $this->getJson('/api/districts/pradesh-1');
        $pradesh1Response->assertStatus(200);
        $this->assertEquals($koshiResponse->json('districts'), $pradesh1Response->json('districts'));

        // Case insensitivity
        $upperResponse = $this->getJson('/api/districts/BAGMATI');
        $upperResponse->assertStatus(200);
        $this->assertEquals($response->json('districts'), $upperResponse->json('districts'));
    }

    public function test_invalid_province_returns_404(): void
    {
        $response = $this->getJson('/api/districts/atlantis');
        $response->assertStatus(404)
            ->assertJson(['error' => 'Province not found']);
    }

    public function test_can_get_municipals_by_district(): void
    {
        // Lowercase
        $response = $this->getJson('/api/municipals/chitwan');
        $response->assertStatus(200)
            ->assertJsonStructure(['municipals']);
        $this->assertContains('bharatpur metropolitan city', $response->json('municipals'));

        // Title Case parameter
        $titleResponse = $this->getJson('/api/municipals/chitwan?case=title');
        $titleResponse->assertStatus(200);
        $this->assertContains('Bharatpur Metropolitan City', $titleResponse->json('municipals'));

        // Spaced and hyphenated names
        $erResponse1 = $this->getJson('/api/municipals/eastern-rukum');
        $erResponse1->assertStatus(200);
        $this->assertNotEmpty($erResponse1->json('municipals'));

        $erResponse2 = $this->getJson('/api/municipals/eastern%20rukum');
        $erResponse2->assertStatus(200);
        $this->assertEquals($erResponse1->json('municipals'), $erResponse2->json('municipals'));

        // Aliases
        $this->getJson('/api/municipals/tanahu')->assertStatus(200);
        $this->getJson('/api/municipals/tanahun')->assertStatus(200);
        $this->getJson('/api/municipals/illam')->assertStatus(200);
        $this->getJson('/api/municipals/ilam')->assertStatus(200);
        $this->getJson('/api/municipals/tehrathum')->assertStatus(200);
        $this->getJson('/api/municipals/terhathum')->assertStatus(200);
    }

    public function test_every_single_district_has_valid_municipal_data(): void
    {
        $districtsResponse = $this->getJson('/api/districts');
        $districts = $districtsResponse->json('districts');

        foreach ($districts as $district) {
            $slug = str_replace(' ', '-', $district);
            $response = $this->getJson("/api/municipals/{$slug}");
            $this->assertEquals(
                200,
                $response->status(),
                "District '{$district}' (slug: '{$slug}') failed with status {$response->status()}"
            );
            $this->assertNotEmpty(
                $response->json('municipals'),
                "District '{$district}' returned empty municipals"
            );
        }
    }

    public function test_invalid_district_returns_404(): void
    {
        $response = $this->getJson('/api/municipals/gotham');
        $response->assertStatus(404)
            ->assertJson(['error' => 'District not found']);
    }

    public function test_search_endpoint(): void
    {
        $response = $this->getJson('/api/search?q=bharatpur');
        $response->assertStatus(200)
            ->assertJsonStructure(['query', 'total', 'results']);

        $this->assertGreaterThanOrEqual(1, $response->json('total'));
        $firstResult = $response->json('results.0');
        $this->assertEquals('bharatpur metropolitan city', $firstResult['name']);
        $this->assertEquals('municipality', $firstResult['type']);
        $this->assertEquals('chitwan', $firstResult['district']);
        $this->assertEquals('bagmati', $firstResult['province']);

        // Search validation error
        $emptySearch = $this->getJson('/api/search?q=');
        $emptySearch->assertStatus(422)
            ->assertJson(['error' => 'Query parameter "q" is required.']);
    }

    public function test_stats_endpoint(): void
    {
        $response = $this->getJson('/api/stats');
        $response->assertStatus(200)
            ->assertJson([
                'country' => 'Nepal',
                'total_provinces' => 7,
                'total_districts' => 77,
                'total_municipalities' => 753,
            ]);
    }

    public function test_hierarchy_endpoint(): void
    {
        $response = $this->getJson('/api/hierarchy');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'country',
                'total_provinces',
                'provinces' => [
                    '*' => [
                        'province',
                        'total_districts',
                        'districts' => [
                            '*' => [
                                'district',
                                'total_municipals',
                                'municipals',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertEquals(7, $response->json('total_provinces'));

        // Also test /api/all alias
        $allResponse = $this->getJson('/api/all');
        $allResponse->assertStatus(200);
    }

    public function test_path_traversal_protection(): void
    {
        $response1 = $this->getJson('/api/districts/../../etc/passwd');
        $this->assertTrue(in_array($response1->status(), [404, 400]));

        $response2 = $this->getJson('/api/municipals/../../etc/passwd');
        $this->assertTrue(in_array($response2->status(), [404, 400]));
    }
}
