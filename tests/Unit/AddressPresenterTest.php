<?php

namespace Tests\Unit;

use App\Services\Nepal\AddressPresenter;
use App\Services\Nepal\DatasetRepository;
use Tests\TestCase;

class AddressPresenterTest extends TestCase
{
    private AddressPresenter $presenter;
    private DatasetRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new DatasetRepository();
        $this->presenter = new AddressPresenter($this->repo);
    }

    private function bharatpur(): array
    {
        return collect($this->repo->municipalities())
            ->firstWhere('legacy_name', 'bharatpur metropolitan city');
    }

    private function kathmanduDistrict(): array
    {
        return collect($this->repo->districts())->firstWhere('slug', 'kathmandu');
    }

    public function test_flat_english_returns_the_legacy_string(): void
    {
        $this->assertSame(
            'bharatpur metropolitan city',
            $this->presenter->municipality($this->bharatpur(), 'en', false, 'lower')
        );
    }

    public function test_flat_english_title_case(): void
    {
        $this->assertSame(
            'Bharatpur Metropolitan City',
            $this->presenter->municipality($this->bharatpur(), 'en', false, 'title')
        );
    }

    public function test_flat_nepali_returns_devanagari_and_ignores_case(): void
    {
        $lower = $this->presenter->municipality($this->bharatpur(), 'np', false, 'lower');
        $title = $this->presenter->municipality($this->bharatpur(), 'np', false, 'title');

        $this->assertSame($lower, $title, 'case must not alter Devanagari');
        $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $lower);
    }

    public function test_flat_both_returns_an_object(): void
    {
        $result = $this->presenter->municipality($this->bharatpur(), 'both', false, 'lower');

        $this->assertSame('bharatpur metropolitan city', $result['name']);
        $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $result['name_np']);
    }

    public function test_detailed_municipality_carries_wards_and_category(): void
    {
        $result = $this->presenter->municipality($this->bharatpur(), 'both', true, 'title');

        $this->assertSame('Bharatpur', $result['name']);
        $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $result['name_np']);
        $this->assertSame('Metropolitan City', $result['category']);
        $this->assertSame('महानगरपालिका', $result['category_np']);
        $this->assertIsInt($result['wards']);
        $this->assertGreaterThan(0, $result['wards']);
        $this->assertSame('bharatpur', $result['slug']);
    }

    public function test_detailed_nepali_puts_devanagari_in_the_name_key(): void
    {
        $result = $this->presenter->municipality($this->bharatpur(), 'np', true, 'lower');

        $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $result['name']);
        $this->assertArrayNotHasKey('name_np', $result);
        $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $result['wards_np']);
    }

    public function test_detailed_english_omits_nepali_keys(): void
    {
        $result = $this->presenter->municipality($this->bharatpur(), 'en', true, 'lower');

        $this->assertArrayNotHasKey('name_np', $result);
        $this->assertArrayNotHasKey('category_np', $result);
        $this->assertSame('Bharatpur', $result['name']);
    }

    public function test_detailed_district_rolls_up_counts(): void
    {
        $result = $this->presenter->district($this->kathmanduDistrict(), 'both', true, 'title');

        $this->assertSame('Kathmandu', $result['name']);
        $this->assertSame('bagmati', $result['province_slug']);
        $this->assertSame(11, $result['total_municipalities']);
        $this->assertGreaterThan(0, $result['total_wards']);
    }

    public function test_title_case_preserves_hyphenated_words(): void
    {
        $this->assertSame('Sub-Metropolitan City', $this->presenter->titleCase('sub-metropolitan city'));
    }
}
