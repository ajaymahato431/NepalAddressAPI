# NepalAddressAPI

NepalAddressAPI is a Laravel-based API for managing Nepal address data such as provinces, districts, municipalities, wards, and related location records.

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

## API

The project is intended to expose address/location endpoints for Nepal. Add your route, controller, and resource documentation here as the API evolves.

## Contributing

Contributions are welcome. Please open a pull request with a clear description of the change.

## License

This project is open-sourced software licensed under the MIT license.
