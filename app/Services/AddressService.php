<?php

namespace App\Services;

use App\Services\Nepal\AddressPresenter;
use App\Services\Nepal\DatasetRepository;
use App\Services\Nepal\NepaliNumeral;
use App\Services\Nepal\NepaliText;
use App\Services\Nepal\SlugResolver;
use Illuminate\Support\Facades\Cache;

class AddressService
{
    public function __construct(
        private DatasetRepository $repository,
        private SlugResolver $resolver,
        private AddressPresenter $presenter,
    ) {}

    public function getProvinces(?string $case = 'lower', string $lang = 'en', bool $detailed = false): array
    {
        return [
            'provinces' => array_map(
                fn ($p) => $this->presenter->province($p, $lang, $detailed, $case ?? 'lower'),
                $this->repository->provinces()
            ),
        ];
    }

    public function getDistricts(?string $case = 'lower', string $lang = 'en', bool $detailed = false): array
    {
        return [
            'districts' => array_map(
                fn ($d) => $this->presenter->district($d, $lang, $detailed, $case ?? 'lower'),
                $this->districtsInLegacyOrder()
            ),
        ];
    }

    public function getDistrictsByProvince(
        string $provinceName,
        ?string $case = 'lower',
        string $lang = 'en',
        bool $detailed = false
    ): ?array {
        $province = $this->resolver->resolveProvince($provinceName);

        if ($province === null) {
            return null;
        }

        return [
            'districts' => array_map(
                fn ($d) => $this->presenter->district($d, $lang, $detailed, $case ?? 'lower'),
                $this->repository->districtsOfProvince($province['id'])
            ),
        ];
    }

    public function getMunicipalsByDistrict(
        string $districtName,
        ?string $case = 'lower',
        string $lang = 'en',
        bool $detailed = false
    ): ?array {
        $district = $this->resolver->resolveDistrict($districtName);

        if ($district === null) {
            return null;
        }

        return [
            'municipals' => array_map(
                fn ($m) => $this->presenter->municipality($m, $lang, $detailed, $case ?? 'lower'),
                $this->repository->municipalitiesOfDistrict($district['id'])
            ),
        ];
    }

    /**
     * Enumerate wards 1..N for one municipality. Ward names do not exist in
     * any open dataset; wards are identified by number.
     */
    public function getWards(
        string $districtName,
        string $municipalityName,
        string $lang = 'en',
        ?string $case = 'lower'
    ): ?array {
        $district = $this->resolver->resolveDistrict($districtName);

        if ($district === null) {
            return null;
        }

        $municipality = $this->resolver->resolveMunicipality($district['id'], $municipalityName);

        if ($municipality === null) {
            return null;
        }

        $wards = [];

        for ($number = 1; $number <= $municipality['wards']; $number++) {
            $ward = match ($lang) {
                'np' => ['ward' => NepaliNumeral::toNepali($number)],
                'both' => ['ward' => $number, 'ward_np' => NepaliNumeral::toNepali($number)],
                default => ['ward' => $number],
            };

            $wards[] = $ward;
        }

        return [
            'municipality' => $this->presenter->municipality($municipality, $lang, true, $case ?? 'lower'),
            'district' => $this->presenter->district($district, $lang, false, $case ?? 'lower'),
            'total_wards' => $municipality['wards'],
            'wards' => $wards,
        ];
    }

    public function getMunicipality(
        string $districtName,
        string $municipalityName,
        string $lang = 'both',
        ?string $case = 'title'
    ): ?array {
        $district = $this->resolver->resolveDistrict($districtName);

        if ($district === null) {
            return null;
        }

        $municipality = $this->resolver->resolveMunicipality($district['id'], $municipalityName);

        if ($municipality === null) {
            return null;
        }

        $province = $this->repository->provinceOfDistrict($district);

        return [
            'municipality' => $this->presenter->municipality($municipality, $lang, true, $case ?? 'title'),
            'district' => $this->presenter->district($district, $lang, false, $case ?? 'title'),
            'province' => $this->presenter->province($province, $lang, false, $case ?? 'title'),
        ];
    }

    public function getCategories(string $lang = 'both'): array
    {
        return [
            'categories' => array_map(fn ($c) => match ($lang) {
                'np' => ['id' => $c['id'], 'name' => $c['name_np'], 'short_code' => $c['short_code']],
                'both' => ['id' => $c['id'], 'name' => $c['name'], 'name_np' => $c['name_np'], 'short_code' => $c['short_code']],
                default => ['id' => $c['id'], 'name' => $c['name'], 'short_code' => $c['short_code']],
            }, $this->repository->categories()),
        ];
    }

