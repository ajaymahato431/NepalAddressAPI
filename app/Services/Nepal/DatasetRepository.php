<?php

namespace App\Services\Nepal;

use Illuminate\Support\Facades\Cache;

class DatasetRepository
{
    public const DATASETS = ['provinces', 'districts', 'municipalities', 'categories'];

    private const GENERATION_KEY = 'nepal_dataset_generation';

    /** @var array<string, array> in-process memo, on top of the cache store */
    private array $loaded = [];

    public function provinces(): array
    {
        return $this->dataset('provinces');
    }

    public function districts(): array
    {
        return $this->dataset('districts');
    }

    public function municipalities(): array
    {
        return $this->dataset('municipalities');
    }

    public function categories(): array
    {
        return $this->dataset('categories');
    }

    public function province(int $id): ?array
    {
        return $this->index('provinces')[$id] ?? null;
    }

    public function district(int $id): ?array
    {
        return $this->index('districts')[$id] ?? null;
    }

    public function category(int $id): ?array
    {
        return $this->index('categories')[$id] ?? null;
    }

    public function districtsOfProvince(int $provinceId): array
    {
        return array_values(array_filter(
            $this->districts(),
            fn ($d) => $d['province_id'] === $provinceId
        ));
    }

    public function municipalitiesOfDistrict(int $districtId): array
    {
        return $this->municipalitiesGrouped()[$districtId] ?? [];
    }

    public function provinceOfDistrict(array $district): ?array
    {
        return $this->province($district['province_id']);
    }

    public function wardTotalForDistrict(int $districtId): int
    {
        return array_sum(array_column($this->municipalitiesOfDistrict($districtId), 'wards'));
    }

    public function totalWards(): int
    {
        return array_sum(array_column($this->municipalities(), 'wards'));
    }

    /**
     * Monotonic counter bumped whenever the dataset is rebuilt.
     *
     * Anything that caches a value DERIVED from the dataset must include this
     * in its cache key. That way a rebuild invalidates derived caches without
     * this class having to know they exist — enumerating their keys here would
     * silently go stale the next time someone adds one.
     */
    public function generation(): int
    {
        return (int) Cache::get(self::GENERATION_KEY, 1);
    }

    public static function flushCache(): void
    {
        foreach (self::DATASETS as $name) {
            Cache::forget("nepal_dataset_{$name}");
        }

        Cache::forever(self::GENERATION_KEY, (int) Cache::get(self::GENERATION_KEY, 1) + 1);
    }

    /** @return array<int, array> municipalities keyed by district_id */
    private function municipalitiesGrouped(): array
    {
        if (! isset($this->loaded['municipalities_grouped'])) {
            $grouped = [];
            foreach ($this->municipalities() as $m) {
                $grouped[$m['district_id']][] = $m;
            }
            $this->loaded['municipalities_grouped'] = $grouped;
        }

        return $this->loaded['municipalities_grouped'];
    }

    /** @return array<int, array> records keyed by id */
    private function index(string $name): array
    {
        $key = "{$name}_index";

        if (! isset($this->loaded[$key])) {
            $this->loaded[$key] = array_column($this->dataset($name), null, 'id');
        }

        return $this->loaded[$key];
    }

    private function dataset(string $name): array
    {
        if (isset($this->loaded[$name])) {
            return $this->loaded[$name];
        }

        $records = Cache::rememberForever("nepal_dataset_{$name}", function () use ($name) {
            $path = public_path("data/canonical/{$name}.json");

            if (! file_exists($path)) {
                throw new \RuntimeException(
                    "Missing dataset {$name}.json. Run: php artisan nepal:build-dataset"
                );
            }

            return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        });

        return $this->loaded[$name] = $records;
    }
}
