# Laravel Page Builder (LPB)

Laravel page builder with dual-database support: SQL (Eloquent) and MongoDB.

## Features

- **Dual Database Support**: Choose between SQL or MongoDB
- **Repository Pattern**: Clean abstraction layer
- **Embedded Documents**: MongoDB stores blocks, widget instances, metatags and menu items inside their parent document
- **High Performance**: 1 query vs 4-20 queries (SQL) for page rendering
- **100% Test Coverage**: Unit and integration tests

## Installation

```bash
composer require andmarruda/lpb
```

### Configuration

Publish configuration file:

```bash
php artisan vendor:publish --tag=lpb-config
```

### SQL Driver (MySQL, PostgreSQL, SQLite)

```bash
php artisan vendor:publish --tag=lpb-migrations-sql
php artisan migrate
```

Configure `.env`:

```env
LPB_DATABASE_DRIVER=sql
LPB_SQL_CONNECTION=mysql
```

### MongoDB Driver

```bash
composer require mongodb/laravel-mongodb:^5.0
```

Configure `.env`:

```env
LPB_DATABASE_DRIVER=mongodb
LPB_MONGODB_CONNECTION=mongodb
```

Create the collections with their validators and indexes (safe to re-run):

```bash
php artisan lpb:install-mongo
```

See [docs/MONGODB_SCHEMA.md](docs/MONGODB_SCHEMA.md) for the document structure.

## Usage

### Create Page

```php
use Andmarruda\Lpb\Repositories\Sql\PageRepository;
use Andmarruda\Lpb\Models\Sql\Page;

$repo = new PageRepository(new Page());

$page = $repo->create([
    'title' => 'Home',
    'slug' => 'home',
    'status' => 'published'
]);
```

### Blocks and Widgets

Resolve repositories from the container so the configured driver is used:

```php
use Andmarruda\Lpb\Contracts\BlockRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;

$pages = app(PageRepositoryInterface::class);
$blocks = app(BlockRepositoryInterface::class);

$row = $blocks->create(['page_id' => $pageId, 'type' => 'row']);
$column = $blocks->create(['page_id' => $pageId, 'type' => 'column', 'parent_id' => $row['id']]);

$blocks->addWidgetInstance($column['id'], 'App\Widgets\HeroWidget', ['title' => 'Welcome']);

$pages->setMetatag($pageId, 'description', 'Page description');
$page = $pages->withBlocks($pageId);
```

## Testing

### Unit Tests

Value objects, casts and repositories with mocked models; no database or
Laravel application needed:

```bash
composer test
```

### Integration Tests (Docker)

Each script installs the package into a fresh Laravel application and runs
the unit and feature suites against a real database:

```bash
composer test:sqlite   # shell/test-sql.sh   - SQLite
composer test:mysql    # shell/test-mysql.sh - MySQL 8.4
composer test:mongo    # shell/test-mongo.sh - MongoDB 7 with lpb:install-mongo applied
composer test:all      # all three
```

The feature suites include a shared acceptance scenario
(`tests/Feature/Concerns/AcceptanceScenario.php`) that runs the same page
lifecycle on every driver through the repository contracts: template,
draft page, nested blocks with widget instances, SEO and metatags, publish
with a revision, loading by slug with all relations, unpublish, and
restoring the first revision.

MySQL `JSON` columns and MongoDB documents may return object keys (widget
config, media meta, revision payloads) in a different order than they were
written; values and list order are preserved.

## Performance

### SQL (Normalized)
- 4-20 queries per page load
- Requires eager loading
- 100-200ms average response time

### MongoDB (Embedded)
- 1 query per page load
- No eager loading needed
- 10-20ms average response time

**Result**: MongoDB is 10x faster for read-heavy workloads

## Architecture

```
src/
├── app/
│   ├── Contracts/
│   │   ├── RepositoryInterface.php
│   │   └── PageRepositoryInterface.php
│   ├── Models/
│   │   ├── Sql/
│   │   │   ├── Page.php
│   │   │   ├── PageWidget.php
│   │   │   └── Metatag.php
│   │   └── Mongo/
│   │       ├── Page.php (embedded widgets/metatags)
│   │       └── GlobalSetting.php
│   └── Repositories/
│       ├── BaseRepository.php
│       ├── Sql/
│       │   └── PageRepository.php
│       └── Mongo/
│           └── PageRepository.php
└── config/
    └── laravel-page-builder.php
```

## Requirements

- PHP 8.2+
- Laravel 10.x or 11.x
- For MongoDB: mongodb PHP extension

## License

MIT

## Credits

Created by Anderson Arruda
