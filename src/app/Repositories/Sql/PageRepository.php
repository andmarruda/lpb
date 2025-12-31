<?php

namespace Andmarruda\Lpb\Repositories\Sql;

use Andmarruda\Lpb\Models\Sql\Page;
use Andmarruda\Lpb\Repositories\BaseRepository;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;

/**
 * SQL implementation of the Page repository.
 *
 * This repository handles page data stored in SQL databases using Eloquent.
 * It extends BaseRepository for common CRUD operations and adds SQL-specific
 * methods for page management including eager loading of relationships.
 */
class PageRepository extends BaseRepository implements PageRepositoryInterface
{
    /**
     * Create a new SQL page repository instance.
     *
     * @param Page $model The SQL Page model instance
     */
    public function __construct(Page $model)
    {
        parent::__construct($model);
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
     * Find a page with its widgets eager loaded.
     *
     * @param string|int $id The page identifier
     * @return array|null The page with widgets as array or null if not found
     */
    public function withWidgets(string|int $id): ?array
    {
        $page = $this->model->with('widgets')->find($id);
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
     * @param string $status The page status (published, draft, archived)
     * @return array Collection of pages with the given status
     */
    public function getByStatus(string $status): array
    {
        return $this->model->where('status', $status)->get()->toArray();
    }
}
