# Design: Ward Data and Nepali (Devanagari) Names for NepalAddressAPI

Date: 2026-09-13
Status: Approved, pending implementation plan

## Problem

The API serves provinces, districts, and municipalities as flat lowercase
English strings. It has no ward data and no Nepali names. Three gaps:

1. No ward information at any level.
2. No Devanagari names, which rules out the API for Nepali-language UIs.
3. The data layer is ~92 hand-maintained JSON files containing duplicates
   (`illam.json` and `ilam.json`, `eastern rukum.json` and `eastern-rukum.json`,
   `pradesh-1.json` and `koshi.json`) resolved at runtime by fuzzy filename
   matching and two hardcoded alias maps.

## Data Source

[sagautam5/local-states-nepal](https://github.com/sagautam5/local-states-nepal),
MIT licensed, Copyright (c) 2020 Sagar Gautam.

Verified contents:

| Dataset | Records | Fields |
|---|---|---|
| provinces | 7 | id, name, area_sq_km, website, headquarter |
| districts | 77 | id, province_id, name, area_sq_km, website, headquarter |
| municipalities | 753 | id, district_id, category_id, name, area_sq_km, website, wards |
| categories | 4 | id, name, short_code |

Every dataset ships as parallel `en.json` and `np.json`. Ward counts sum to
**6,743**, all numeric, no missing values.

### Known limitation

`wards` is a **count**, not named ward records. Nepali wards are identified by
number, and no open dataset provides ward names. The API will enumerate wards
`1..N` faithfully; it will not invent names.

### Reconciliation with existing data

Comparing upstream against the current repo:

- **Per-district municipality counts agree exactly** in every district.
- **147 of 720 municipality names differ in romanization only**
  (`nisikhola`/`nishikhola`, `sworgadwary`/`swargadwari`,
  `amachodingmo`/`aamachhodingmo`).
- **4 district names differ in romanization**: upstream `Acham`, `Pachthar`,
  `Parwat`, `Ramechap` vs. repo `achham`, `panchthar`, `parbat`, `ramechhap`.
- Current flat strings embed the type suffix (`bharatpur metropolitan city`);
  upstream splits this into `name: "Bharatpur"` plus `category_id: 1`.

Adopting upstream names verbatim would silently change ~20% of municipality
strings and break existing consumers. The repo's spellings are therefore
**pinned as `legacy_name`** and used for all backward-compatible output.

## Architecture

### Canonical dataset

`public/data/` is reduced to four bilingual files plus attribution:

```
provinces.json  districts.json  municipalities.json  categories.json  ATTRIBUTION.md
```

Merged municipality record:

```json
{
  "id": 27, "district_id": 27, "category_id": 1, "slug": "bharatpur",
  "name": "Bharatpur", "name_np": "भरतपुर",
  "legacy_name": "bharatpur metropolitan city",
  "category": "Metropolitan City", "category_np": "महानगरपालिका",
  "wards": 29, "area_sq_km": "432.95", "website": "..."
}
```

### Build command

`php artisan nepal:build-dataset` regenerates the canonical files:

1. Join upstream `en.json` and `np.json` by `id`.
2. Pair upstream names against existing repo names **within each district** by
   lowest Levenshtein distance. Safe because per-district counts match.
3. Write `legacy_name` from the repo spelling.
4. **Fail loudly** on any record that cannot be paired. Never guess.
5. Regenerate the legacy `districtsByProvince/` and `municipalsByDistrict/`
   files from canonical data so direct public-URL consumers keep working.
6. Delete only true duplicates: `illam.json`, `eastern rukum.json`,
   `pradesh-1.json`.

The command makes provenance reproducible and lets the dataset be refreshed
from upstream later.

### Service decomposition

`AddressService` is ~400 lines and would roughly triple. It is split into
single-purpose units:

| Unit | Responsibility |
|---|---|
| `App\Services\Nepal\DatasetRepository` | Load and cache the four files; build id and slug indexes |
| `App\Services\Nepal\SlugResolver` | Normalize input to a canonical record; owns all alias maps |
| `App\Services\Nepal\NepaliNumeral` | Convert `29` to `२९` and back |
| `App\Services\Nepal\AddressPresenter` | Shape records into flat or detailed output; apply `case` and `lang` |
| `App\Services\AddressService` | Thin facade; public method signatures unchanged |

The controller changes only to pass the new query parameters through.

This also fixes a performance defect: `search()` currently calls
`getProvinceForDistrict()` inside a loop over all 77 districts, causing
O(n^2) file lookups. The slug index reduces this to one linear pass over
837 records.

## API

### Existing routes

All seven keep their exact default response shape:

```
GET /api/provinces
GET /api/districts
GET /api/districts/{province}
GET /api/municipals/{district}
GET /api/search?q=
GET /api/all, /api/hierarchy
GET /api/stats
```

### New routes

```
GET /api/wards/{district}/{municipality}   enumerate wards 1..N
GET /api/municipality/{district}/{name}    single municipality detail
GET /api/categories                        the 4 local-body types, bilingual
```

### Query parameters

| Parameter | Values | Default |
|---|---|---|
| `detailed` | `true`, `false` | `false` |
| `lang` | `en`, `np`, `both` | `en` in flat mode, `both` when `detailed=true` |
| `case` | `lower`, `title` | `lower` |

The `lang` default deliberately differs by mode. A flat-mode default of `both`
would break the legacy contract; a detailed-mode default of `en` would
contradict the requirement that both languages be available together. Because
`detailed=true` is opt-in, no existing consumer observes the difference.

`case` applies to English output only. Devanagari has no letter case.

### Field naming

`name` always holds the name in the requested language. `name_np` appears only
when `lang=both`:

- `lang=en` produces `{"name": "Kathmandu"}`
- `lang=np` produces `{"name": "काठमाडौं"}`
- `lang=both` produces `{"name": "Kathmandu", "name_np": "काठमाडौं"}`

The same rule applies to `headquarter`/`headquarter_np`,
`category`/`category_np`, `area_sq_km`/`area_sq_km_np`, and `wards`/`wards_np`.

In flat mode, `lang=both` returns objects rather than strings, using the same
key names.

### Search

Matches against `name`, `name_np`, and `legacy_name`. A Devanagari query
matches Nepali names. Results respect `lang` and `detailed`.

### Stats

Gains `total_wards: 6743` and a per-category municipality breakdown, with
bilingual labels when requested.

## Error handling

- Unknown province: 404 `{"error": "Province not found"}` (unchanged)
- Unknown district: 404 `{"error": "District not found"}` (unchanged)
- Unknown municipality: 404 `{"error": "Municipality not found"}`
- Empty search query: 422 (unchanged)
- Invalid `lang` or `case` value: 422 with the accepted values listed
- Slug normalization continues to strip path-traversal sequences

## Testing

Test-driven. The existing `tests/Feature/AddressApiTest.php` runs **unmodified**
and is the backward-compatibility proof.

New coverage:

- Dataset integrity: 7 provinces, 77 districts, 753 municipalities, 6,743 wards
- Every municipality resolves a non-empty `legacy_name`
- Every legacy flat response matches the pre-change response exactly
- `lang=en`, `lang=np`, `lang=both` on every endpoint
- `detailed=true` on every endpoint
- Ward enumeration returns exactly `wards` entries with correct Devanagari numerals
- `NepaliNumeral` round-trips
- Nepali-text search
- New 404 and 422 paths

## Documentation

`README.md` gains ward and Nepali examples, the new routes, the parameter
table, and a data-source credit. `ATTRIBUTION.md` carries the upstream MIT
notice. The landing page gains examples for the new capabilities.

## Out of scope

- Ward names (no source exists)
- Ward-level geography or boundaries
- Wards embedded in `/api/all` and `/api/hierarchy`; 6,743 entries would bloat
  the payload, so ward counts are included but enumeration stays on its own route
- Migration to a database
