# Repository Pattern Usage

## Architecture Overview

```
BaseRepository (abstract)
    ↓
    ├── EloquentRepository (deprecated, use BaseRepository)
    ├── MongoRepository (deprecated, use BaseRepository)
    ├── Sql/PageRepository (extends BaseRepository)
    └── Mongo/PageRepository (extends BaseRepository)
```

## Why BaseRepository?

The `BaseRepository` eliminates code duplication between SQL and MongoDB implementations:

- **Before**: EloquentRepository and MongoRepository had identical code
- **After**: Both extend BaseRepository, add only specific methods

## Creating a New Repository

### Step 1: Create the Model

```php
// src/app/Models/Sql/Page.php
namespace Andmarruda\Lpb\Models\Sql;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    // Model definition (no interface implementation!)
}
```

### Step 2: Create Repository Interface (if needed)

```php
// src/app/Contracts/PageRepositoryInterface.php
namespace Andmarruda\Lpb\Contracts;

interface PageRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug): ?array;
    public function getPublished(): array;
    // Add specific methods
}
```

### Step 3: Create Concrete Repository

```php
// src/app/Repositories/Sql/PageRepository.php
namespace Andmarruda\Lpb\Repositories\Sql;

use Andmarruda\Lpb\Models\Sql\Page;
use Andmarruda\Lpb\Repositories\BaseRepository;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;

class PageRepository extends BaseRepository implements PageRepositoryInterface
{
    public function __construct(Page $model)
    {
        parent::__construct($model);
    }

    // Add specific methods
    public function findBySlug(string $slug): ?array
    {
        return $this->model->bySlug($slug)->first()?->toArray();
    }
}
```

## Using Repositories

### In Service Provider

```php
$this->app->bind(PageRepositoryInterface::class, function ($app) {
    $driver = config('laravel-page-builder.database_driver', 'sql');

    return match ($driver) {
        'mongodb' => new \Andmarruda\Lpb\Repositories\Mongo\PageRepository(
            new \Andmarruda\Lpb\Models\Mongo\Page()
        ),
        'sql' => new \Andmarruda\Lpb\Repositories\Sql\PageRepository(
            new \Andmarruda\Lpb\Models\Sql\Page()
        ),
        default => new \Andmarruda\Lpb\Repositories\Sql\PageRepository(
            new \Andmarruda\Lpb\Models\Sql\Page()
        ),
    };
});
```

### In Controllers/Services

```php
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;

class PageService
{
    public function __construct(
        private PageRepositoryInterface $pageRepository
    ) {}

    public function getHomePage(): ?array
    {
        return $this->pageRepository->findBySlug('home');
    }

    public function getAllPublished(): array
    {
        return $this->pageRepository->getPublished();
    }
}
```

## Methods Available

### From BaseRepository (all repositories have these):

- `find(string|int $id): ?array` - Find by ID
- `all(): array` - Get all records
- `create(array $data): array` - Create new record
- `update(string|int $id, array $data): ?array` - Update record
- `delete(string|int $id): bool` - Delete record

### From PageRepositoryInterface (SQL and MongoDB):

- `findBySlug(string $slug): ?array` - Find by slug
- `getPublished(): array` - Get published pages
- `withWidgets(string|int $id): ?array` - Get with widgets
- `withMetatags(string|int $id): ?array` - Get with metatags
- `getByStatus(string $status): array` - Get by status

### MongoDB-specific (Mongo/PageRepository only):

- `addWidget(string|int $pageId, array $widget): ?array`
- `updateWidget(string|int $pageId, int $index, array $data): ?array`
- `removeWidget(string|int $pageId, int $index): ?array`
- `setMetatag(string|int $pageId, string $name, string $content): ?array`
- `getMetatag(string|int $pageId, string $name): ?string`

## Key Differences: SQL vs MongoDB

### SQL (Eager Loading Required)

```php
// Widgets are in separate table, need eager loading
public function withWidgets(string|int $id): ?array
{
    return $this->model->with('widgets')->find($id)?->toArray();
}
```

### MongoDB (Already Embedded)

```php
// Widgets are embedded in document, no eager loading needed
public function withWidgets(string|int $id): ?array
{
    return $this->model->find($id)?->toArray();
}
```

## Testing

```php
use Andmarruda\Lpb\Repositories\Sql\PageRepository;
use Andmarruda\Lpb\Models\Sql\Page;

$repository = new PageRepository(new Page());

// Create
$page = $repository->create([
    'title' => 'Home',
    'slug' => 'home',
    'status' => 'published'
]);

// Find
$found = $repository->findBySlug('home');

// Update
$updated = $repository->update($page['id'], ['title' => 'New Home']);

// Delete
$deleted = $repository->delete($page['id']);
```

## Benefits

✅ **No code duplication** - CRUD in BaseRepository
✅ **Reuses Model scopes** - `$this->model->published()`
✅ **Reuses Model relationships** - `$this->model->with('widgets')`
✅ **Testable** - Mock repository, not Model
✅ **Flexible** - SQL and MongoDB have different implementations when needed
✅ **Clean** - Repository implements interface, Model doesn't
