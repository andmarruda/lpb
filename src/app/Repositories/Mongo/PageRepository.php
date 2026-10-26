<?php

namespace Andmarruda\Lpb\Repositories\Mongo;

use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Repositories\BaseRepository;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;

/**
 * MongoDB implementation of the Page repository.
 *
 * This repository handles page data stored in MongoDB with embedded documents.
 * It extends BaseRepository for common CRUD operations. Unlike SQL, blocks and
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
     * Find a page with its blocks.
     *
     * Blocks are embedded in the page document, so no eager loading is needed;
     * they are shaped like the SQL output (ordered, with page_id and block_id).
     *
     * @param string|int $id The page identifier
     * @return array|null The page with blocks as array or null if not found
     */
    public function withBlocks(string|int $id): ?array
    {
        $page = $this->model->find($id);
        if (!$page) {
            return null;
        }

        $data = $page->toArray();
        $data['blocks'] = EmbeddedBlocks::presentAll((string) ($data['id'] ?? $data['_id']), $data['blocks'] ?? []);
        return $data;
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
