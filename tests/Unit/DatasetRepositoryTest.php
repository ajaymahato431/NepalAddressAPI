<?php

namespace Tests\Unit;

use App\Services\Nepal\DatasetRepository;
use Tests\TestCase;

class DatasetRepositoryTest extends TestCase
{
    private DatasetRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new DatasetRepository();
    }

    public function test_loads_all_datasets(): void
    {
        $this->assertCount(7, $this->repo->provinces());
        $this->assertCount(77, $this->repo->districts());
        $this->assertCount(753, $this->repo->municipalities());
        $this->assertCount(4, $this->repo->categories());
    }

    public function test_finds_districts_of_a_province(): void
    {
        $bagmati = collect($this->repo->provinces())->firstWhere('slug', 'bagmati');
        $districts = $this->repo->districtsOfProvince($bagmati['id']);

        $this->assertCount(13, $districts);
        $this->assertContains('Kathmandu', array_column($districts, 'name'));
        $this->assertContains('Chitwan', array_column($districts, 'name'));
    }

    public function test_finds_municipalities_of_a_district(): void
    {
        $kathmandu = collect($this->repo->districts())->firstWhere('slug', 'kathmandu');
        $municipalities = $this->repo->municipalitiesOfDistrict($kathmandu['id']);

        $this->assertCount(11, $municipalities);
        $this->assertContains('Kathmandu', array_column($municipalities, 'name'));
    }

    public function test_resolves_parent_province_of_a_district(): void
    {
        $kathmandu = collect($this->repo->districts())->firstWhere('slug', 'kathmandu');
        $province = $this->repo->provinceOfDistrict($kathmandu);

        $this->assertSame('bagmati', $province['slug']);
    }

    public function test_sums_wards_for_a_district(): void
    {
        $kathmandu = collect($this->repo->districts())->firstWhere('slug', 'kathmandu');
        $expected = array_sum(array_column(
            $this->repo->municipalitiesOfDistrict($kathmandu['id']),
            'wards'
        ));

        $this->assertSame($expected, $this->repo->wardTotalForDistrict($kathmandu['id']));
        $this->assertGreaterThan(0, $this->repo->wardTotalForDistrict($kathmandu['id']));
    }

    public function test_looks_up_records_by_id(): void
    {
        $this->assertSame('Metropolitan City', $this->repo->category(1)['name']);
        $this->assertNull($this->repo->category(999));
        $this->assertNull($this->repo->district(999));
    }

    public function test_sums_wards_across_the_country(): void
    {
        $this->assertSame(6743, $this->repo->totalWards());
    }
}
