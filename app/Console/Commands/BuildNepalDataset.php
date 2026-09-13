<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BuildNepalDataset extends Command
{
    protected $signature = 'nepal:build-dataset';

    protected $description = 'Merge upstream en/np datasets into canonical bilingual files';

    /**
     * Upstream district spellings that differ from this repo's.
     * Canonical form is this repo's, so existing responses do not change.
     */
    private const DISTRICT_SPELLING = [
        'Acham' => 'Achham',
        'Pachthar' => 'Panchthar',
        'Parwat' => 'Parbat',
        'Ramechap' => 'Ramechhap',
    ];

    /**
     * Municipality pairings the edit-distance matcher cannot resolve alone.
     * Keyed by "<district slug>|<upstream name>". Hand-verified.
     */
    private const MUNICIPALITY_OVERRIDES = [
        'ramechhap|Doramba' => 'doramba sailung rural municipality',
        'rasuwa|Parbatikunda' => 'amachodingmo rural municipality',
        'sindhupalchok|Panchpokhari' => 'panchpokhari thangpal rural municipality',
        'sindhupalchok|Lisankhu' => 'lisangkhu pakhar rural municipality',
        'gorkha|Sulikot' => 'barpak sulikot rural municipality',
        'gorkha|Ajirkot' => 'ajirkot rural municipality',
        'gulmi|Rurukshetra' => 'ruru rural municipality',
    ];

    public function handle(): int
    {
        $categories = $this->buildCategories();
        $provinces = $this->buildProvinces();
        $districts = $this->buildDistricts($provinces);
        $municipalities = $this->buildMunicipalities($districts, $categories);

        $this->write('categories', $categories);
        $this->write('provinces', $provinces);
        $this->write('districts', $districts);
        $this->write('municipalities', $municipalities);

        $this->info(sprintf(
            'Built %d provinces, %d districts, %d municipalities, %d wards.',
            count($provinces),
            count($districts),
            count($municipalities),
            array_sum(array_column($municipalities, 'wards'))
        ));

        return self::SUCCESS;
    }

    private function upstream(string $set, string $lang): array
    {
        $path = base_path("database/data/upstream/{$set}.{$lang}.json");

        if (! file_exists($path)) {
            throw new \RuntimeException("Missing upstream file: {$path}");
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Join en and np records by id. Fails if any id is missing on either side. */
    private function join(string $set): array
    {
        $en = $this->upstream($set, 'en');
        $np = collect($this->upstream($set, 'np'))->keyBy('id');

        return array_map(function (array $record) use ($np, $set) {
            if (! $np->has($record['id'])) {
                throw new \RuntimeException("No Nepali record for {$set} id {$record['id']}");
            }

            return [$record, $np->get($record['id'])];
        }, $en);
    }

    public static function slug(string $name): string
    {
        $clean = strtolower(trim($name));
        $clean = preg_replace('/[\s_]+/', '-', $clean);
        $clean = preg_replace('/[^a-z0-9\-]/', '', $clean);

        return trim($clean, '-');
    }

    private function buildCategories(): array
    {
        return array_map(fn ($pair) => [
            'id' => $pair[0]['id'],
            'name' => $pair[0]['name'],
            'name_np' => $pair[1]['name'],
            'short_code' => $pair[0]['short_code'],
        ], $this->join('categories'));
    }

    private function buildProvinces(): array
    {
        return array_map(function ($pair) {
            [$en, $np] = $pair;
            // Legacy responses use the bare name: "koshi", not "koshi province".
            $bare = trim(preg_replace('/\s*Province$/i', '', $en['name']));

            return [
                'id' => $en['id'],
                'slug' => self::slug($bare),
                'name' => $bare,
                'name_np' => $np['name'],
                'legacy_name' => strtolower($bare),
                'area_sq_km' => $en['area_sq_km'],
                'area_sq_km_np' => $np['area_sq_km'],
                'website' => $en['website'],
                'headquarter' => $en['headquarter'],
                'headquarter_np' => $np['headquarter'],
            ];
        }, $this->join('provinces'));
    }

    private function buildDistricts(array $provinces): array
    {
        $provinceIds = array_column($provinces, 'id');

        return array_map(function ($pair) use ($provinceIds) {
            [$en, $np] = $pair;
            $name = self::DISTRICT_SPELLING[$en['name']] ?? $en['name'];

            if (! in_array($en['province_id'], $provinceIds, true)) {
                throw new \RuntimeException("District {$name} has unknown province_id {$en['province_id']}");
            }

            return [
                'id' => $en['id'],
                'province_id' => $en['province_id'],
                'slug' => self::slug($name),
                'name' => $name,
                'name_np' => $np['name'],
                'legacy_name' => strtolower($name),
                'area_sq_km' => $en['area_sq_km'],
                'area_sq_km_np' => $np['area_sq_km'],
                'website' => $en['website'],
                'headquarter' => $en['headquarter'],
                'headquarter_np' => $np['headquarter'],
            ];
        }, $this->join('districts'));
    }

    /**
     * Pair each upstream municipality with the spelling this repo already
     * serves, so legacy responses do not change. Fails loudly rather than
     * guessing when a pairing is ambiguous.
     */
    private function buildMunicipalities(array $districts, array $categories): array
    {
        $categoryNames = array_column($categories, 'name', 'id');
        $districtsById = array_column($districts, null, 'id');

        $byDistrict = [];
        foreach ($this->join('municipalities') as $pair) {
            $byDistrict[$pair[0]['district_id']][] = $pair;
        }

        $result = [];

        foreach ($byDistrict as $districtId => $pairs) {
            $district = $districtsById[$districtId];
            $pool = $this->legacyMunicipals($district['slug']);

            if (count($pool) !== count($pairs)) {
                throw new \RuntimeException(sprintf(
                    'District %s: %d legacy names but %d upstream municipalities.',
                    $district['name'], count($pool), count($pairs)
                ));
            }

            // Explicit overrides first, so they cannot be stolen by the matcher.
            $remaining = [];
            foreach ($pairs as $pair) {
                $key = $district['slug'].'|'.$pair[0]['name'];

                if (! isset(self::MUNICIPALITY_OVERRIDES[$key])) {
                    $remaining[] = $pair;
                    continue;
                }

                $legacy = self::MUNICIPALITY_OVERRIDES[$key];
                $index = array_search($legacy, $pool, true);

                if ($index === false) {
                    throw new \RuntimeException("Override target '{$legacy}' not found for {$key}");
                }

                unset($pool[$index]);
                $result[] = $this->municipalityRecord($pair, $legacy);
            }

            foreach ($remaining as $pair) {
                $legacy = $this->bestLegacyMatch($pair[0], $pool, $categoryNames, $district['name']);
                unset($pool[array_search($legacy, $pool, true)]);
                $result[] = $this->municipalityRecord($pair, $legacy);
            }

            if ($pool !== []) {
                throw new \RuntimeException(sprintf(
                    'District %s: unmatched legacy names: %s',
                    $district['name'], implode(', ', $pool)
                ));
            }
        }

        usort($result, fn ($a, $b) => $a['id'] <=> $b['id']);

        return $result;
    }

    private function municipalityRecord(array $pair, string $legacy): array
    {
        [$en, $np] = $pair;

        return [
            'id' => $en['id'],
            'district_id' => $en['district_id'],
            'category_id' => $en['category_id'],
            'slug' => self::slug($en['name']),
            'name' => $en['name'],
            'name_np' => $np['name'],
            'legacy_name' => $legacy,
            'wards' => (int) $en['wards'],
            'area_sq_km' => $en['area_sq_km'],
            'area_sq_km_np' => $np['area_sq_km'],
            'website' => $en['website'],
        ];
    }

    /** Read the repo's existing municipality spellings for a district. */
    private function legacyMunicipals(string $districtSlug): array
    {
        $candidates = [
            $districtSlug,
            str_replace('-', ' ', $districtSlug),
            str_replace('-', '', $districtSlug),
        ];

        foreach ($candidates as $candidate) {
            $path = public_path("data/municipalsByDistrict/{$candidate}.json");

            if (file_exists($path)) {
                $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

                return array_values(array_map('trim', $data['municipals']));
            }
        }

        throw new \RuntimeException("No legacy municipal file for district slug '{$districtSlug}'");
    }

    /**
     * Lowest edit distance wins. Requires a margin of 2 over the runner-up,
     * otherwise the pairing is ambiguous and needs an explicit override.
     */
    private function bestLegacyMatch(array $upstream, array $pool, array $categoryNames, string $districtName): string
    {
        $target = $this->comparable($upstream['name'].' '.$categoryNames[$upstream['category_id']]);

        $best = null;
        $bestDistance = PHP_INT_MAX;
        $runnerUp = PHP_INT_MAX;

        foreach ($pool as $legacy) {
            $distance = levenshtein($target, $this->comparable($legacy));

            if ($distance < $bestDistance) {
                $runnerUp = $bestDistance;
                $bestDistance = $distance;
                $best = $legacy;
            } elseif ($distance < $runnerUp) {
                $runnerUp = $distance;
            }
        }

        if ($best === null) {
            throw new \RuntimeException("No candidates left for {$upstream['name']} in {$districtName}");
        }

        if ($runnerUp - $bestDistance < 2) {
            throw new \RuntimeException(sprintf(
                'Ambiguous pairing in %s: "%s" -> "%s" (distance %d, runner-up %d). Add an override.',
                $districtName, $upstream['name'], $best, $bestDistance, $runnerUp
            ));
        }

        return $best;
    }

    private function comparable(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($value));
    }

    private function write(string $name, array $records): void
    {
        if (! is_dir(public_path('data/canonical'))) {
            mkdir(public_path('data/canonical'), 0755, true);
        }

        file_put_contents(
            public_path("data/canonical/{$name}.json"),
            json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
