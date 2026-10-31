<?php
namespace Andmarruda\Lpb\Contracts;

use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\ValueObjects\SEO;

/**
 * Pages. Status and published_revision_id change only through the
 * publishing service (PublishingServiceInterface); update() rejects status.
 * Every change to content that is part of a snapshot increments
 * tree_version.
 */
interface PageRepositoryInterface extends RepositoryInterface
{
    /**
     * revisionable_type of page revisions.
     */
    public const REVISION_TYPE = 'page';

    public function findBySlug(string $slug): ?array;

    /**
     * A page by slug with template (or null), blocks with their widget
     * instances, metatags and SEO: everything needed to render it.
     *
     * @param string $slug
     * @return array|null
     */
    public function findBySlugWithRelations(string $slug): ?array;

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
     * Set the status and the served revision; used by the publishing service.
     *
     * @param string|int $id
     * @param PublishState $status
     * @param string|null $publishedRevisionId
     * @return array|null The updated page or null if the page was not found
     */
    public function setPublication(string|int $id, PublishState $status, ?string $publishedRevisionId): ?array;

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
