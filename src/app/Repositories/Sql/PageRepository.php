<?php

namespace Andmarruda\Lpb\Repositories\Sql;

use Andmarruda\Lpb\Models\Sql\Page;
use Andmarruda\Lpb\Models\Sql\Revision;
use Andmarruda\Lpb\Repositories\BaseRepository;
use Andmarruda\Lpb\Repositories\Concerns\ManagesPageState;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\RevisionRepositoryInterface;

/**
 * SQL implementation of the Page repository.
 *
 * This repository handles page data stored in SQL databases using Eloquent.
 * It extends BaseRepository for common CRUD operations and adds SQL-specific
 * methods for page management including eager loading of relationships.
 */
class PageRepository extends BaseRepository implements PageRepositoryInterface
{
    use ManagesPageState;

    protected RevisionRepositoryInterface $revisions;

    /**
     * Create a new SQL page repository instance.
     *
     * @param Page $model The SQL Page model instance
     * @param RevisionRepository|null $revisions Defaults to the SQL revision repository
     */
    public function __construct(Page $model, ?RevisionRepository $revisions = null)
    {
        parent::__construct($model);
        $this->revisions = $revisions ?? new RevisionRepository(new Revision());
    }

    /**
     * Find a page by its slug.
     *
     * @param string $slug The page slug
     * @return array|null The page data as array or null if not found
     */
    public function findBySlug(string $slug): ?array
    {
        $page = $this->model->bySlug($slug)->first();
        return $page ? $page->toArray() : null;
    }

    /**
     * Retrieve all published pages.
     *
     * @return array Collection of published pages as array
     */
    public function getPublished(): array
    {
        return $this->model->published()->get()->toArray();
    }

    /**
     * Find a page with its blocks and their widget instances eager loaded.
     *
     * @param string|int $id The page identifier
     * @return array|null The page with blocks as array or null if not found
     */
    public function withBlocks(string|int $id): ?array
    {
        $page = $this->model->with('blocks.widgetInstances')->find($id);
        return $page ? $page->toArray() : null;
    }

    /**
     * Find a page with its metatags eager loaded.
     *
     * @param string|int $id The page identifier
     * @return array|null The page with metatags as array or null if not found
     */
    public function withMetatags(string|int $id): ?array
    {
        $page = $this->model->with('metatags')->find($id);
        return $page ? $page->toArray() : null;
    }

    /**
     * Retrieve pages by status.
     *
     * @param PublishState|string $status The page status (published, draft, archived)
     * @return array Collection of pages with the given status
     */
    public function getByStatus(PublishState|string $status): array
    {
        $status = $status instanceof PublishState ? $status->value : $status;
        return $this->model->where('status', $status)->get()->toArray();
    }

    /**
     * Set or update a metatag in an existing page.
     *
     * @param string|int $pageId The page identifier
     * @param string $name The metatag name
     * @param string $content The metatag content
     * @return array|null The updated page as array or null if not found
     */
    public function setMetatag(string|int $pageId, string $name, string $content): ?array
    {
        $page = $this->model->find($pageId);
        if (!$page) {
            return null;
        }

        $page->metatags()->updateOrCreate(['name' => $name], ['content' => $content]);

        return $this->withContent($page);
    }

    /**
     * Get a specific metatag value from a page.
     *
     * @param string|int $pageId The page identifier
     * @param string $name The metatag name
     * @return string|null The metatag content or null if not found
     */
    public function getMetatag(string|int $pageId, string $name): ?string
    {
        return $this->model->find($pageId)?->metatags()->where('name', $name)->value('content');
    }

    /**
     * Reload a page with its metatags.
     *
     * @param Page $page
     * @return array
     */
    protected function withContent(Page $page): array
    {
        return $page->fresh(['metatags'])->toArray();
    }

    /**
     * Page with blocks, widget instances and metatags.
     *
     * @param string|int $id
     * @return array
     */
    protected function snapshot(string|int $id): array
    {
        return $this->model->with(['blocks.widgetInstances', 'metatags'])->find($id)->toArray();
    }

    /**
     * Run writes in a database transaction.
     *
     * @param callable $callback
     * @return void
     */
    protected function atomically(callable $callback): void
    {
        $this->model->getConnection()->transaction($callback);
    }
}
