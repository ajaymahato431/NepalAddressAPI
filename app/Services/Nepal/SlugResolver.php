<?php

namespace App\Services\Nepal;

class SlugResolver
{
    public function __construct(
        private DatasetRepository $repository
    ) {}

    /** Province aliases, carried over from the original AddressService. */
    private const PROVINCE_ALIASES = [
        'pradesh-1' => 'koshi', 'province-1' => 'koshi', 'koshi-province' => 'koshi',
        'pradesh1' => 'koshi', 'province1' => 'koshi',
        'pradesh-2' => 'madhesh', 'province-2' => 'madhesh', 'madhesh-province' => 'madhesh',
        'pradesh-3' => 'bagmati', 'province-3' => 'bagmati', 'bagmati-province' => 'bagmati',
        'pradesh-4' => 'gandaki', 'province-4' => 'gandaki', 'gandaki-province' => 'gandaki',
        'pradesh-5' => 'lumbini', 'province-5' => 'lumbini', 'lumbini-province' => 'lumbini',
        'pradesh-6' => 'karnali', 'province-6' => 'karnali', 'karnali-province' => 'karnali',
        'pradesh-7' => 'sudurpaschim', 'province-7' => 'sudurpaschim',
        'sudurpashchim' => 'sudurpaschim', 'sudurpaschim-province' => 'sudurpaschim',
    ];

    /** District aliases, carried over plus the four upstream spellings. */
    private const DISTRICT_ALIASES = [
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
        'acham' => 'achham',
        'pachthar' => 'panchthar',
        'parwat' => 'parbat',
        'ramechap' => 'ramechhap',
    ];

    /**
     * Normalize input to a slug. Also the path-traversal guard: everything
     * outside [a-z0-9-] is discarded, so "../.." cannot escape.
     */
    public function normalize(string $input): string
    {
        $clean = strtolower(trim($input));
        $clean = preg_replace('/[\s_]+/', '-', $clean);
        $clean = preg_replace('/[^a-z0-9\-]/', '', $clean);

        return trim($clean, '-');
    }

    public function resolveProvince(string $input): ?array
    {
        if ($record = $this->matchNepali($this->repository->provinces(), $input)) {
            return $record;
        }

        $slug = $this->normalize($input);
        $slug = self::PROVINCE_ALIASES[$slug] ?? $slug;

        return $this->matchSlug($this->repository->provinces(), $slug);
    }

    public function resolveDistrict(string $input): ?array
    {
        if ($record = $this->matchNepali($this->repository->districts(), $input)) {
            return $record;
        }

        $slug = $this->normalize($input);
        $slug = self::DISTRICT_ALIASES[$slug] ?? $slug;

        return $this->matchSlug($this->repository->districts(), $slug);
    }

    public function resolveMunicipality(int $districtId, string $input): ?array
    {
        $pool = $this->repository->municipalitiesOfDistrict($districtId);

        if ($record = $this->matchNepali($pool, $input)) {
            return $record;
        }

        $slug = $this->normalize($input);

        if ($record = $this->matchSlug($pool, $slug)) {
            return $record;
        }

        // Accept the legacy name too, e.g. "bharatpur-metropolitan-city".
        foreach ($pool as $record) {
            if ($this->normalize($record['legacy_name']) === $slug) {
                return $record;
            }
        }

        return null;
    }

    private function matchSlug(array $pool, string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        foreach ($pool as $record) {
            if ($record['slug'] === $slug) {
                return $record;
            }
        }

        return null;
    }

    /** Exact match on the Devanagari name, so Nepali input resolves directly. */
    private function matchNepali(array $pool, string $input): ?array
    {
        $trimmed = trim($input);

        if ($trimmed === '' || ! preg_match('/\p{Devanagari}/u', $trimmed)) {
            return null;
        }

        foreach ($pool as $record) {
            if ($record['name_np'] === $trimmed) {
                return $record;
            }
        }

        return null;
    }
}
