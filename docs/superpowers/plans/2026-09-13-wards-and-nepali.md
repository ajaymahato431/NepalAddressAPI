# Ward Data and Nepali Names Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add ward data and Nepali (Devanagari) names to every NepalAddressAPI endpoint without changing any existing response.

**Architecture:** Replace ~92 sprawling per-district JSON files with four canonical bilingual datasets built by a repeatable artisan command. Split the monolithic `AddressService` into four single-purpose units behind an unchanged facade. Existing responses stay byte-identical; new data is opt-in via `?detailed=true` and `?lang=`.

**Tech Stack:** PHP 8.2, Laravel 12, PHPUnit 11. No new composer dependencies.

**Spec:** `docs/superpowers/specs/2026-09-13-wards-and-nepali-design.md`

## Global Constraints

- **No new composer dependencies.** Everything uses the Laravel 12 standard library.
- **`tests/Feature/AddressApiTest.php` must never be modified.** It is the backward-compatibility proof. If a change breaks it, the change is wrong.
- **Data source:** `sagautam5/local-states-nepal`, MIT, Copyright (c) 2020 Sagar Gautam. The MIT notice must ship in `public/data/ATTRIBUTION.md`.
- **Verified dataset totals:** 7 provinces, 77 districts, 753 municipalities, 6,743 wards. Any task producing different numbers has a bug.
- **`case` applies to English output only.** Devanagari has no letter case; never apply `ucfirst`/`strtolower` to a Devanagari string.
- **Run tests with:** `php artisan test`. Single file: `php artisan test --filter=ClassName`.
- **Commit after every task.** Use the message given in the task's final step.

---

### Task 1: Freeze the legacy API contract

Before changing anything, record every current response to a fixture. Every later task replays this fixture. This is what makes "we didn't break it" a fact rather than a hope.

**Files:**
- Create: `tests/Feature/LegacyContractTest.php`
- Create (generated, then committed): `tests/fixtures/legacy-api-snapshot.json`

**Interfaces:**
- Consumes: nothing (first task).
- Produces: `tests/fixtures/legacy-api-snapshot.json`, a map of `"METHOD /uri"` to the decoded JSON body that endpoint returned **before** any change.

- [ ] **Step 1: Write the snapshot test with a record mode**

Create `tests/Feature/LegacyContractTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegacyContractTest extends TestCase
{
    private const SNAPSHOT = __DIR__.'/../fixtures/legacy-api-snapshot.json';

    /**
     * Every URI whose response must never change.
     */
    public static function legacyUris(): array
    {
        $uris = [
            '/api/provinces',
            '/api/provinces?case=title',
            '/api/districts',
            '/api/districts?case=title',
            '/api/stats',
            '/api/hierarchy',
            '/api/all',
            '/api/search?q=bharatpur',
            '/api/search?q=kathmandu',
            '/api/search?q=rural',
            '/api/districts/bagmati',
            '/api/districts/koshi',
            '/api/districts/pradesh-1',
            '/api/districts/BAGMATI',
            '/api/districts/sudurpaschim?case=title',
            '/api/municipals/eastern-rukum',
            '/api/municipals/eastern%20rukum',
            '/api/municipals/chitwan?case=title',
            '/api/municipals/tanahu',
            '/api/municipals/illam',
            '/api/municipals/tehrathum',
        ];

        // Every district, through the same slug rule the old test used.
        foreach (self::districtSlugs() as $slug) {
            $uris[] = "/api/municipals/{$slug}";
        }

        return array_map(fn ($u) => [$u], array_unique($uris));
    }

    /**
     * The 77 district slugs, hardcoded on purpose. Reading them from
     * public/data/districts.json would couple this test to a file whose
     * shape changes in Task 3, and a frozen contract must not move.
     */
    private static function districtSlugs(): array
    {
        return [
            'bhojpur', 'dhankuta', 'ilam', 'jhapa', 'khotang', 'morang', 'okhaldhunga',
            'panchthar', 'sankhuwasabha', 'solukhumbu', 'sunsari', 'taplejung',
            'terhathum', 'udayapur', 'bara', 'dhanusha', 'mahottari', 'parsa',
            'rautahat', 'saptari', 'sarlahi', 'siraha', 'bhaktapur', 'chitwan',
            'dhading', 'dolakha', 'kathmandu', 'kavrepalanchok', 'lalitpur',
            'makwanpur', 'nuwakot', 'ramechhap', 'rasuwa', 'sindhuli',
            'sindhupalchok', 'baglung', 'gorkha', 'kaski', 'lamjung', 'manang',
            'mustang', 'myagdi', 'nawalpur', 'parbat', 'syangja', 'tanahun',
            'arghakhanchi', 'banke', 'bardiya', 'dang', 'eastern-rukum', 'gulmi',
            'kapilvastu', 'parasi', 'palpa', 'pyuthan', 'rolpa', 'rupandehi',
            'dailekh', 'dolpa', 'humla', 'jajarkot', 'jumla', 'kalikot', 'mugu',
            'salyan', 'surkhet', 'western-rukum', 'achham', 'baitadi', 'bajhang',
            'bajura', 'dadeldhura', 'darchula', 'doti', 'kailali', 'kanchanpur',
        ];
    }

    /**
     * @dataProvider legacyUris
     */
    public function test_legacy_response_is_unchanged(string $uri): void
    {
        $response = $this->getJson($uri);
        $this->assertSame(200, $response->status(), "{$uri} did not return 200");

        $snapshot = json_decode(file_get_contents(self::SNAPSHOT), true);

        $this->assertArrayHasKey(
            $uri,
            $snapshot,
            "No recorded snapshot for {$uri}. Re-record with RECORD_LEGACY_SNAPSHOT=1."
        );

        $this->assertEquals(
            $snapshot[$uri],
            $response->json(),
            "Response for {$uri} changed. The legacy contract is broken."
        );
    }
}
```

- [ ] **Step 2: Write the one-off recorder**

