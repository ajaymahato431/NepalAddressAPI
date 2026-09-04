# NepalAddressAPI 🇳🇵

[![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20.svg?style=flat&logo=laravel)](https://laravel.com)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2+-777BB4.svg?style=flat&logo=php)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![Tests Passing](https://img.shields.io/badge/Tests-100%25%20Passed-brightgreen.svg)]()
[![753 Local Levels](https://img.shields.io/badge/Municipalities-753%20Verified-emerald.svg)]()
[![Part of Noted Insights](https://img.shields.io/badge/Part%20of-Noted%20Insights-6366f1.svg?style=flat)](https://notedinsights.com)

**NepalAddressAPI** is a high-performance, developer-friendly REST API for accessing authentic address and administrative division data of Nepal — covering all **7 Provinces**, **77 Districts**, and **753 Local Level Municipalities** (Metropolitan, Sub-Metropolitan, Municipalities, and Rural Municipalities).

> 💡 **Official Service of Noted Insights**: NepalAddressAPI is built, maintained, and publicly hosted as part of the [Noted Insights](https://notedinsights.com) ecosystem, dedicated to delivering reliable software utilities and study resources for Nepal.

---

## 🌟 Key Highlights

- **Zero-Database Requirement**: Runs directly out-of-the-box with static JSON datasets & file caching.
- **100% Data Accuracy**: Every single one of the 77 districts has verified local-level municipal data with zero missing entries or broken links.
- **Official & Dual Koshi / Pradesh-1 Support**: Seamlessly supports official constitutional names (`koshi`) and legacy aliases (`pradesh-1`, `province-1`).
- **Flexible Casing**: Returns lowercase by default or formatted **Title Case** (`?case=title`).
- **Fuzzy Search Endpoint**: Autocomplete `/api/search?q={term}` returns matching locations with parent district and province hierarchy.
- **Full Hierarchy / All Data Endpoint**: Download the entire nested tree (`/api/hierarchy` or `/api/all`) for client-side offline storage or cascading dropdowns.
- **Fast HTTP Caching**: Pre-configured with `Cache-Control` (`public, max-age=86400, stale-while-revalidate=604800`) and `ETag` headers.
- **Path Traversal Protection**: Inputs are strictly slugified and validated.

---

## 🚀 Live Demo & Base URL

- **Production Base URL:** `https://nepaladdress.notedinsights.com/api`
- **Interactive Documentation & Playground:** [nepaladdress.notedinsights.com](https://nepaladdress.notedinsights.com)
- **Official Portal:** [notedinsights.com](https://notedinsights.com)
- **Local Base URL:** `http://localhost:8000/api`

---

## 📚 API Endpoints Reference

### 1. Get All Provinces
Retrieve a list of all 7 provinces of Nepal.

- **Method:** `GET`
- **Endpoint:** `/api/provinces`
- **Query Parameters:**
  - `case` *(optional)*: `title` or `lower` (default: `lower`)

**Request Example:**
```bash
curl -X GET "https://nepaladdress.notedinsights.com/api/provinces?case=title"
```

**Response Example (200 OK):**
```json
{
  "provinces": [
    "Koshi",
    "Madhesh",
    "Bagmati",
    "Gandaki",
    "Lumbini",
    "Karnali",
    "Sudurpaschim"
  ]
}
```

---

### 2. Get All Districts
Retrieve a list of all 77 official districts in Nepal.

- **Method:** `GET`
- **Endpoint:** `/api/districts`
- **Query Parameters:**
  - `case` *(optional)*: `title` or `lower` (default: `lower`)

**Request Example:**
```bash
curl -X GET "https://nepaladdress.notedinsights.com/api/districts"
```

**Response Example (200 OK):**
```json
{
  "districts": [
    "achham",
    "arghakhanchi",
    "baglung",
    "baitadi",
    "chitwan",
    "kathmandu",
    "lalitpur",
    "nawalpur",
    "parasi",
    "sunsari",
    "tanahun"
  ]
}
```

---

### 3. Get Districts by Province
Retrieve all districts belonging to a specific province. Supports both official names (`koshi`), legacy aliases (`pradesh-1`, `province-1`), and is case-insensitive.

- **Method:** `GET`
- **Endpoint:** `/api/districts/{provinceName}`
- **Query Parameters:**
  - `case` *(optional)*: `title` or `lower` (default: `lower`)

**Request Example:**
```bash
curl -X GET "https://nepaladdress.notedinsights.com/api/districts/bagmati?case=title"
```

**Response Example (200 OK):**
```json
{
  "districts": [
    "Sindhuli",
    "Ramechhap",
    "Dolakha",
    "Bhaktapur",
    "Dhading",
    "Kathmandu",
    "Kavrepalanchok",
    "Lalitpur",
    "Nuwakot",
    "Rasuwa",
    "Sindhupalchok",
    "Chitwan",
    "Makwanpur"
  ]
}
```

---

### 4. Get Municipalities by District
Retrieve all local levels (Metropolitan, Sub-Metropolitan, Municipalities, Rural Municipalities) in a district. Handles spaces, hyphens, and aliases (e.g., `eastern-rukum`, `eastern rukum`, `tanahu`, `tanahun`).

- **Method:** `GET`
- **Endpoint:** `/api/municipals/{districtName}`
- **Query Parameters:**
  - `case` *(optional)*: `title` or `lower` (default: `lower`)

**Request Example:**
```bash
curl -X GET "https://nepaladdress.notedinsights.com/api/municipals/chitwan?case=title"
```

**Response Example (200 OK):**
```json
{
  "municipals": [
    "Ichchhyakamana Rural Municipality",
    "Bharatpur Metropolitan City",
    "Kalika Municipality",
    "Khairahani Municipality",
    "Madi Municipality",
    "Rapti Municipality",
    "Ratnanagar Municipality"
  ]
}
```

---

### 5. Global Search / Autocomplete
Fuzzy search across all provinces, districts, and municipalities. Ideal for live search bars and autocomplete components.

- **Method:** `GET`
- **Endpoint:** `/api/search`
- **Query Parameters:**
  - `q` *(required)*: Search keyword (e.g. `bharatpur`, `chitwan`, `koshi`)
  - `case` *(optional)*: `title` or `lower` (default: `lower`)
  - `limit` *(optional)*: Maximum results to return (default: `20`, max: `50`)

**Request Example:**
```bash
curl -X GET "https://nepaladdress.notedinsights.com/api/search?q=bharatpur&case=title"
```

**Response Example (200 OK):**
```json
{
  "query": "bharatpur",
  "total": 1,
  "results": [
    {
      "name": "Bharatpur Metropolitan City",
      "type": "municipality",
      "district": "Chitwan",
      "province": "Bagmati"
    }
  ]
}
```

---

### 6. Full Address Hierarchy
Download the entire country's address tree in a single request. Perfect for client-side caching in Redux, Pinia, Zustand, or localStorage to populate cascading dropdowns with zero further API calls.

- **Method:** `GET`
- **Endpoint:** `/api/hierarchy` *(or `/api/all`)*
- **Query Parameters:**
  - `case` *(optional)*: `title` or `lower` (default: `lower`)

**Response Example (200 OK):**
```json
{
  "country": "Nepal",
  "total_provinces": 7,
  "provinces": [
    {
      "province": "bagmati",
      "total_districts": 13,
      "districts": [
        {
          "district": "chitwan",
          "total_municipals": 7,
          "municipals": [
            "ichchhyakamana rural municipality",
            "bharatpur metropolitan city",
            "kalika municipality",
            "khairahani municipality",
            "madi municipality",
            "rapti municipality",
            "ratnanagar municipality"
          ]
        }
      ]
    }
  ]
}
```

---

### 7. Overview Statistics
Get high-level counts and breakdown per province.

- **Method:** `GET`
- **Endpoint:** `/api/stats`

**Response Example (200 OK):**
```json
{
  "country": "Nepal",
  "total_provinces": 7,
  "total_districts": 77,
  "total_municipalities": 753,
  "provinces_breakdown": [
    {
      "province": "koshi",
      "districts_count": 14,
      "municipals_count": 137
    },
    {
      "province": "madhesh",
      "districts_count": 8,
      "municipals_count": 136
    },
    {
      "province": "bagmati",
      "districts_count": 13,
      "municipals_count": 119
    },
    {
      "province": "gandaki",
      "districts_count": 11,
      "municipals_count": 85
    },
    {
      "province": "lumbini",
      "districts_count": 12,
      "municipals_count": 109
    },
    {
      "province": "karnali",
      "districts_count": 10,
      "municipals_count": 79
    },
    {
      "province": "sudurpaschim",
      "districts_count": 9,
      "municipals_count": 88
    }
  ]
}
```

---

## 💻 Integration Examples

### PHP (Laravel `Http` Client)

```php
use Illuminate\Support\Facades\Http;

$baseUrl = 'https://nepaladdress.notedinsights.com/api';

// 1. Get Provinces
$provinces = Http::get("{$baseUrl}/provinces", ['case' => 'title'])->json('provinces');

// 2. Get Districts of Bagmati
$districts = Http::get("{$baseUrl}/districts/bagmati", ['case' => 'title'])->json('districts');

// 3. Get Municipalities of Chitwan
$municipals = Http::get("{$baseUrl}/municipals/chitwan", ['case' => 'title'])->json('municipals');

// 4. Autocomplete search
$results = Http::get("{$baseUrl}/search", ['q' => 'bharatpur', 'case' => 'title'])->json('results');
```

### JavaScript / TypeScript (`fetch`)

```javascript
const BASE_URL = 'https://nepaladdress.notedinsights.com/api';

// 1. Get Provinces
const { provinces } = await fetch(`${BASE_URL}/provinces?case=title`).then(r => r.json());

// 2. Get Districts
const { districts } = await fetch(`${BASE_URL}/districts/bagmati?case=title`).then(r => r.json());

// 3. Get Municipalities
const { municipals } = await fetch(`${BASE_URL}/municipals/chitwan?case=title`).then(r => r.json());

// 4. Live Search
const { results } = await fetch(`${BASE_URL}/search?q=kathmandu&case=title`).then(r => r.json());
```

### Python (`requests`)

```python
import requests

BASE_URL = "https://nepaladdress.notedinsights.com/api"

# Get all provinces in Title Case
provinces = requests.get(f"{BASE_URL}/provinces", params={"case": "title"}).json()["provinces"]

# Get districts
districts = requests.get(f"{BASE_URL}/districts/bagmati").json()["districts"]

# Get municipalities
municipals = requests.get(f"{BASE_URL}/municipals/chitwan", params={"case": "title"}).json()["municipals"]
```

---

## 🛠️ Local Development & Setup

### Prerequisites
- PHP 8.2 or later
- Composer

### Installation Steps

1. **Clone the repository:**
   ```bash
   git clone https://github.com/ajaymahato431/NepalAddressAPI.git
   cd NepalAddressAPI
   ```

2. **Install Composer dependencies:**
   ```bash
   composer install
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Start local development server:**
   ```bash
   composer dev
   # or: php artisan serve
   ```

5. **Visit the interactive documentation and playground:**
   Open [http://localhost:8000](http://localhost:8000) in your browser.

---

## 🧪 Running Automated Tests

A comprehensive PHPUnit / Pest test suite is included, verifying:
- All provinces, districts, and 753 municipalities
- Dual Koshi / Pradesh-1 aliases
- Input sanitization and path traversal security
- Case formatting and search capabilities

```bash
composer test
# or: php artisan test
```

---

## 🗺️ Nepal Administrative Reference

| Province | Canonical Slug | Supported Aliases | Districts Count | Local Levels |
|---|---|---|---|---|
| **Koshi** | `koshi` | `pradesh-1`, `province-1` | 14 | 137 |
| **Madhesh** | `madhesh` | `pradesh-2`, `province-2` | 8 | 136 |
| **Bagmati** | `bagmati` | `pradesh-3`, `province-3` | 13 | 119 |
| **Gandaki** | `gandaki` | `pradesh-4`, `province-4` | 11 | 85 |
| **Lumbini** | `lumbini` | `pradesh-5`, `province-5` | 12 | 109 |
| **Karnali** | `karnali` | `pradesh-6`, `province-6` | 10 | 79 |
| **Sudurpaschim** | `sudurpaschim` | `pradesh-7`, `province-7`, `sudurpashchim` | 9 | 88 |
| **Total** | | | **77** | **753** |

---

## 🏢 Part of Noted Insights

NepalAddressAPI is proud to be part of the [**Noted Insights**](https://notedinsights.com) ecosystem, which builds free tools, educational study materials, and developer utilities for Nepal:

- 🌐 **Official Portal:** [notedinsights.com](https://notedinsights.com)
- 🇳🇵 **Nepali Unicode Typing & Converter:** [unicode.notedinsights.com](https://unicode.notedinsights.com)
- 🎓 **Capstone Projects & Notes:** [notedinsights.com/capstone-projects](https://notedinsights.com/capstone-projects/)
- 👨‍💻 **Lead Developer:** [Ajay Mahato](https://ajaymahato9988.com.np/)

---

## 🤝 Contributing

Contributions, issues, and feature requests are welcome! Feel free to open a pull request or file an issue.

## 📄 License

This project is open-source software licensed under the [MIT License](LICENSE).
Copyright (c) 2024–2026 Ajay Mahato. All rights reserved.
Crafted with care by Ajay Mahato for the Nepal Developer Community.
