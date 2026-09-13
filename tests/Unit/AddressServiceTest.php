<?php

namespace Tests\Unit;

use App\Services\AddressService;
use App\Services\Nepal\AddressPresenter;
use App\Services\Nepal\DatasetRepository;
use App\Services\Nepal\SlugResolver;
use Tests\TestCase;

class AddressServiceTest extends TestCase
{
    private AddressService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = new DatasetRepository();
        $this->service = new AddressService(
            $repository,
            new SlugResolver($repository),
            new AddressPresenter($repository)
        );
    }

    /**
     * Kathmandu is stored with chandrabindu (U+0901). Searching with the
     * anusvara spelling (U+0902) must still find it: search() and
     * SlugResolver must agree on Nepali matching, not just resolution.
     */
    public function test_search_finds_kathmandu_by_the_mark_the_dataset_does_not_use(): void
    {
        $storedSpelling = 'काठमाडौ'."\u{0901}"; // काठमाडौँ - what's actually in the dataset
        $altSpelling = 'काठमाडौ'."\u{0902}";    // काठमाडौं - the mark the dataset does NOT use

        // Sanity precondition, independent of the fold: the two spellings differ as raw strings.
        $this->assertNotSame($storedSpelling, $altSpelling);

        $results = $this->service->search($altSpelling)['results'];

        $this->assertNotEmpty($results, 'Search with the alternate Nepali mark found nothing.');
        $this->assertTrue(
            collect($results)->contains(fn ($r) => $r['type'] === 'district' && $r['district'] === 'kathmandu'),
            'Search with the anusvara spelling did not find the Kathmandu district.'
        );
    }
}
