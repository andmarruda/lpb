<?php
namespace Andmarruda\Lpb\Contracts;

use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\ValueObjects\SEO;

/**
 * Pages. A status change through update() or transition() must be a legal
 * PublishState transition and records a page revision.
 */
interface PageRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug): ?array;
    public function getPublished(): array;
    public function withBlocks(string|int $id): ?array;
    public function withMetatags(string|int $id): ?array;
    public function getByStatus(PublishState|string $status): array;

    /**
     * Set or update a metatag of a page.
     *
     * @param string|int $pageId
     * @param string $name
     * @param string $content
     * @return array|null The updated page or null if the page was not found
     */
    public function setMetatag(string|int $pageId, string $name, string $content): ?array;

    /**
     * Get a metatag content by name.
     *
     * @param string|int $pageId
     * @param string $name
     * @return string|null
     */
    public function getMetatag(string|int $pageId, string $name): ?string;

    /**
     * Move a page to another publish state and record a revision.
     *
     * @param string|int $id
     * @param PublishState $to
     * @param string|null $createdBy Opaque user identifier stored on the revision
     * @return array|null The updated page or null if the page was not found
     * @throws \Andmarruda\Lpb\Exceptions\InvalidStateTransitionException
     */
    public function transition(string|int $id, PublishState $to, ?string $createdBy = null): ?array;

    /**
     * Replace the SEO of a page; an empty SEO clears it.
     *
     * @param string|int $id
     * @param SEO $seo
     * @return array|null The updated page or null if the page was not found
     */
    public function setSeo(string|int $id, SEO $seo): ?array;

    public function getSeo(string|int $id): ?SEO;
}
