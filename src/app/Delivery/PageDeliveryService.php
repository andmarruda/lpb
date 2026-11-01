<?php
namespace Andmarruda\Lpb\Delivery;

use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Andmarruda\Lpb\Contracts\RevisionRepositoryInterface;
use Andmarruda\Lpb\Tree\PageTree;
use Andmarruda\Lpb\ValueObjects\CacheKey;
use Andmarruda\Lpb\ValueObjects\PublishState;

/**
 * Builds the payloads frontends receive (ADR 09, ADR 12).
 *
 * Published payloads go through two cache entries: a short pointer
 * lpb:pointer:{site}:{locale}:{slug} to the served revision, kept up to date
 * by the publishing events, and an immutable content entry
 * lpb:content:{revisionId}:{designVersion}. Nothing else is invalidated.
 */
class PageDeliveryService
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private PageTreeRepositoryInterface $trees,
        private RevisionRepositoryInterface $revisions,
        private DesignVersion $design,
        private DeliveryCache $cache,
        private ReferenceResolver $references,
    ) {
    }

    /**
     * The published payload of a page, or null when it is not served.
     *
     * @param string $slug
     * @param string $locale
     * @param string $site
     * @return array|null
     */
    public function published(string $slug, string $locale = CacheKey::DEFAULT, string $site = CacheKey::DEFAULT): ?array
    {
        $pointer = $this->pointer($slug, $locale, $site);
        if ($pointer === null) {
            return null;
        }

        $designVersion = $this->design->current();
        return $this->cache->remember(
            CacheKey::content($pointer['revision_id'], $designVersion)->toString(),
            $this->cache->contentTtl(),
            fn () => $this->build($pointer, $designVersion),
        );
    }

    /**
     * The working tree of a page as a payload, never cached.
     *
     * @param string $pageId
     * @return array|null Null if the page does not exist
     */
    public function preview(string $pageId): ?array
    {
        $tree = $this->trees->getWorkingTree($pageId);
        return $tree === null ? null : $this->present($tree, null, null);
    }

    /**
     * Point a slug at the revision now served.
     */
    public function rememberPointer(string $slug, string $pageId, string $revisionId, string $locale = CacheKey::DEFAULT, string $site = CacheKey::DEFAULT): void
    {
        $this->cache->put(CacheKey::pointer($slug, $locale, $site)->toString(), ['page_id' => $pageId, 'revision_id' => $revisionId], $this->cache->pointerTtl());
    }

    public function forgetPointer(string $slug, string $locale = CacheKey::DEFAULT, string $site = CacheKey::DEFAULT): void
    {
        $this->cache->forget(CacheKey::pointer($slug, $locale, $site)->toString());
    }

    /**
     * @return array{page_id: string, revision_id: string}|null
     */
    private function pointer(string $slug, string $locale, string $site): ?array
    {
        $key = CacheKey::pointer($slug, $locale, $site)->toString();
        $pointer = $this->cache->get($key);
        if (is_array($pointer)) {
            return $pointer;
        }

        $page = $this->pages->findBySlug($slug);
        if (!$page || $page['status'] !== PublishState::Published->value || empty($page['published_revision_id'])) {
            return null;
        }

        $pointer = ['page_id' => (string) $page['id'], 'revision_id' => (string) $page['published_revision_id']];
        $this->cache->put($key, $pointer, $this->cache->pointerTtl());
        return $pointer;
    }

    private function build(array $pointer, int $designVersion): ?array
    {
        $revision = $this->revisions->findFor(PageRepositoryInterface::REVISION_TYPE, $pointer['page_id'], $pointer['revision_id']);
        return $revision === null ? null : $this->present($revision['payload'], (string) $revision['id'], $designVersion);
    }

    /**
     * Canonical payload: configs upgraded, references resolved, editor-only
     * fields removed.
     */
    private function present(array $tree, ?string $revisionId, ?int $designVersion): array
    {
        // Widgets no longer registered (e.g. the raw html widget after it was
        // turned off) are not delivered, even from older revisions
        $tree['blocks'] = $this->references->resolve(PageTree::upgrade(PageTree::onlyRegistered($tree['blocks'] ?? [])));
        $tree['revision_id'] = $revisionId;
        $tree['design_version'] = $designVersion;
        unset($tree['tree_version']);
        return $tree;
    }
}