    /**
     * One linear pass over 837 records. The previous implementation resolved
     * each district's parent province inside the loop, which was O(n^2).
     */
    public function search(
        string $query,
        ?string $case = 'lower',
        int $limit = 25,
        string $lang = 'en',
        bool $detailed = false
    ): array {
        $needle = trim($query);

        if ($needle === '') {
            return ['query' => $query, 'total' => 0, 'results' => []];
        }

        $lower = mb_strtolower($needle);
        $case ??= 'lower';
        $results = [];

        foreach ($this->repository->provinces() as $province) {
            if ($this->matches($province, $lower)) {
                $results[] = [
                    'name' => $this->presenter->province($province, $lang, $detailed, $case),
                    'type' => 'province',
                    'province' => $this->presenter->province($province, $lang, false, $case),
                ];
            }
        }

        $districts = $this->districtsInLegacyOrder();

        foreach ($districts as $district) {
            if ($this->matches($district, $lower)) {
                $province = $this->repository->provinceOfDistrict($district);
                $results[] = [
                    'name' => $this->presenter->district($district, $lang, $detailed, $case),
                    'type' => 'district',
                    'district' => $this->presenter->district($district, $lang, false, $case),
                    'province' => $this->presenter->province($province, $lang, false, $case),
                ];
            }
        }

        foreach ($districts as $district) {
            $province = null;

            foreach ($this->repository->municipalitiesOfDistrict($district['id']) as $municipality) {
                if (! $this->matches($municipality, $lower)) {
                    continue;
                }

                $province ??= $this->repository->provinceOfDistrict($district);

                $results[] = [
                    'name' => $this->presenter->municipality($municipality, $lang, $detailed, $case),
                    'type' => 'municipality',
                    'district' => $this->presenter->district($district, $lang, false, $case),
                    'province' => $this->presenter->province($province, $lang, false, $case),
                ];

                if (count($results) >= $limit) {
                    break 2;
                }
            }
        }

        return [
            'query' => $query,
            'total' => count($results),
            'results' => array_slice($results, 0, $limit),
        ];
    }

    /**
     * Districts sorted the way the legacy districts.json file was: plain
     * alphabetical by legacy name, not the canonical dataset's province-then-id
     * grouping. Used by getDistricts() (the old flat /api/districts list was
     * alphabetical) and by search() (which truncates to $limit, so which N
     * results come out is sensitive to traversal order). Deliberately not
     * used for districtsOfProvince()/municipalitiesOfDistrict() traversal —
     * that old order was arbitrary scrape order with no reproducible rule.
     */
    private function districtsInLegacyOrder(): array
    {
        $districts = $this->repository->districts();

        usort($districts, fn ($a, $b) => $a['legacy_name'] <=> $b['legacy_name']);

        return $districts;
    }

    /**
     * Match against the legacy name, the upstream name, and the Nepali name.
     * The Nepali comparison folds anusvara/chandrabindu on both sides, the
     * same way SlugResolver does, so a query using either mark finds records
     * stored with the other.
     */
    private function matches(array $record, string $lowerNeedle): bool
    {
        foreach ([$record['legacy_name'], $record['name']] as $candidate) {
            if (str_contains(mb_strtolower($candidate), $lowerNeedle)) {
                return true;
            }
        }

        return str_contains(
            NepaliText::foldForComparison($record['name_np']),
            NepaliText::foldForComparison($lowerNeedle)
        );
    }

    public function getAllHierarchy(?string $case = 'lower', string $lang = 'en', bool $detailed = false): array
    {
        $case ??= 'lower';
        // The generation suffix makes a dataset rebuild invalidate this cache.
        $generation = $this->repository->generation();
        $cacheKey = "nepal_hierarchy_{$generation}_{$case}_{$lang}_".($detailed ? '1' : '0');

        return Cache::rememberForever($cacheKey, function () use ($case, $lang, $detailed) {
            $provinces = [];

            foreach ($this->repository->provinces() as $province) {
                $districts = [];

                foreach ($this->repository->districtsOfProvince($province['id']) as $district) {
                    $municipals = $this->repository->municipalitiesOfDistrict($district['id']);

                    $districts[] = [
                        'district' => $this->presenter->district($district, $lang, $detailed, $case),
                        'total_municipals' => count($municipals),
                        'total_wards' => $this->repository->wardTotalForDistrict($district['id']),
                        'municipals' => array_map(
                            fn ($m) => $this->presenter->municipality($m, $lang, $detailed, $case),
                            $municipals
                        ),
                    ];
                }

                $provinces[] = [
                    'province' => $this->presenter->province($province, $lang, $detailed, $case),
                    'total_districts' => count($districts),
                    'districts' => $districts,
                ];
            }

            return [
                'country' => 'Nepal',
                'total_provinces' => count($provinces),
                'provinces' => $provinces,
            ];
        });
    }

    public function getStats(string $lang = 'en'): array
    {
        $generation = $this->repository->generation();

        return Cache::rememberForever("nepal_stats_{$generation}_{$lang}", function () use ($lang) {
            $breakdown = [];

            foreach ($this->repository->provinces() as $province) {
                $districts = $this->repository->districtsOfProvince($province['id']);
                $municipals = 0;
                $wards = 0;

                foreach ($districts as $district) {
                    $municipals += count($this->repository->municipalitiesOfDistrict($district['id']));
                    $wards += $this->repository->wardTotalForDistrict($district['id']);
                }

                $breakdown[] = [
                    'province' => $this->presenter->province($province, $lang, false, 'lower'),
                    'districts_count' => count($districts),
                    'municipals_count' => $municipals,
                    'wards_count' => $wards,
                ];
            }

            $byCategory = [];

            foreach ($this->repository->categories() as $category) {
                $members = array_filter(
                    $this->repository->municipalities(),
                    fn ($m) => $m['category_id'] === $category['id']
                );

                $byCategory[] = [
                    'category' => $lang === 'np' ? $category['name_np'] : $category['name'],
                    'short_code' => $category['short_code'],
                    'count' => count($members),
                    'wards' => array_sum(array_column($members, 'wards')),
                ];
            }

            return [
                'country' => 'Nepal',
                'total_provinces' => count($this->repository->provinces()),
                'total_districts' => count($this->repository->districts()),
                'total_municipalities' => count($this->repository->municipalities()),
                'total_wards' => $this->repository->totalWards(),
                'provinces_breakdown' => $breakdown,
                'municipalities_by_category' => $byCategory,
            ];
        });
    }
}
