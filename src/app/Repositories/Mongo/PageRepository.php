<?php

namespace Andmarruda\Lpb\Repositories\Mongo;

use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Repositories\BaseRepository;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;

/**
 * MongoDB implementation of the Page repository.
 *
 * This repository handles page data stored in MongoDB with embedded documents.
 * It extends BaseRepository for common CRUD operations. Unlike SQL, widgets and
 * metatags are already embedded in the page document, so no eager loading is needed.
 */
class PageRepository extends BaseRepository implements PageRepositoryInterface
{
    /**
     * Create a new MongoDB page repository instance.
     *
     * @param Page $model The MongoDB Page model instance
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
     * Find a page with its widgets.
     *
     * In MongoDB, widgets are already embedded in the page document,
     * so this method simply returns the page without any eager loading.
     *
     * @param string|int $id The page identifier
     * @return array|null The page with embedded widgets as array or null if not found
     */
    public function withWidgets(string|int $id): ?array
    {
        $page = $this->model->find($id);
        return $page ? $page->toArray() : null;
    }

    /**
     * Find a page with its metatags.
     *
     * In MongoDB, metatags are already embedded in the page document,
     * so this method simply returns the page without any eager loading.
     *
     * @param string|int $id The page identifier
     * @return array|null The page with embedded metatags as array or null if not found
     */
    public function withMetatags(string|int $id): ?array
    {
        $page = $this->model->find($id);
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

    /**
     * Add a widget to an existing page.
     *
     * @param string|int $pageId The page identifier
     * @param array $widget The widget data to add
     * @return array|null The updated page as array or null if not found
     */
    public function addWidget(string|int $pageId, array $widget): ?array
    {
        $page = $this->model->find($pageId);
        if ($page) {
            $page->addWidget($widget);
            return $page->fresh()->toArray();
        }
        return null;
    }

    /**
     * Update a widget in an existing page.
     *
     * @param string|int $pageId The page identifier
     * @param int $widgetIndex The widget array index
     * @param array $data The widget data to update
     * @return array|null The updated page as array or null if not found
     */
    public function updateWidget(string|int $pageId, int $widgetIndex, array $data): ?array
    {
        $page = $this->model->find($pageId);
        if ($page) {
            $page->updateWidget($widgetIndex, $data);
            return $page->fresh()->toArray();
        }
        return null;
    }

    /**
     * Remove a widget from an existing page.
     *
     * @param string|int $pageId The page identifier
     * @param int $widgetIndex The widget array index
     * @return array|null The updated page as array or null if not found
     */
    public function removeWidget(string|int $pageId, int $widgetIndex): ?array
    {
        $page = $this->model->find($pageId);
        if ($page) {
            $page->removeWidget($widgetIndex);
            return $page->fresh()->toArray();
        }
        return null;
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
        if ($page) {
            $page->setMetatag($name, $content);
            return $page->fresh()->toArray();
        }
        return null;
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
        $page = $this->model->find($pageId);
        return $page?->getMetatag($name);
    }
}
