<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegacyStaticFilesTest extends TestCase
{
    public function test_duplicate_files_are_gone(): void
    {
        foreach ([
            'data/municipalsByDistrict/illam.json',
            'data/municipalsByDistrict/eastern rukum.json',
            'data/municipalsByDistrict/western rukum.json',
            'data/municipalsByDistrict/tanahu.json',
            'data/municipalsByDistrict/tehrathum.json',
            'data/municipalsByDistrict/sunskari.json',
            'data/municipalsByDistrict/nawalparasi.json',
            'data/districtsByProvince/pradesh-1.json',
        ] as $stale) {
            $this->assertFileDoesNotExist(public_path($stale));
        }
    }

    public function test_directories_hold_exactly_one_file_per_canonical_slug(): void
    {
        $districts = json_decode(file_get_contents(public_path('data/canonical/districts.json')), true);
        $provinces = json_decode(file_get_contents(public_path('data/canonical/provinces.json')), true);

        $this->assertCount(77, glob(public_path('data/municipalsByDistrict/*.json')));
        $this->assertCount(7, glob(public_path('data/districtsByProvince/*.json')));

        foreach ($districts as $district) {
            $this->assertFileExists(public_path("data/municipalsByDistrict/{$district['slug']}.json"));
        }

        foreach ($provinces as $province) {
            $this->assertFileExists(public_path("data/districtsByProvince/{$province['slug']}.json"));
        }
    }

    public function test_deleted_alias_files_are_still_reachable_through_the_api(): void
    {
        // The duplicate files are gone, but every name they served must still
        // resolve via the alias map — that is what makes their removal safe.
        foreach (['illam', 'tanahu', 'tehrathum', 'sunskari', 'nawalparasi'] as $alias) {
            $response = $this->getJson("/api/municipals/{$alias}");
            $response->assertStatus(200, "alias {$alias} no longer resolves");
            $this->assertNotEmpty($response->json('municipals'));
        }

        $this->getJson('/api/districts/pradesh-1')->assertStatus(200);
    }

    public function test_every_district_static_file_matches_the_api(): void
    {
        foreach ($this->getJson('/api/districts')->json('districts') as $district) {
            $slug = str_replace(' ', '-', $district);
            $path = public_path("data/municipalsByDistrict/{$slug}.json");

            $this->assertFileExists($path, "Missing static file for {$district}");

            $static = json_decode(file_get_contents($path), true)['municipals'];
            $api = $this->getJson("/api/municipals/{$slug}")->json('municipals');

            $this->assertSame($api, $static, "Static file for {$district} disagrees with the API");
        }
    }

    public function test_every_province_static_file_matches_the_api(): void
    {
        foreach ($this->getJson('/api/provinces')->json('provinces') as $province) {
            $path = public_path("data/districtsByProvince/{$province}.json");

            $this->assertFileExists($path, "Missing static file for {$province}");

            $static = json_decode(file_get_contents($path), true)['districts'];
            $api = $this->getJson("/api/districts/{$province}")->json('districts');

            $this->assertSame($api, $static, "Static file for {$province} disagrees with the API");
        }
    }

    public function test_legacy_top_level_files_are_refreshed_from_canonical(): void
    {
        $provinces = json_decode(file_get_contents(public_path('data/provinces.json')), true);
        $districts = json_decode(file_get_contents(public_path('data/districts.json')), true);

        // The legacy wrapper shape is preserved deliberately.
        $this->assertArrayHasKey('provinces', $provinces);
        $this->assertArrayHasKey('districts', $districts);

        $this->assertSame($this->getJson('/api/provinces')->json('provinces'), $provinces['provinces']);
        $this->assertSame($this->getJson('/api/districts')->json('districts'), $districts['districts']);
    }

    public function test_canonical_files_are_present(): void
    {
        foreach (['provinces', 'districts', 'municipalities', 'categories'] as $name) {
            $this->assertFileExists(public_path("data/canonical/{$name}.json"));
        }
    }
}