Create `tests/Feature/RecordLegacySnapshotTest.php`. It is skipped unless the env var is set, so it never runs in CI:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class RecordLegacySnapshotTest extends TestCase
{
    public function test_record_snapshot(): void
    {
        if (getenv('RECORD_LEGACY_SNAPSHOT') !== '1') {
            $this->markTestSkipped('Set RECORD_LEGACY_SNAPSHOT=1 to record.');
        }

        $snapshot = [];

        foreach (LegacyContractTest::legacyUris() as [$uri]) {
            $response = $this->getJson($uri);
            $this->assertSame(200, $response->status(), "{$uri} returned {$response->status()}");
            $snapshot[$uri] = $response->json();
        }

        $dir = __DIR__.'/../fixtures';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $dir.'/legacy-api-snapshot.json',
            json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->assertGreaterThanOrEqual(90, count($snapshot));
    }
}
```

- [ ] **Step 3: Record the snapshot against the current, unmodified code**

Run:

```bash
RECORD_LEGACY_SNAPSHOT=1 php artisan test --filter=RecordLegacySnapshotTest
```

Expected: PASS, and `tests/fixtures/legacy-api-snapshot.json` now exists.

Verify it captured every URI:

```bash
php -r "echo count(json_decode(file_get_contents('tests/fixtures/legacy-api-snapshot.json'), true)).PHP_EOL;"
```

Expected: at least 90.

- [ ] **Step 4: Run the contract test to verify it passes against current code**

Run: `php artisan test --filter=LegacyContractTest`
Expected: PASS, ~98 assertions. This proves the fixture is a faithful recording.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/LegacyContractTest.php tests/Feature/RecordLegacySnapshotTest.php tests/fixtures/legacy-api-snapshot.json
git commit -m "test: freeze legacy API contract with response snapshot"
```

---

### Task 2: Nepali numeral conversion

Pure function, no dependencies. Build it first so later tasks can use it.

**Files:**
- Create: `app/Services/Nepal/NepaliNumeral.php`
- Test: `tests/Unit/NepaliNumeralTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `NepaliNumeral::toNepali(int|string $value): string` — converts every ASCII digit to Devanagari, leaves other characters (`.`, `-`) intact.
  - `NepaliNumeral::toArabic(string $value): string` — the inverse.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/NepaliNumeralTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\Nepal\NepaliNumeral;
use PHPUnit\Framework\TestCase;

class NepaliNumeralTest extends TestCase
{
    public function test_converts_single_digits(): void
    {
        $this->assertSame('०', NepaliNumeral::toNepali(0));
        $this->assertSame('९', NepaliNumeral::toNepali(9));
    }

    public function test_converts_multi_digit_numbers(): void
    {
        $this->assertSame('२९', NepaliNumeral::toNepali(29));
        $this->assertSame('६७४३', NepaliNumeral::toNepali(6743));
    }

    public function test_preserves_decimal_points(): void
    {
        $this->assertSame('४३२.९५', NepaliNumeral::toNepali('432.95'));
    }

    public function test_converts_back_to_arabic(): void
    {
        $this->assertSame('29', NepaliNumeral::toArabic('२९'));
        $this->assertSame('432.95', NepaliNumeral::toArabic('४३२.९५'));
    }

    public function test_round_trips_every_number_up_to_1000(): void
    {
        for ($i = 0; $i <= 1000; $i++) {
            $this->assertSame(
                (string) $i,
                NepaliNumeral::toArabic(NepaliNumeral::toNepali($i)),
                "Round trip failed for {$i}"
            );
        }
    }

    public function test_leaves_non_numeric_text_alone(): void
    {
        $this->assertSame('काठमाडौं', NepaliNumeral::toNepali('काठमाडौं'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=NepaliNumeralTest`
Expected: FAIL with `Class "App\Services\Nepal\NepaliNumeral" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Services/Nepal/NepaliNumeral.php`:

```php
<?php

namespace App\Services\Nepal;

class NepaliNumeral
{
    private const ARABIC = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    private const DEVANAGARI = ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९'];

    /**
     * Convert ASCII digits to Devanagari, leaving all other characters intact.
     */
    public static function toNepali(int|string $value): string
    {
        return str_replace(self::ARABIC, self::DEVANAGARI, (string) $value);
    }

    /**
     * Convert Devanagari digits to ASCII, leaving all other characters intact.
     */
    public static function toArabic(string $value): string
    {
        return str_replace(self::DEVANAGARI, self::ARABIC, $value);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=NepaliNumeralTest`
Expected: PASS, 6 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Nepal/NepaliNumeral.php tests/Unit/NepaliNumeralTest.php
git commit -m "feat: add Nepali numeral conversion"
```

---

### Task 3: Vendor upstream data and build the canonical dataset

This task produces the four bilingual files everything else reads. The pairing algorithm here was prototyped and verified: **753/753 municipalities pair, 7 need explicit overrides, 0 remain ambiguous.**

**Files:**
- Create: `database/data/upstream/{provinces,districts,municipalities,categories}.{en,np}.json` (vendored from upstream)
- Create: `app/Console/Commands/BuildNepalDataset.php`
- Create: `public/data/ATTRIBUTION.md`
- Test: `tests/Feature/DatasetIntegrityTest.php`
- Generated: `public/data/{provinces,districts,municipalities,categories}.json`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: four canonical files. Record shapes are fixed here and every later task depends on them:
  - province: `{id, slug, name, name_np, area_sq_km, area_sq_km_np, website, headquarter, headquarter_np, legacy_name}`
  - district: `{id, province_id, slug, name, name_np, area_sq_km, area_sq_km_np, website, headquarter, headquarter_np, legacy_name}`
  - municipality: `{id, district_id, category_id, slug, name, name_np, legacy_name, wards, area_sq_km, area_sq_km_np, website}`
  - category: `{id, name, name_np, short_code}`

- [ ] **Step 1: Vendor the upstream data**

```bash
mkdir -p database/data/upstream
BASE=https://raw.githubusercontent.com/sagautam5/local-states-nepal/master/dataset
for f in provinces districts municipalities categories; do
  for l in en np; do
    curl -sfL "$BASE/$f/$l.json" -o "database/data/upstream/$f.$l.json"
  done
