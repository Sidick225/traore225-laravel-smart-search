# Laravel Smart Search

[![Latest Version on
Packagist](https://img.shields.io/packagist/v/traore225/laravel-smart-search.svg?style=flat-square)](https://packagist.org/packages/traore225/laravel-smart-search)
[![Total
Downloads](https://img.shields.io/packagist/dt/traore225/laravel-smart-search.svg?style=flat-square)](https://packagist.org/packages/traore225/laravel-smart-search)
[![License](https://img.shields.io/packagist/l/traore225/laravel-smart-search.svg?style=flat-square)](LICENSE)

A lightweight, configurable, scoring-based search engine for Laravel.

Laravel Smart Search provides:

-   Exact match priority
-   Weighted scoring system
-   FULLTEXT support (MySQL/MariaDB)
-   Automatic fallback to LIKE search
-   Configurable columns
-   Safe FULLTEXT detection (no crashes)
-   Optional fallback disabling per request

------------------------------------------------------------------------

## Installation

Install via Composer:

``` bash
composer require traore225/laravel-smart-search
```

------------------------------------------------------------------------

## Requirements

- PHP 8.1+
- Laravel 10+ / 11+
- MySQL or MariaDB recommended for FULLTEXT support

------------------------------------------------------------------------

## Publish Configuration

``` bash
php artisan vendor:publish --tag=smart-search-config
```

This creates:

`config/smart-search.php`

------------------------------------------------------------------------

## FULLTEXT Setup (Recommended)

Generate a migration for your FULLTEXT index:

``` bash
php artisan smart-search:make-index --table=posts --columns=title
php artisan migrate
```

Check if FULLTEXT is installed:

``` bash
php artisan smart-search:install --table=posts --column=title
```

If you prefer to add it manually:

``` sql
ALTER TABLE posts ADD FULLTEXT (title);
```

> FULLTEXT is optional but recommended for performance.
> Smart Search automatically detects if FULLTEXT is available and safely disables it if missing.

------------------------------------------------------------------------

## Basic Usage

``` php
use Traore225\LaravelSmartSearch\Search\SearchEngine;

$engine = app(SearchEngine::class);

$query = \App\Models\Post::query();

$query = $engine->apply($query, [
    'description' => 'ps3 controller',
]);

$results = $query->paginate();
```

------------------------------------------------------------------------

## Disable Fallback (Per Request)

``` php
$query = $engine->apply($query, [
    'description' => 'ps3 controller',
    'fallback' => false,
]);
```

------------------------------------------------------------------------

## Configuration Example

``` php
return [
    'max_title_tokens' => 3,

    'columns' => [
        'title' => 'title',
        'description' => 'description',
    ],

    'fulltext' => [
        'enabled' => true,
        'multiplier' => 10,
    ],

    'weights' => [
        'exact_title' => 1000000,
        'title_word_base' => 4000,
        'title_word_step' => 500,
        'title_cumulative_base' => 3000,
        'title_cumulative_step' => 300,
    ],

    'fallback' => [
        'enabled' => true,
        'min_words' => 2,
        'fields' => ['title', 'description'],
    ],
];
```

------------------------------------------------------------------------

## How It Works

Priority order:

1.  Exact match
2.  Weighted LIKE scoring
3.  FULLTEXT boolean scoring (if available)
4.  Fallback LIKE search (if enabled)

FULLTEXT is auto-detected and safely disabled when the index is missing.

------------------------------------------------------------------------

## Database Support

| Database   | Support |
|------------|----------|
| MySQL      | Full |
| MariaDB    | Full |
| PostgreSQL | LIKE fallback only |
| SQLite     | LIKE fallback only |

------------------------------------------------------------------------

## Performance

-   FULLTEXT auto-detected and cached
-   No crash if index is missing
-   Minimal overhead
-   No external dependencies

------------------------------------------------------------------------

## License

MIT License © 2026 Traore Sidiki
