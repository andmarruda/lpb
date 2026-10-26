<?php

namespace Andmarruda\Lpb\Repositories\Mongo;

use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Models\Mongo\Revision;
use Andmarruda\Lpb\Models\Mongo\Template;
use Andmarruda\Lpb\Repositories\BaseRepository;
use Andmarruda\Lpb\Repositories\Concerns\ManagesPageState;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\RevisionRepositoryInterface;

/**
 * MongoDB implementation of the Page repository.
 *
 * This repository handles page data stored in MongoDB with embedded documents.
 * It extends BaseRepository for common CRUD operations. Unlike SQL, blocks and
 * metatags are already embedded in the page document, so no eager loading is needed.
 */
class PageRepository extends BaseRepository implements PageRepositoryInterface
{
    use ManagesPageState;

    protected RevisionRepositoryInterface $revisions;

    /**
     * Create a new MongoDB page repository instance.
     *
     * @param Page $model The MongoDB Page model instance
     * @param RevisionRepository|null $revisions Defaults to the MongoDB revision repository
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
     * Find a page by its slug with its template, blocks, widget instances and metatags.
     *
     * Blocks and metatags are embedded; the template is read from its own collection.
     *
     * @param string $slug The page slug
     * @return array|null The page with relations as array or null if not found
     */
    public function findBySlugWithRelations(string $slug): ?array
    {
        $page = $this->model->bySlug($slug)->first();
        if (!$page) {
            return null;
        }

        $data = $this->withPresentedBlocks($page->toArray());
        $data['metatags'] ??= [];
        $data['template'] = $page->template_id ? Template::find($page->template_id)?->toArray() : null;
        return $data;
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
        return $page ? $this->withPresentedBlocks($page->toArray()) : null;
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

    /**
     * Page document with blocks shaped like the SQL output; metatags are embedded.
     *
     * @param string|int $id
     * @return array
     */
    protected function snapshot(string|int $id): array
    {
        return $this->withBlocks($id);
    }

    /**
     * Run writes directly: standalone MongoDB servers have no transactions,
     * so update() validates a transition before writing anything.
     *
     * @param callable $callback
     * @return void
     */
    protected function atomically(callable $callback): void
    {
        $callback();
    }

    /**
     * Replace raw embedded blocks with blocks shaped like the SQL output.
     *
     * @param array $data Page as array
     * @return array
     */
    protected function withPresentedBlocks(array $data): array
    {
        $data['blocks'] = EmbeddedBlocks::presentAll((string) ($data['id'] ?? $data['_id']), $data['blocks'] ?? []);
        return $data;
    }
}