done
curl -sfL https://raw.githubusercontent.com/sagautam5/local-states-nepal/master/LICENSE -o database/data/upstream/LICENSE
ls -la database/data/upstream
```

Expected: 9 files. Verify counts:

```bash
php -r 'foreach(["provinces"=>7,"districts"=>77,"municipalities"=>753,"categories"=>4] as $f=>$n){ foreach(["en","np"] as $l){ $c=count(json_decode(file_get_contents("database/data/upstream/$f.$l.json"),true)); echo "$f.$l: $c ".($c===$n?"OK":"MISMATCH expected $n").PHP_EOL; } }'
```

Expected: all OK.

- [ ] **Step 2: Write the failing dataset integrity test**

Create `tests/Feature/DatasetIntegrityTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatasetIntegrityTest extends TestCase
{
    private function load(string $name): array
    {
        $path = public_path("data/{$name}.json");
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
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=DatasetIntegrityTest`
Expected: FAIL with "Canonical dataset provinces.json is missing" (the current `provinces.json` has a `{"provinces": [...]}` wrapper, not a list of records, so `assertCount(7, ...)` fails too).

- [ ] **Step 4: Write the build command**

Create `app/Console/Commands/BuildNepalDataset.php`:

```php
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
                $result[] = $this->municipalityRecord($pair, $legacy, $categoryNames);
            }

            foreach ($remaining as $pair) {
                $legacy = $this->bestLegacyMatch($pair[0], $pool, $categoryNames, $district['name']);
                unset($pool[array_search($legacy, $pool, true)]);
                $result[] = $this->municipalityRecord($pair, $legacy, $categoryNames);
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

    private function municipalityRecord(array $pair, string $legacy, array $categoryNames): array
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

        if ($bestDistance > 0 && $runnerUp - $bestDistance < 2) {
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
        file_put_contents(
            public_path("data/{$name}.json"),
            json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
```

- [ ] **Step 5: Run the build command**

```bash
php artisan nepal:build-dataset
```

Expected output exactly: `Built 7 provinces, 77 districts, 753 municipalities, 6743 wards.`

If it throws an ambiguity or count error, do **not** loosen the margin. Read the message, hand-verify the correct pairing, and add it to `MUNICIPALITY_OVERRIDES`.

- [ ] **Step 6: Run the integrity test to verify it passes**

Run: `php artisan test --filter=DatasetIntegrityTest`
Expected: PASS, 7 tests.

- [ ] **Step 7: Write the attribution file**

Create `public/data/ATTRIBUTION.md`:

```markdown
# Data Attribution

The administrative dataset in this directory (provinces, districts,
municipalities, ward counts, and their Nepali names) is derived from
[sagautam5/local-states-nepal](https://github.com/sagautam5/local-states-nepal),
used under the MIT License.

Copyright (c) 2020 Sagar Gautam

Upstream data is vendored unmodified in `database/data/upstream/`, alongside a
copy of its MIT license. The canonical files here are generated from it by
`php artisan nepal:build-dataset`.

The only editorial change is romanized spelling: where upstream and this
project spelled a place differently, this project's existing spelling is
preserved as `legacy_name` so published API responses stay stable.
```

- [ ] **Step 8: Commit**

```bash
git add database/data/upstream public/data/ATTRIBUTION.md public/data/provinces.json public/data/districts.json public/data/municipalities.json public/data/categories.json app/Console/Commands/BuildNepalDataset.php tests/Feature/DatasetIntegrityTest.php
git commit -m "feat: build canonical bilingual dataset with ward counts"
```

---

### Task 4: Dataset repository

Loads and indexes the canonical files. One job: data access. No formatting, no HTTP concerns.

**Files:**
- Create: `app/Services/Nepal/DatasetRepository.php`
- Test: `tests/Unit/DatasetRepositoryTest.php`

**Interfaces:**
- Consumes: the four canonical files from Task 3.
- Produces:
  - `provinces(): array` / `districts(): array` / `municipalities(): array` / `categories(): array` — full record lists
  - `province(int $id): ?array` / `district(int $id): ?array` / `category(int $id): ?array`
  - `districtsOfProvince(int $provinceId): array`
  - `municipalitiesOfDistrict(int $districtId): array`
  - `provinceOfDistrict(array $district): ?array`
  - `wardTotalForDistrict(int $districtId): int`
  - `totalWards(): int` — used by `AddressService::getStats()` in Task 7

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/DatasetRepositoryTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DatasetRepositoryTest`
Expected: FAIL with `Class "App\Services\Nepal\DatasetRepository" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Services/Nepal/DatasetRepository.php`:

```php
<?php

namespace App\Services\Nepal;

use Illuminate\Support\Facades\Cache;

class DatasetRepository
{
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
            $path = public_path("data/{$name}.json");

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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=DatasetRepositoryTest`
Expected: PASS, 7 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Nepal/DatasetRepository.php tests/Unit/DatasetRepositoryTest.php
git commit -m "feat: add dataset repository with id and group indexes"
```

---

### Task 5: Slug resolver

Turns arbitrary user input into a canonical record. Owns every alias map, including the ones currently living in `AddressService`.

**Files:**
- Create: `app/Services/Nepal/SlugResolver.php`
- Test: `tests/Unit/SlugResolverTest.php`

**Interfaces:**
- Consumes: `DatasetRepository` (Task 4), injected via constructor.
- Produces:
  - `normalize(string $input): string`
  - `resolveProvince(string $input): ?array`
  - `resolveDistrict(string $input): ?array`
  - `resolveMunicipality(int $districtId, string $input): ?array`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/SlugResolverTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\Nepal\DatasetRepository;
use App\Services\Nepal\SlugResolver;
use Tests\TestCase;

class SlugResolverTest extends TestCase
{
    private SlugResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SlugResolver(new DatasetRepository());
    }

    public function test_normalizes_input(): void
    {
        $this->assertSame('eastern-rukum', $this->resolver->normalize('Eastern Rukum'));
        $this->assertSame('eastern-rukum', $this->resolver->normalize('  EASTERN_RUKUM  '));
        $this->assertSame('kathmandu', $this->resolver->normalize('Kathmandu'));
    }

    public function test_normalization_strips_path_traversal(): void
    {
        $this->assertSame('etcpasswd', $this->resolver->normalize('../../etc/passwd'));
        $this->assertSame('', $this->resolver->normalize('../..'));
    }

    public function test_resolves_provinces_including_aliases(): void
    {
        $this->assertSame('koshi', $this->resolver->resolveProvince('koshi')['slug']);
        $this->assertSame('koshi', $this->resolver->resolveProvince('pradesh-1')['slug']);
        $this->assertSame('koshi', $this->resolver->resolveProvince('province-1')['slug']);
        $this->assertSame('bagmati', $this->resolver->resolveProvince('BAGMATI')['slug']);
        $this->assertSame('sudurpaschim', $this->resolver->resolveProvince('sudurpashchim')['slug']);
        $this->assertNull($this->resolver->resolveProvince('atlantis'));
    }

    public function test_resolves_districts_including_aliases(): void
    {
        $this->assertSame('ilam', $this->resolver->resolveDistrict('illam')['slug']);
        $this->assertSame('terhathum', $this->resolver->resolveDistrict('tehrathum')['slug']);
        $this->assertSame('tanahun', $this->resolver->resolveDistrict('tanahu')['slug']);
        $this->assertSame('kavrepalanchok', $this->resolver->resolveDistrict('kavre')['slug']);
        $this->assertSame('eastern-rukum', $this->resolver->resolveDistrict('eastern rukum')['slug']);
        $this->assertNull($this->resolver->resolveDistrict('gotham'));
    }

    public function test_resolves_districts_by_nepali_name(): void
    {
        $this->assertSame('kathmandu', $this->resolver->resolveDistrict('काठमाडौं')['slug']);
    }

    public function test_resolves_municipalities_within_a_district(): void
    {
        $chitwan = $this->resolver->resolveDistrict('chitwan');

        $found = $this->resolver->resolveMunicipality($chitwan['id'], 'bharatpur');
        $this->assertSame('Bharatpur', $found['name']);

        $byLegacy = $this->resolver->resolveMunicipality($chitwan['id'], 'bharatpur-metropolitan-city');
        $this->assertSame('Bharatpur', $byLegacy['name']);

        $this->assertNull($this->resolver->resolveMunicipality($chitwan['id'], 'springfield'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SlugResolverTest`
Expected: FAIL with `Class "App\Services\Nepal\SlugResolver" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Services/Nepal/SlugResolver.php`:

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SlugResolverTest`
Expected: PASS, 6 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Nepal/SlugResolver.php tests/Unit/SlugResolverTest.php
git commit -m "feat: add slug resolver with alias and Nepali-name lookup"
```

---

### Task 6: Address presenter

Turns canonical records into response payloads. One job: output shaping. This is where `lang`, `detailed`, and `case` are applied, and the only place that knows about `legacy_name`.

**Files:**
- Create: `app/Services/Nepal/AddressPresenter.php`
- Test: `tests/Unit/AddressPresenterTest.php`

**Interfaces:**
- Consumes: `DatasetRepository` (Task 4), `NepaliNumeral` (Task 2).
- Produces:
  - `AddressPresenter::LANGS = ['en', 'np', 'both']`
  - `AddressPresenter::CASES = ['lower', 'title']`
  - `province(array $r, string $lang, bool $detailed, string $case): string|array`
  - `district(array $r, string $lang, bool $detailed, string $case): string|array`
  - `municipality(array $r, string $lang, bool $detailed, string $case): string|array`
  - `titleCase(string $value): string`

Flat mode (`$detailed === false`) returns a **string** for `lang=en` and `lang=np`, and an **array** `{name, name_np}` for `lang=both`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/AddressPresenterTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AddressPresenterTest`
Expected: FAIL with `Class "App\Services\Nepal\AddressPresenter" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Services/Nepal/AddressPresenter.php`:

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AddressPresenterTest`
Expected: PASS, 9 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Nepal/AddressPresenter.php tests/Unit/AddressPresenterTest.php
git commit -m "feat: add address presenter for flat, detailed, and bilingual output"
```

---

### Task 7: Rewrite AddressService as a facade

The moment of truth. `AddressService` keeps its public method signatures but delegates to the new units and reads the canonical dataset. **The Task 1 snapshot must still pass** — that is this task's real test.

**Files:**
- Modify: `app/Services/AddressService.php` (full rewrite)
- Test: `tests/Feature/LegacyContractTest.php` (Task 1, unmodified), `tests/Feature/AddressApiTest.php` (unmodified)

**Interfaces:**
- Consumes: `DatasetRepository`, `SlugResolver`, `AddressPresenter`.
- Produces (all gain `$lang` and `$detailed`, defaulted so existing callers are unaffected):
  - `getProvinces(?string $case = 'lower', string $lang = 'en', bool $detailed = false): array`
  - `getDistricts(?string $case = 'lower', string $lang = 'en', bool $detailed = false): array`
  - `getDistrictsByProvince(string $province, ?string $case = 'lower', string $lang = 'en', bool $detailed = false): ?array`
  - `getMunicipalsByDistrict(string $district, ?string $case = 'lower', string $lang = 'en', bool $detailed = false): ?array`
  - `search(string $q, ?string $case = 'lower', int $limit = 25, string $lang = 'en', bool $detailed = false): array`
  - `getAllHierarchy(?string $case = 'lower', string $lang = 'en', bool $detailed = false): array`
  - `getStats(string $lang = 'en'): array`
  - `getWards(string $district, string $municipality, string $lang = 'en', ?string $case = 'lower'): ?array`
  - `getMunicipality(string $district, string $municipality, string $lang = 'both', ?string $case = 'title'): ?array`
  - `getCategories(string $lang = 'both'): array`

- [ ] **Step 1: Run the existing tests to confirm the starting point is green**

Run: `php artisan test`
Expected: PASS. Note the totals; they must not drop after the rewrite.

- [ ] **Step 2: Rewrite AddressService**

Replace the entire contents of `app/Services/AddressService.php`:

```php
<?php

namespace App\Services;

use App\Services\Nepal\AddressPresenter;
use App\Services\Nepal\DatasetRepository;
use App\Services\Nepal\NepaliNumeral;
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
                $this->repository->districts()
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

        foreach ($this->repository->districts() as $district) {
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

        foreach ($this->repository->districts() as $district) {
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

    /** Match against the legacy name, the upstream name, and the Nepali name. */
    private function matches(array $record, string $lowerNeedle): bool
    {
        foreach ([$record['legacy_name'], $record['name']] as $candidate) {
            if (str_contains(mb_strtolower($candidate), $lowerNeedle)) {
                return true;
            }
        }

        return str_contains($record['name_np'], $lowerNeedle);
    }

    public function getAllHierarchy(?string $case = 'lower', string $lang = 'en', bool $detailed = false): array
    {
        $case ??= 'lower';
        $cacheKey = "nepal_hierarchy_{$case}_{$lang}_".($detailed ? '1' : '0');

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
        return Cache::rememberForever("nepal_stats_{$lang}", function () use ($lang) {
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
```

- [ ] **Step 3: Run the full suite**

Run: `php artisan test`

Expected: `LegacyContractTest` and `AddressApiTest` both PASS.

Three deliberate additions will make `LegacyContractTest` fail on exactly three URIs — `/api/stats`, `/api/hierarchy`, and `/api/all` — because they gained `total_wards`, `wards_count`, and `municipalities_by_category`. **These are additive keys the spec calls for.** Confirm the failure is additive-only:

```bash
php artisan test --filter=LegacyContractTest 2>&1 | grep -A5 'api/stats'
```

Every other URI must pass untouched. If any `/api/provinces`, `/api/districts`, or `/api/municipals` URI fails, the rewrite has a real bug — fix it, do not re-record.

- [ ] **Step 4: Re-record the snapshot for the three intentionally-changed URIs**

Only after confirming the diff is additive:

```bash
RECORD_LEGACY_SNAPSHOT=1 php artisan test --filter=RecordLegacySnapshotTest
php artisan test
```

Expected: full suite PASS.

- [ ] **Step 5: Verify the old data files are no longer read**

```bash
grep -rn "districtsByProvince\|municipalsByDistrict" app/ || echo "clean"
```

Expected: only `app/Console/Commands/BuildNepalDataset.php` (which reads them to derive `legacy_name`). `AddressService` must not appear.

- [ ] **Step 6: Commit**

```bash
git add app/Services/AddressService.php tests/fixtures/legacy-api-snapshot.json
git commit -m "refactor: rewrite AddressService over canonical dataset"
```

---

### Task 8: Query parameter validation

Add `lang` and `detailed` to the controller, and reject invalid values with 422 rather than silently defaulting.

**Files:**
- Modify: `app/Http/Controllers/api/JsonDataController.php`
- Test: `tests/Feature/QueryParameterTest.php`

**Interfaces:**
- Consumes: `AddressPresenter::LANGS`, `AddressPresenter::CASES` (Task 6); `AddressService` (Task 7).
- Produces: on `JsonDataController`, `protected function params(Request $request): array` returning `['case' => string, 'lang' => string, 'detailed' => bool]`, throwing `HttpResponseException` (422) on invalid input.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/QueryParameterTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class QueryParameterTest extends TestCase
{
    public function test_rejects_unknown_lang(): void
    {
        $this->getJson('/api/districts?lang=fr')
            ->assertStatus(422)
            ->assertJsonStructure(['error', 'accepted']);
    }

    public function test_rejects_unknown_case(): void
    {
        $this->getJson('/api/districts?case=shouty')
            ->assertStatus(422)
            ->assertJsonStructure(['error', 'accepted']);
    }

    public function test_accepts_every_valid_lang(): void
    {
        foreach (['en', 'np', 'both'] as $lang) {
            $this->getJson("/api/districts?lang={$lang}")->assertStatus(200);
        }
    }

    public function test_flat_mode_defaults_to_english(): void
    {
        $districts = $this->getJson('/api/districts')->json('districts');

        $this->assertIsString($districts[0]);
        $this->assertContains('kathmandu', $districts);
    }

    public function test_detailed_mode_defaults_to_both_languages(): void
    {
        $districts = $this->getJson('/api/districts?detailed=true')->json('districts');

        $this->assertIsArray($districts[0]);
        $this->assertArrayHasKey('name', $districts[0]);
        $this->assertArrayHasKey('name_np', $districts[0]);
    }

    public function test_lang_np_returns_devanagari_strings(): void
    {
        $districts = $this->getJson('/api/districts?lang=np')->json('districts');

        $this->assertIsString($districts[0]);
        $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $districts[0]);
    }

    public function test_lang_both_returns_paired_objects(): void
    {
        $districts = $this->getJson('/api/districts?lang=both')->json('districts');

        $this->assertArrayHasKey('name', $districts[0]);
        $this->assertArrayHasKey('name_np', $districts[0]);
    }

    public function test_detailed_districts_carry_ward_totals(): void
    {
        $districts = $this->getJson('/api/districts?detailed=true')->json('districts');

        $kathmandu = collect($districts)->firstWhere('slug', 'kathmandu');
        $this->assertSame(11, $kathmandu['total_municipalities']);
        $this->assertGreaterThan(0, $kathmandu['total_wards']);
    }

    public function test_detailed_accepts_truthy_spellings(): void
    {
        foreach (['true', '1'] as $value) {
            $districts = $this->getJson("/api/districts?detailed={$value}")->json('districts');
            $this->assertIsArray($districts[0], "detailed={$value} did not enable detailed mode");
        }

        foreach (['false', '0'] as $value) {
            $districts = $this->getJson("/api/districts?detailed={$value}")->json('districts');
            $this->assertIsString($districts[0], "detailed={$value} should stay flat");
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=QueryParameterTest`
Expected: FAIL — invalid `lang` returns 200 instead of 422, and `detailed=true` still returns strings.

- [ ] **Step 3: Update the controller**

Replace `app/Http/Controllers/api/JsonDataController.php` with:

```php
<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\AddressService;
use App\Services\Nepal\AddressPresenter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JsonDataController extends Controller
{
    public function __construct(
        protected AddressService $addressService
    ) {}

    protected function cachedResponse(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, [
            'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
            'ETag' => md5(json_encode($data)),
        ]);
    }

    /**
     * Resolve case/lang/detailed. `lang` defaults to 'en' in flat mode and
     * 'both' in detailed mode: a flat default of 'both' would change the
     * shape of existing responses.
     */
    protected function params(Request $request): array
    {
        $case = (string) $request->query('case', 'lower');
        $detailed = filter_var($request->query('detailed', false), FILTER_VALIDATE_BOOLEAN);
        $lang = (string) $request->query('lang', $detailed ? 'both' : 'en');

        if (! in_array($case, AddressPresenter::CASES, true)) {
            $this->reject('case', $case, AddressPresenter::CASES);
        }

        if (! in_array($lang, AddressPresenter::LANGS, true)) {
            $this->reject('lang', $lang, AddressPresenter::LANGS);
        }

        return ['case' => $case, 'lang' => $lang, 'detailed' => $detailed];
    }

    private function reject(string $name, string $value, array $accepted): never
    {
        throw new HttpResponseException(response()->json([
            'error' => "Invalid value \"{$value}\" for parameter \"{$name}\".",
            'accepted' => $accepted,
        ], 422));
    }

    public function getProvinces(Request $request): JsonResponse
    {
        ['case' => $case, 'lang' => $lang, 'detailed' => $detailed] = $this->params($request);

        return $this->cachedResponse(
            $this->addressService->getProvinces($case, $lang, $detailed)
        );
    }

    public function getDistricts(Request $request): JsonResponse
    {
        ['case' => $case, 'lang' => $lang, 'detailed' => $detailed] = $this->params($request);

        return $this->cachedResponse(
            $this->addressService->getDistricts($case, $lang, $detailed)
        );
    }

    public function getDistrictsByProvince(Request $request, string $provinceName): JsonResponse
    {
        ['case' => $case, 'lang' => $lang, 'detailed' => $detailed] = $this->params($request);
        $data = $this->addressService->getDistrictsByProvince($provinceName, $case, $lang, $detailed);

        if ($data === null) {
            return response()->json(['error' => 'Province not found'], 404);
        }

        return $this->cachedResponse($data);
    }

    public function getMunicipalsByDistrict(Request $request, string $districtName): JsonResponse
    {
        ['case' => $case, 'lang' => $lang, 'detailed' => $detailed] = $this->params($request);
        $data = $this->addressService->getMunicipalsByDistrict($districtName, $case, $lang, $detailed);

        if ($data === null) {
            return response()->json(['error' => 'District not found'], 404);
        }

        return $this->cachedResponse($data);
    }

    public function search(Request $request): JsonResponse
    {
        ['case' => $case, 'lang' => $lang, 'detailed' => $detailed] = $this->params($request);
        $query = (string) $request->query('q', '');
        $limit = min(50, max(1, (int) $request->query('limit', 20)));

        if (trim($query) === '') {
            return response()->json(['error' => 'Query parameter "q" is required.'], 422);
        }

        return $this->cachedResponse(
            $this->addressService->search($query, $case, $limit, $lang, $detailed)
        );
    }

    public function getAllHierarchy(Request $request): JsonResponse
    {
        ['case' => $case, 'lang' => $lang, 'detailed' => $detailed] = $this->params($request);

        return $this->cachedResponse(
            $this->addressService->getAllHierarchy($case, $lang, $detailed)
        );
    }

    public function getStats(Request $request): JsonResponse
    {
        ['lang' => $lang] = $this->params($request);

        return $this->cachedResponse($this->addressService->getStats($lang));
    }

    public function getWards(Request $request, string $districtName, string $municipalityName): JsonResponse
    {
        ['case' => $case, 'lang' => $lang] = $this->params($request);
        $data = $this->addressService->getWards($districtName, $municipalityName, $lang, $case);

        if ($data === null) {
            return response()->json(['error' => 'Municipality not found'], 404);
        }

        return $this->cachedResponse($data);
    }

    public function getMunicipality(Request $request, string $districtName, string $municipalityName): JsonResponse
    {
        ['case' => $case, 'lang' => $lang] = $this->params($request);
        $data = $this->addressService->getMunicipality($districtName, $municipalityName, $lang, $case);

        if ($data === null) {
            return response()->json(['error' => 'Municipality not found'], 404);
        }

        return $this->cachedResponse($data);
    }

    public function getCategories(Request $request): JsonResponse
    {
        ['lang' => $lang] = $this->params($request);

        return $this->cachedResponse($this->addressService->getCategories($lang));
    }
}
```

Note: `getStats` now takes a `Request`. Its route needs no change; Laravel injects it.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=QueryParameterTest`
Expected: PASS, 9 tests.

Then the full suite: `php artisan test`
Expected: PASS. `LegacyContractTest` must stay green — defaults were chosen to preserve it.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/api/JsonDataController.php tests/Feature/QueryParameterTest.php
git commit -m "feat: add lang and detailed query parameters with validation"
```

---

### Task 9: Ward, municipality detail, and category routes

The controller methods already exist from Task 8. This task wires the routes and tests them.

**Files:**
- Modify: `routes/api.php`
- Test: `tests/Feature/WardApiTest.php`

**Interfaces:**
- Consumes: `JsonDataController::getWards`, `getMunicipality`, `getCategories` (Task 8).
- Produces: three routes.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/WardApiTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class WardApiTest extends TestCase
{
    public function test_lists_wards_for_a_municipality(): void
    {
        $response = $this->getJson('/api/wards/chitwan/bharatpur');

        $response->assertStatus(200)
            ->assertJsonStructure(['municipality', 'district', 'total_wards', 'wards']);

        $total = $response->json('total_wards');
        $this->assertGreaterThan(0, $total);
        $this->assertCount($total, $response->json('wards'));
        $this->assertSame(1, $response->json('wards.0.ward'));
        $this->assertSame($total, $response->json('wards.'.($total - 1).'.ward'));
    }

    public function test_wards_accept_the_legacy_municipality_name(): void
    {
        $a = $this->getJson('/api/wards/chitwan/bharatpur');
        $b = $this->getJson('/api/wards/chitwan/bharatpur-metropolitan-city');

        $b->assertStatus(200);
        $this->assertSame($a->json('total_wards'), $b->json('total_wards'));
    }

    public function test_wards_in_nepali_use_devanagari_numerals(): void
    {
        $response = $this->getJson('/api/wards/chitwan/bharatpur?lang=np');

        $response->assertStatus(200);
        $this->assertSame('१', $response->json('wards.0.ward'));
        $this->assertSame('२', $response->json('wards.1.ward'));
    }

    public function test_wards_in_both_languages_carry_paired_numerals(): void
    {
        $response = $this->getJson('/api/wards/chitwan/bharatpur?lang=both');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('wards.0.ward'));
        $this->assertSame('१', $response->json('wards.0.ward_np'));
    }

    public function test_unknown_municipality_returns_404(): void
    {
        $this->getJson('/api/wards/chitwan/springfield')
            ->assertStatus(404)
            ->assertJson(['error' => 'Municipality not found']);
    }

    public function test_unknown_district_returns_404(): void
    {
        $this->getJson('/api/wards/gotham/springfield')->assertStatus(404);
    }

    public function test_ward_route_resists_path_traversal(): void
    {
        $response = $this->getJson('/api/wards/../../etc/passwd/x');
        $this->assertContains($response->status(), [404, 400]);
    }

    public function test_municipality_detail(): void
    {
        $response = $this->getJson('/api/municipality/chitwan/bharatpur');

        $response->assertStatus(200)
            ->assertJsonStructure(['municipality', 'district', 'province']);

        $this->assertSame('Bharatpur', $response->json('municipality.name'));
        $this->assertSame('Metropolitan City', $response->json('municipality.category'));
        $this->assertMatchesRegularExpression(
            '/\p{Devanagari}/u',
            $response->json('municipality.name_np')
        );
        $this->assertGreaterThan(0, $response->json('municipality.wards'));
    }

    public function test_categories_endpoint(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);
        $categories = $response->json('categories');

        $this->assertCount(4, $categories);
        $this->assertSame('Metropolitan City', $categories[0]['name']);
        $this->assertSame('महानगरपालिका', $categories[0]['name_np']);
        $this->assertSame('MC', $categories[0]['short_code']);
    }

    public function test_every_municipality_has_reachable_wards(): void
    {
        $districts = $this->getJson('/api/districts')->json('districts');
        $checked = 0;

        foreach ($districts as $district) {
            $slug = str_replace(' ', '-', $district);
            $municipals = $this->getJson("/api/municipals/{$slug}?detailed=true")->json('municipals');

            foreach ($municipals as $municipality) {
                $response = $this->getJson("/api/wards/{$slug}/{$municipality['slug']}");
                $this->assertSame(
                    200,
                    $response->status(),
                    "Wards unreachable for {$municipality['slug']} in {$slug}"
                );
                $checked++;
            }
        }

        $this->assertSame(753, $checked);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=WardApiTest`
Expected: FAIL with 404s — the routes do not exist yet.

- [ ] **Step 3: Add the routes**

Append to `routes/api.php`:

```php
// Ward & Detail Endpoints
Route::get('/wards/{districtName}/{municipalityName}', [JsonDataController::class, 'getWards']);
Route::get('/municipality/{districtName}/{municipalityName}', [JsonDataController::class, 'getMunicipality']);
Route::get('/categories', [JsonDataController::class, 'getCategories']);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=WardApiTest`
Expected: PASS, 10 tests. The last one walks all 753 municipalities and is slow (~30s); that is expected.

Then: `php artisan test`
Expected: full suite PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/api.php tests/Feature/WardApiTest.php
git commit -m "feat: add ward, municipality detail, and category endpoints"
```

---

### Task 10: Nepali search

Search must match Devanagari queries. The matching logic landed in Task 7; this task proves it and covers the `lang`/`detailed` interaction.

**Files:**
- Test: `tests/Feature/NepaliSearchTest.php`
- Modify (only if tests fail): `app/Services/AddressService.php`

**Interfaces:**
- Consumes: `AddressService::search` (Task 7).
- Produces: no new interface; this task is verification and any fixes it surfaces.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/NepaliSearchTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class NepaliSearchTest extends TestCase
{
    public function test_finds_a_district_by_its_nepali_name(): void
    {
        $response = $this->getJson('/api/search?q='.urlencode('काठमाडौं'));

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('total'));

        $types = array_column($response->json('results'), 'type');
        $this->assertContains('district', $types);
    }

    public function test_finds_a_province_by_its_nepali_name(): void
    {
        $response = $this->getJson('/api/search?q='.urlencode('बाग्मती'));

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    public function test_nepali_search_can_return_nepali_results(): void
    {
        $response = $this->getJson('/api/search?q='.urlencode('काठमाडौं').'&lang=np');

        $response->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/\p{Devanagari}/u',
            $response->json('results.0.name')
        );
    }

    public function test_english_search_still_works(): void
    {
        $response = $this->getJson('/api/search?q=bharatpur');

        $response->assertStatus(200);
        $this->assertSame('bharatpur metropolitan city', $response->json('results.0.name'));
        $this->assertSame('municipality', $response->json('results.0.type'));
        $this->assertSame('chitwan', $response->json('results.0.district'));
        $this->assertSame('bagmati', $response->json('results.0.province'));
    }

    public function test_detailed_search_results_carry_wards(): void
    {
        $response = $this->getJson('/api/search?q=bharatpur&detailed=true');

        $response->assertStatus(200);
        $this->assertArrayHasKey('wards', $response->json('results.0.name'));
    }

    public function test_search_respects_the_limit(): void
    {
        $response = $this->getJson('/api/search?q=a&limit=5');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, count($response->json('results')));
    }

    public function test_search_is_fast(): void
    {
        $start = microtime(true);
        $this->getJson('/api/search?q=rural')->assertStatus(200);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(2.0, $elapsed, 'Search took over 2s; the index is not being used');
    }
}
```

- [ ] **Step 2: Run test to verify current state**

Run: `php artisan test --filter=NepaliSearchTest`
Expected: PASS if Task 7 was implemented correctly. If any test fails, fix `AddressService::matches()` or `search()` — do not weaken the test.

- [ ] **Step 3: Verify stats gained ward data**

```bash
php artisan test --filter=DatasetIntegrityTest
php -r '
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$stats = $app->make(App\Services\AddressService::class)->getStats();
echo "total_wards: {$stats["total_wards"]}\n";
echo "categories: ".count($stats["municipalities_by_category"])."\n";
'
```

Expected: `total_wards: 6743` and `categories: 4`.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/NepaliSearchTest.php app/Services/AddressService.php
git commit -m "test: cover Nepali search and ward statistics"
```

---

### Task 11: Regenerate legacy static files and remove duplicates

`public/data/` is a public directory; the old per-district files may be fetched directly as static assets. Regenerate them from canonical data so they stay accurate, and delete only the true duplicates.

**Files:**
- Modify: `app/Console/Commands/BuildNepalDataset.php`
- Test: `tests/Feature/LegacyStaticFilesTest.php`
- Delete: `public/data/municipalsByDistrict/illam.json`, `public/data/municipalsByDistrict/eastern rukum.json`, `public/data/districtsByProvince/pradesh-1.json`

**Interfaces:**
- Consumes: canonical datasets (Task 3).
- Produces: `BuildNepalDataset::writeLegacyFiles(array $provinces, array $districts, array $municipalities): void`, called at the end of `handle()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/LegacyStaticFilesTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegacyStaticFilesTest extends TestCase
{
    public function test_duplicate_files_are_gone(): void
    {
        $this->assertFileDoesNotExist(public_path('data/municipalsByDistrict/illam.json'));
        $this->assertFileDoesNotExist(public_path('data/municipalsByDistrict/eastern rukum.json'));
        $this->assertFileDoesNotExist(public_path('data/districtsByProvince/pradesh-1.json'));
    }

    public function test_every_district_has_a_static_municipal_file_matching_the_api(): void
    {
        foreach ($this->getJson('/api/districts')->json('districts') as $district) {
            $slug = str_replace(' ', '-', $district);
            $path = public_path("data/municipalsByDistrict/{$slug}.json");

            $this->assertFileExists($path, "Missing static file for {$district}");

            $static = json_decode(file_get_contents($path), true)['municipals'];
            $api = $this->getJson("/api/municipals/{$slug}")->json('municipals');

            $this->assertSame($api, $static, "Static file for {$district} disagrees with the API");
        }
    }

    public function test_every_province_has_a_static_district_file_matching_the_api(): void
    {
        foreach ($this->getJson('/api/provinces')->json('provinces') as $province) {
            $path = public_path("data/districtsByProvince/{$province}.json");

            $this->assertFileExists($path, "Missing static file for {$province}");

            $static = json_decode(file_get_contents($path), true)['districts'];
            $api = $this->getJson("/api/districts/{$province}")->json('districts');

            $this->assertSame($api, $static, "Static file for {$province} disagrees with the API");
        }
    }

    public function test_top_level_lists_match_the_api(): void
    {
        $provinces = json_decode(file_get_contents(public_path('data/provinces.json')), true);
        $this->assertSame(
            $this->getJson('/api/provinces')->json('provinces'),
            array_column($provinces, 'legacy_name')
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LegacyStaticFilesTest`
Expected: FAIL — the duplicate files still exist.

- [ ] **Step 3: Add legacy file generation to the build command**

In `app/Console/Commands/BuildNepalDataset.php`, add this call at the end of `handle()`, immediately before the `$this->info(...)` line:

```php
        $this->writeLegacyFiles($provinces, $districts, $municipalities);
```

Then add these methods to the class:

```php
    /**
     * Regenerate the pre-existing per-province and per-district files from
     * canonical data. They are public static URLs, so they keep working.
     */
    private function writeLegacyFiles(array $provinces, array $districts, array $municipalities): void
    {
        foreach (['districtsByProvince', 'municipalsByDistrict'] as $dir) {
            if (! is_dir(public_path("data/{$dir}"))) {
                mkdir(public_path("data/{$dir}"), 0755, true);
            }
        }

        $districtsByProvince = [];
        foreach ($districts as $district) {
            $districtsByProvince[$district['province_id']][] = $district['legacy_name'];
        }

        foreach ($provinces as $province) {
            $this->writeJson(
                public_path("data/districtsByProvince/{$province['slug']}.json"),
                ['districts' => $districtsByProvince[$province['id']] ?? []]
            );
        }

        $municipalsByDistrict = [];
        foreach ($municipalities as $municipality) {
            $municipalsByDistrict[$municipality['district_id']][] = $municipality['legacy_name'];
        }

        foreach ($districts as $district) {
            $this->writeJson(
                public_path("data/municipalsByDistrict/{$district['slug']}.json"),
                ['municipals' => $municipalsByDistrict[$district['id']] ?? []]
            );
        }

        foreach ($this->staleFiles() as $path) {
            if (file_exists($path)) {
                unlink($path);
                $this->line("Removed duplicate: {$path}");
            }
        }
    }

    /** Files superseded by canonical data; each duplicates another file. */
    private function staleFiles(): array
    {
        return [
            public_path('data/municipalsByDistrict/illam.json'),
            public_path('data/municipalsByDistrict/eastern rukum.json'),
            public_path('data/districtsByProvince/pradesh-1.json'),
        ];
    }

    private function writeJson(string $path, array $payload): void
    {
        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
```

Important: `legacyMunicipals()` reads from `municipalsByDistrict/`, and `writeLegacyFiles()` overwrites it. Because `handle()` completes all reads before any write, this is safe — but do not reorder them.

- [ ] **Step 4: Rebuild and run the tests**

```bash
php artisan nepal:build-dataset
php artisan test --filter=LegacyStaticFilesTest
```

Expected: PASS, 4 tests.

Then confirm nothing regressed: `php artisan test`
Expected: full suite PASS, including `AddressApiTest` and `LegacyContractTest`.

- [ ] **Step 5: Verify the build is idempotent**

Running the build twice must produce no diff. This proves the regenerated files can still seed `legacy_name` on the next run:

```bash
php artisan nepal:build-dataset
git status --porcelain public/data
php artisan nepal:build-dataset
git status --porcelain public/data
php artisan test
```

Expected: the second run changes nothing further, and the suite still passes.

- [ ] **Step 6: Commit**

```bash
git add -A public/data app/Console/Commands/BuildNepalDataset.php tests/Feature/LegacyStaticFilesTest.php
git commit -m "chore: regenerate legacy static files and drop duplicates"
```

---

### Task 12: Documentation

**Files:**
- Modify: `README.md`
- Modify: `resources/views/welcome.blade.php`

**Interfaces:**
- Consumes: every endpoint and parameter from Tasks 8 and 9.
- Produces: no code interface.

- [ ] **Step 1: Capture real example responses**

Do not hand-write examples. Generate them so the docs cannot drift:

```bash
php artisan serve --port=8123 &
sleep 3
for u in "/api/districts?detailed=true" "/api/districts?lang=np" "/api/wards/chitwan/bharatpur?lang=both" "/api/municipality/chitwan/bharatpur" "/api/categories" "/api/stats"; do
  echo "### GET $u"
  curl -s "http://127.0.0.1:8123$u" | head -c 700
  echo
done
kill %1
```

Keep this output; paste the real payloads into the README.

- [ ] **Step 2: Update the README**

Add a **Ward & Nepali Support** section documenting:

- The three new routes with a real example response each, from Step 1.
- This parameter table:

```markdown
| Parameter  | Values                | Default                                 | Notes                                    |
|------------|-----------------------|-----------------------------------------|------------------------------------------|
| `lang`     | `en`, `np`, `both`    | `en` flat, `both` when `detailed=true`  | `np` returns Devanagari                  |
| `detailed` | `true`, `false`       | `false`                                 | Objects with ids, wards, area, website   |
| `case`     | `lower`, `title`      | `lower`                                 | English only; Devanagari has no case     |
```

- A **Data & Attribution** section:

```markdown
## Data & Attribution

Administrative data is derived from
[sagautam5/local-states-nepal](https://github.com/sagautam5/local-states-nepal)
(MIT, Copyright (c) 2020 Sagar Gautam): 7 provinces, 77 districts,
753 municipalities, 6,743 wards, each with English and Nepali names.

Regenerate the canonical dataset with:

    php artisan nepal:build-dataset

Wards are identified by number. No open dataset provides ward names, so the
API enumerates wards 1..N rather than inventing names.
```

- A backward-compatibility note:

```markdown
### Backward compatibility

Existing endpoints return exactly what they always have. Nepali names and ward
data are opt-in through `lang` and `detailed`. `tests/Feature/LegacyContractTest.php`
replays a recorded snapshot of every legacy response on each test run.
```

- [ ] **Step 3: Update the landing page**

In `resources/views/welcome.blade.php`, add the three new endpoints to the endpoint list, matching the existing markup exactly. Add one Nepali example (`/api/districts?lang=np`) and one ward example (`/api/wards/chitwan/bharatpur`). Do not restyle the page.

- [ ] **Step 4: Verify every documented endpoint actually works**

```bash
php artisan serve --port=8123 &
sleep 3
grep -oE '/api/[a-zA-Z0-9/_?=&.-]+' README.md | sort -u | while read -r u; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:8123$u")
  echo "$code $u"
done
kill %1
```

Expected: every line reads `200`. Any other code means the README documents something that does not work — fix the README or the route.

- [ ] **Step 5: Run the full suite one final time**

Run: `php artisan test`
Expected: all tests PASS. Record the totals in the commit message.

- [ ] **Step 6: Commit**

```bash
git add README.md resources/views/welcome.blade.php
git commit -m "docs: document ward, Nepali, and detailed-mode support"
```

---

## Verification Checklist

Run this after Task 12. Every line must hold before the work is called done.

- [ ] `php artisan test` passes with zero failures
- [ ] `tests/Feature/AddressApiTest.php` is unmodified: `git diff --stat HEAD~12 -- tests/Feature/AddressApiTest.php` returns nothing
- [ ] `php artisan nepal:build-dataset` prints `Built 7 provinces, 77 districts, 753 municipalities, 6743 wards.`
- [ ] Running the build twice leaves `git status --porcelain public/data` empty
- [ ] `/api/stats` reports `total_wards: 6743`
- [ ] `/api/districts` (no params) is byte-identical to the recorded snapshot
- [ ] `/api/districts?lang=np` returns Devanagari
- [ ] `/api/wards/chitwan/bharatpur?lang=both` returns paired ASCII and Devanagari numerals
- [ ] `public/data/ATTRIBUTION.md` names the upstream project, its MIT license, and its copyright holder
