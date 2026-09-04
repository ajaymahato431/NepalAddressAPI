<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class AddressService
{
    /**
     * Map of known province aliases to canonical slugs.
     */
    protected array $provinceAliases = [
        'pradesh-1' => 'koshi',
        'province-1' => 'koshi',
        'koshi-province' => 'koshi',
        'pradesh1' => 'koshi',
        'province1' => 'koshi',
        'pradesh-2' => 'madhesh',
        'province-2' => 'madhesh',
        'madhesh-province' => 'madhesh',
        'pradesh-3' => 'bagmati',
        'province-3' => 'bagmati',
        'bagmati-province' => 'bagmati',
        'pradesh-4' => 'gandaki',
        'province-4' => 'gandaki',
        'gandaki-province' => 'gandaki',
        'pradesh-5' => 'lumbini',
        'province-5' => 'lumbini',
        'lumbini-province' => 'lumbini',
        'pradesh-6' => 'karnali',
        'province-6' => 'karnali',
        'karnali-province' => 'karnali',
        'pradesh-7' => 'sudurpaschim',
        'province-7' => 'sudurpaschim',
        'sudurpashchim' => 'sudurpaschim',
        'sudurpaschim-province' => 'sudurpaschim',
    ];

    /**
     * Map of known district aliases to canonical slugs.
     */
    protected array $districtAliases = [
        'illam' => 'ilam',
        'tehrathum' => 'terhathum',
        'tanahu' => 'tanahun',
        'sunskari' => 'sunsari',
        'nawalparasi' => 'parasi',
        'nawalparasi-west' => 'parasi',
        'nawalparasi-east' => 'nawalpur',
        'chitawan' => 'chitwan',
        'makawanpur' => 'makwanpur',
        'kavre' => 'kavrepalanchok',
        'kabhre' => 'kavrepalanchok',
        'kavrepalanchowk' => 'kavrepalanchok',
        'sindhupalchowk' => 'sindhupalchok',
    ];

    /**
     * Normalize an input key to prevent path traversal and match slugs.
     */
    public function normalizeSlug(string $input): string
    {
        $clean = trim($input);
        $clean = strtolower($clean);
        $clean = preg_replace('/[\s_]+/', '-', $clean);
        $clean = preg_replace('/[^a-z0-9\-]/', '', $clean);
        return trim($clean, '-');
    }

    /**
     * Format a string or list of strings based on the requested casing.
     *
     * @param string|array $data
     * @param string|null $case 'title' | 'lower'
     * @return string|array
     */
    public function formatCase(string|array $data, ?string $case = 'lower'): string|array
    {
        if (is_array($data)) {
            return array_map(fn($item) => $this->formatCase($item, $case), $data);
        }

        if (strtolower((string) $case) === 'title') {
            // Convert to Title Case, preserving words like Sub-Metropolitan
            $words = explode(' ', $data);
            $capitalized = array_map(function ($w) {
                if (str_contains($w, '-')) {
                    $parts = explode('-', $w);
                    return implode('-', array_map('ucfirst', $parts));
                }
                return ucfirst($w);
            }, $words);
            return implode(' ', $capitalized);
        }

        return strtolower($data);
    }

    /**
     * Retrieve all provinces.
     */
    public function getProvinces(?string $case = 'lower'): array
    {
        $provinces = Cache::rememberForever('nepal_address_provinces', function () {
            $path = public_path('data/provinces.json');
            if (!File::exists($path)) {
                return ['koshi', 'madhesh', 'bagmati', 'gandaki', 'lumbini', 'karnali', 'sudurpaschim'];
            }
            $data = json_decode(File::get($path), true);
            return $data['provinces'] ?? [];
        });

        return [
            'provinces' => $this->formatCase($provinces, $case),
        ];
    }

    /**
     * Retrieve all districts.
     */
    public function getDistricts(?string $case = 'lower'): array
    {
        $districts = Cache::rememberForever('nepal_address_districts', function () {
            $path = public_path('data/districts.json');
            if (!File::exists($path)) {
                return [];
            }
            $data = json_decode(File::get($path), true);
            return array_map('trim', $data['districts'] ?? []);
        });

        return [
            'districts' => $this->formatCase($districts, $case),
        ];
    }

    /**
     * Retrieve districts for a specific province.
     */
    public function getDistrictsByProvince(string $provinceName, ?string $case = 'lower'): ?array
    {
        $slug = $this->normalizeSlug($provinceName);

        if (empty($slug)) {
            return null;
        }

        // Check aliases
        if (isset($this->provinceAliases[$slug])) {
            $slug = $this->provinceAliases[$slug];
        }

        $cacheKey = "nepal_address_districts_by_prov_{$slug}";

        $districts = Cache::rememberForever($cacheKey, function () use ($slug) {
            $possibleFiles = [
                public_path("data/districtsByProvince/{$slug}.json"),
            ];

            if ($slug === 'koshi') {
                $possibleFiles[] = public_path('data/districtsByProvince/pradesh-1.json');
            } elseif ($slug === 'pradesh-1') {
                $possibleFiles[] = public_path('data/districtsByProvince/koshi.json');
            }

            foreach ($possibleFiles as $file) {
                if (File::exists($file)) {
                    $data = json_decode(File::get($file), true);
                    return array_map('trim', $data['districts'] ?? []);
                }
            }

            return null;
        });

        if ($districts === null) {
            return null;
        }

        return [
            'districts' => $this->formatCase($districts, $case),
        ];
    }

    /**
     * Retrieve municipalities for a specific district.
     */
    public function getMunicipalsByDistrict(string $districtName, ?string $case = 'lower'): ?array
    {
        $slug = $this->normalizeSlug($districtName);

        if (empty($slug)) {
            return null;
        }

        // Check aliases
        if (isset($this->districtAliases[$slug])) {
            $slug = $this->districtAliases[$slug];
        }

        $cacheKey = "nepal_address_municipals_by_dist_{$slug}";

        $municipals = Cache::rememberForever($cacheKey, function () use ($slug) {
            $candidates = [
                $slug,
                str_replace('-', ' ', $slug),
                str_replace('-', '', $slug),
            ];

            foreach ($candidates as $cand) {
                $file = public_path("data/municipalsByDistrict/{$cand}.json");
                if (File::exists($file)) {
                    $data = json_decode(File::get($file), true);
                    return array_map('trim', $data['municipals'] ?? []);
                }
            }

            return null;
        });

        if ($municipals === null) {
            return null;
        }

        return [
            'municipals' => $this->formatCase($municipals, $case),
        ];
    }

    /**
     * Get the province to which a district belongs.
     */
    public function getProvinceForDistrict(string $district): ?string
    {
        $districtSlug = $this->normalizeSlug($district);
        if (isset($this->districtAliases[$districtSlug])) {
            $districtSlug = $this->districtAliases[$districtSlug];
        }

        $allProvinces = $this->getProvinces()['provinces'];
        foreach ($allProvinces as $province) {
            $provDistricts = $this->getDistrictsByProvince($province)['districts'] ?? [];
            foreach ($provDistricts as $d) {
                $dSlug = $this->normalizeSlug($d);
                if ($dSlug === $districtSlug) {
                    return $province;
                }
            }
        }

        return null;
    }

    /**
     * Search across provinces, districts, and municipalities.
     */
    public function search(string $query, ?string $case = 'lower', int $limit = 25): array
    {
        $q = strtolower(trim($query));
        if (empty($q)) {
            return [
                'query' => $query,
                'total' => 0,
                'results' => [],
            ];
        }

        $results = [];

        // 1. Search Provinces
        $provinces = $this->getProvinces()['provinces'];
        foreach ($provinces as $prov) {
            if (str_contains(strtolower($prov), $q)) {
                $results[] = [
                    'name' => $this->formatCase($prov, $case),
                    'type' => 'province',
                    'province' => $this->formatCase($prov, $case),
                ];
            }
        }

        // 2. Search Districts
        $districts = $this->getDistricts()['districts'];
        foreach ($districts as $dist) {
            if (str_contains(strtolower($dist), $q)) {
                $parentProv = $this->getProvinceForDistrict($dist) ?? 'unknown';
                $results[] = [
                    'name' => $this->formatCase($dist, $case),
                    'type' => 'district',
                    'district' => $this->formatCase($dist, $case),
                    'province' => $this->formatCase($parentProv, $case),
                ];
            }
        }

        // 3. Search Municipalities
        foreach ($districts as $dist) {
            $municipals = $this->getMunicipalsByDistrict($dist)['municipals'] ?? [];
            $parentProv = $this->getProvinceForDistrict($dist) ?? 'unknown';

            foreach ($municipals as $mun) {
                if (str_contains(strtolower($mun), $q)) {
                    $results[] = [
                        'name' => $this->formatCase($mun, $case),
                        'type' => 'municipality',
                        'district' => $this->formatCase($dist, $case),
                        'province' => $this->formatCase($parentProv, $case),
                    ];

                    if (count($results) >= $limit) {
                        break 2;
                    }
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
     * Retrieve complete address hierarchy (provinces -> districts -> municipalities).
     */
    public function getAllHierarchy(?string $case = 'lower'): array
    {
        $cacheKey = "nepal_address_full_hierarchy_{$case}";

        return Cache::rememberForever($cacheKey, function () use ($case) {
            $provinces = $this->getProvinces()['provinces'];
            $hierarchy = [];

            foreach ($provinces as $prov) {
                $districtsList = $this->getDistrictsByProvince($prov)['districts'] ?? [];
                $districtsData = [];

                foreach ($districtsList as $dist) {
                    $municipals = $this->getMunicipalsByDistrict($dist)['municipals'] ?? [];
                    $districtsData[] = [
                        'district' => $this->formatCase($dist, $case),
                        'total_municipals' => count($municipals),
                        'municipals' => $this->formatCase($municipals, $case),
                    ];
                }

                $hierarchy[] = [
                    'province' => $this->formatCase($prov, $case),
                    'total_districts' => count($districtsData),
                    'districts' => $districtsData,
                ];
            }

            return [
                'country' => 'Nepal',
                'total_provinces' => count($hierarchy),
                'provinces' => $hierarchy,
            ];
        });
    }

    /**
     * Retrieve summary statistics.
     */
    public function getStats(): array
    {
        return Cache::rememberForever('nepal_address_stats', function () {
            $provinces = $this->getProvinces()['provinces'];
            $allDistricts = $this->getDistricts()['districts'];

            $provinceStats = [];
            $totalMunicipals = 0;

            foreach ($provinces as $prov) {
                $districts = $this->getDistrictsByProvince($prov)['districts'] ?? [];
                $munCount = 0;
                foreach ($districts as $d) {
                    $munCount += count($this->getMunicipalsByDistrict($d)['municipals'] ?? []);
                }
                $totalMunicipals += $munCount;
                $provinceStats[] = [
                    'province' => $prov,
                    'districts_count' => count($districts),
                    'municipals_count' => $munCount,
                ];
            }

            return [
                'country' => 'Nepal',
                'total_provinces' => count($provinces),
                'total_districts' => count($allDistricts),
                'total_municipalities' => $totalMunicipals,
                'provinces_breakdown' => $provinceStats,
            ];
        });
    }
}
