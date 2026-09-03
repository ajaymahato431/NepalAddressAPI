# NepalAddressAPI

NepalAddressAPI is a Laravel-based REST API for accessing Nepal address data such as provinces, districts, and municipalities.

## Live API

**Base URL:** https://nepaladdress.notedinsights.com/

## API Endpoints

### GET /provinces
Retrieve a list of all provinces.

```bash
https://nepaladdress.notedinsights.com/api/provinces
```

### GET /districts
Retrieve a list of all districts.

```bash
https://nepaladdress.notedinsights.com/api/districts
```

### GET /districts/{provinceName}
Retrieve districts filtered by a specific province name.

```bash
https://nepaladdress.notedinsights.com/api/districts/{provinceName}
```

### GET /municipals/{districtName}
Retrieve municipalities filtered by a specific district name.

```bash
https://nepaladdress.notedinsights.com/api/municipals/{districtName}
```

## How to Use

Send HTTP GET requests to the endpoints above to retrieve JSON data. Use the provided routes to access province, district, and municipal data efficiently. For example, to get districts for a specific province, replace `{provinceName}` with the desired province name in the URL.

## Laravel Examples

```php
$response = Http::get('https://nepaladdress.notedinsights.com/api/provinces');
$response = Http::get('https://nepaladdress.notedinsights.com/api/districts');
$response = Http::get('https://nepaladdress.notedinsights.com/api/districts/bagmati');
$response = Http::get('https://nepaladdress.notedinsights.com/api/municipals/chitwan');
```

## Features

- REST API built with Laravel 12
- Sanctum authentication support
- Structured for Nepal address/location data
- Ready for extension with controllers, models, migrations, and seeders

## Tech Stack

- PHP 8.2+
- Laravel 12
- Laravel Sanctum

## Requirements

- PHP 8.2 or later
- Composer
- Node.js and npm
- SQLite, MySQL, PostgreSQL, or another Laravel-supported database

## Installation

```bash
git clone https://github.com/ajaymahato431/NepalAddressAPI.git
cd NepalAddressAPI
composer install
cp .env.example .env
php artisan key:generate
```

Configure your database credentials in `.env`, then run:

```bash
php artisan migrate
```

## Development

```bash
php artisan serve
```

If you are using the default Laravel frontend assets:

```bash
npm install
npm run dev
```

## Testing

```bash
php artisan test
```

## Project Structure

- `app/` — application logic
- `routes/` — API and web routes
- `database/migrations/` — database schema
- `database/seeders/` — sample data seeders
- `resources/` — views and frontend assets

## Contributing

Contributions are welcome. Please open a pull request with a clear description of the change.

## License

This project is open-sourced software licensed under the MIT license.
