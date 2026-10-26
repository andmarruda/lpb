<?php
namespace Andmarruda\Lpb\Contracts;

use Andmarruda\Lpb\ValueObjects\PublishState;

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
}
