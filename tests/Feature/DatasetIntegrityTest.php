<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatasetIntegrityTest extends TestCase
{
    private function load(string $name): array
    {
        $path = public_path("data/canonical/{$name}.json");
        $this->assertFileExists($path, "Canonical dataset {$name}.json is missing");

        return json_decode(file_get_contents($path), true);
    }

    public function test_dataset_totals(): void
    {
        $this->assertCount(7, $this->load('provinces'));
        $this->assertCount(77, $this->load('districts'));
        $this->assertCount(753, $this->load('municipalities'));
        $this->assertCount(4, $this->load('categories'));
    }

    public function test_total_wards_is_6743(): void
    {
        $total = array_sum(array_column($this->load('municipalities'), 'wards'));
        $this->assertSame(6743, $total);
    }

    public function test_every_record_has_both_languages(): void
    {
        foreach (['provinces', 'districts', 'municipalities'] as $set) {
            foreach ($this->load($set) as $record) {
                $this->assertNotEmpty($record['name'], "{$set} record {$record['id']} has no English name");
                $this->assertNotEmpty($record['name_np'], "{$set} record {$record['id']} has no Nepali name");
                $this->assertMatchesRegularExpression(
                    '/\p{Devanagari}/u',
                    $record['name_np'],
                    "{$set} record {$record['id']} name_np is not Devanagari"
                );
            }
        }
    }

    public function test_every_record_has_a_legacy_name(): void
    {
        foreach (['provinces', 'districts', 'municipalities'] as $set) {
            foreach ($this->load($set) as $record) {
                $this->assertNotEmpty(
                    $record['legacy_name'],
                    "{$set} record {$record['id']} ({$record['name']}) has no legacy_name"
                );
            }
        }
    }

    public function test_legacy_names_are_unique_within_their_parent(): void
    {
        $byDistrict = [];
        foreach ($this->load('municipalities') as $m) {
            $byDistrict[$m['district_id']][] = $m['legacy_name'];
        }

        foreach ($byDistrict as $districtId => $names) {
            $this->assertSame(
                count($names),
                count(array_unique($names)),
                "District {$districtId} has duplicate legacy municipality names"
            );
        }
    }

    public function test_foreign_keys_resolve(): void
    {
        $provinceIds = array_column($this->load('provinces'), 'id');
        $districtIds = array_column($this->load('districts'), 'id');
        $categoryIds = array_column($this->load('categories'), 'id');

        foreach ($this->load('districts') as $d) {
            $this->assertContains($d['province_id'], $provinceIds, "District {$d['name']} has a dangling province_id");
        }

        foreach ($this->load('municipalities') as $m) {
            $this->assertContains($m['district_id'], $districtIds, "Municipality {$m['name']} has a dangling district_id");
            $this->assertContains($m['category_id'], $categoryIds, "Municipality {$m['name']} has a dangling category_id");
        }
    }

    public function test_wards_are_positive_integers(): void
    {
        foreach ($this->load('municipalities') as $m) {
            $this->assertIsInt($m['wards'], "Municipality {$m['name']} wards is not an int");
            $this->assertGreaterThan(0, $m['wards'], "Municipality {$m['name']} has no wards");
        }
    }
}
