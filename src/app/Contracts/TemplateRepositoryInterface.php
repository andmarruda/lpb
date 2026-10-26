<?php
namespace Andmarruda\Lpb\Contracts;

interface TemplateRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug): ?array;
    public function getActive(): array;
    public function getDefault(): ?array;

    /**
     * Make a template the only default one.
     *
     * @param string|int $id
     * @return bool False if the template was not found
     */
    public function setDefault(string|int $id): bool;

    /**
     * Assign an active template to a page.
     *
     * @param string|int $templateId
     * @param string|int $pageId
     * @return bool False if either is missing or the template is inactive
     */
    public function attachToPage(string|int $templateId, string|int $pageId): bool;

    /**
     * Remove the template from a page.
     *
     * @param string|int $pageId
     * @return bool False if the page was not found
     */
    public function detachFromPage(string|int $pageId): bool;
}
