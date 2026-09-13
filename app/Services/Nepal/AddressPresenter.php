<?php

namespace App\Services\Nepal;

class AddressPresenter
{
    public const LANGS = ['en', 'np', 'both'];

    public const CASES = ['lower', 'title'];

    public function __construct(
        private DatasetRepository $repository
    ) {}

    public function province(array $record, string $lang, bool $detailed, string $case): string|array
    {
        if (! $detailed) {
            return $this->flat($record, $lang, $case);
        }

        $districts = $this->repository->districtsOfProvince($record['id']);
        $wards = 0;
        $municipalities = 0;

        foreach ($districts as $district) {
            $municipalities += count($this->repository->municipalitiesOfDistrict($district['id']));
            $wards += $this->repository->wardTotalForDistrict($district['id']);
        }

        return $this->withNames([
            'id' => $record['id'],
            'slug' => $record['slug'],
        ], $record, $lang, $case) + $this->bilingual([
            'headquarter' => [$record['headquarter'], $record['headquarter_np']],
            'area_sq_km' => [$record['area_sq_km'], $record['area_sq_km_np']],
        ], $lang) + [
            'website' => $record['website'],
            'total_districts' => count($districts),
            'total_municipalities' => $municipalities,
            'total_wards' => $wards,
        ];
    }

    public function district(array $record, string $lang, bool $detailed, string $case): string|array
    {
        if (! $detailed) {
            return $this->flat($record, $lang, $case);
        }

        $province = $this->repository->provinceOfDistrict($record);
        $municipalities = $this->repository->municipalitiesOfDistrict($record['id']);

        return $this->withNames([
            'id' => $record['id'],
            'slug' => $record['slug'],
        ], $record, $lang, $case) + $this->bilingual([
            'headquarter' => [$record['headquarter'], $record['headquarter_np']],
            'area_sq_km' => [$record['area_sq_km'], $record['area_sq_km_np']],
        ], $lang) + [
            'website' => $record['website'],
            'province_slug' => $province['slug'] ?? null,
            'total_municipalities' => count($municipalities),
            'total_wards' => $this->repository->wardTotalForDistrict($record['id']),
        ];
    }

    public function municipality(array $record, string $lang, bool $detailed, string $case): string|array
    {
        if (! $detailed) {
            return $this->flat($record, $lang, $case);
        }

        $category = $this->repository->category($record['category_id']);
        $district = $this->repository->district($record['district_id']);

        return $this->withNames([
            'id' => $record['id'],
            'slug' => $record['slug'],
        ], $record, $lang, $case) + $this->bilingual([
            'category' => [$category['name'], $category['name_np']],
            'area_sq_km' => [$record['area_sq_km'], $record['area_sq_km_np']],
        ], $lang) + [
            'website' => $record['website'],
            'district_slug' => $district['slug'] ?? null,
        ] + $this->wards($record['wards'], $lang);
    }

    /** Ward count, in whichever numeral systems the caller asked for. */
    private function wards(int $count, string $lang): array
    {
        return match ($lang) {
            'np' => ['wards' => $count, 'wards_np' => NepaliNumeral::toNepali($count)],
            'both' => ['wards' => $count, 'wards_np' => NepaliNumeral::toNepali($count)],
            default => ['wards' => $count],
        };
    }

    /**
     * Flat output: the legacy English string, the Devanagari name, or both.
     */
    private function flat(array $record, string $lang, string $case): string|array
    {
        $english = $this->applyCase($record['legacy_name'], $case);

        return match ($lang) {
            'np' => $record['name_np'],
            'both' => ['name' => $english, 'name_np' => $record['name_np']],
            default => $english,
        };
    }

    /**
     * `name` always holds the requested language. `name_np` appears only for
     * lang=both. Detailed output uses the upstream name, not the legacy one.
     */
    private function withNames(array $carry, array $record, string $lang, string $case): array
    {
        $english = $this->applyCase($record['name'], $case === 'lower' ? 'title' : $case);

        return $carry + match ($lang) {
            'np' => ['name' => $record['name_np']],
            'both' => ['name' => $english, 'name_np' => $record['name_np']],
            default => ['name' => $english],
        };
    }

    /**
     * @param array<string, array{0: string, 1: string}> $fields key => [en, np]
     */
    private function bilingual(array $fields, string $lang): array
    {
        $out = [];

        foreach ($fields as $key => [$en, $np]) {
            match ($lang) {
                'np' => $out[$key] = $np,
                'both' => [$out[$key] = $en, $out[$key.'_np'] = $np],
                default => $out[$key] = $en,
            };
        }

        return $out;
    }

    private function applyCase(string $value, string $case): string
    {
        return $case === 'title' ? $this->titleCase($value) : strtolower($value);
    }

    /** Title Case, preserving hyphenated compounds like Sub-Metropolitan. */
    public function titleCase(string $value): string
    {
        $words = explode(' ', $value);

        $capitalized = array_map(function (string $word) {
            if (str_contains($word, '-')) {
                return implode('-', array_map('ucfirst', explode('-', $word)));
            }

            return ucfirst($word);
        }, $words);

        return implode(' ', $capitalized);
    }
}
