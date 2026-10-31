<?php

namespace Andmarruda\Lpb\Repositories\Concerns;

use Andmarruda\Lpb\Events\PageDeleted;
use Andmarruda\Lpb\Events\PageSlugChanged;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\ValueObjects\SEO;
use Illuminate\Container\Container;

/**
 * Page fields, SEO and publication state for page repositories.
 *
 * Status changes only through setPublication(), which the publishing
 * service calls; changes to snapshot content increment tree_version.
 * Expects a $model property.
 */
trait ManagesPageState
{
    /**
     * Create a page; pages start as drafts.
     *
     * @param array $data The page data
     * @return array The created page as array
     * @throws InvalidValueException When the data sets a status other than draft
     */
    public function create(array $data): array
    {
        $status = $data['status'] ?? PublishState::Draft;
        if (($status instanceof PublishState ? $status : PublishState::tryFrom((string) $status)) !== PublishState::Draft) {
            throw new InvalidValueException('Pages are created as drafts; publish them through the publishing service.');
        }

        return parent::create($data);
    }

    /**
     * Update a page's fields; status is rejected.
     *
     * @param string|int $id The page identifier
     * @param array $data The data to update
     * @return array|null The updated page as array or null if not found
     * @throws InvalidValueException When the data changes the status
     */
    public function update(string|int $id, array $data): ?array
    {
        if (array_key_exists('status', $data)) {
            throw new InvalidValueException('Page status changes go through the publishing service (publish, unpublish, archive, rollback).');
        }

        $page = $this->model->find($id);
        if (!$page) {
            return null;
        }

        $oldSlug = $page->slug;
        $page->update($data);
        $this->bumpTreeVersion($id);

        if ($page->slug !== $oldSlug) {
            $this->dispatchEvent(new PageSlugChanged((string) $page->getKey(), $oldSlug, $page->slug));
        }

        return $page->fresh()->toArray();
    }

    /**
     * Delete a page.
     *
     * @param string|int $id The page identifier
     * @return bool True if deleted successfully, false otherwise
     */
    public function delete(string|int $id): bool
    {
        $page = $this->model->find($id);
        if (!$page || !$page->delete()) {
            return false;
        }

        $this->dispatchEvent(new PageDeleted((string) $page->getKey(), (string) $page->slug));
        return true;
    }

    /**
     * Set the status and the served revision; used by the publishing service.
     *
     * @param string|int $id The page identifier
     * @param PublishState $status
     * @param string|null $publishedRevisionId
     * @return array|null The updated page as array or null if not found
     */
    public function setPublication(string|int $id, PublishState $status, ?string $publishedRevisionId): ?array
    {
        $page = $this->model->find($id);
        if (!$page) {
            return null;
        }

        $page->forceFill(['status' => $status, 'published_revision_id' => $publishedRevisionId])->save();
        return $this->find($id);
    }

    /**
     * Replace the SEO of a page; an empty SEO clears it.
     *
     * @param string|int $id The page identifier
     * @param SEO $seo The SEO value object
     * @return array|null The updated page as array or null if not found
     */
    public function setSeo(string|int $id, SEO $seo): ?array
    {
        $page = $this->model->find($id);
        if (!$page) {
            return null;
        }

        $page->seo = $seo;
        $page->save();
        $this->bumpTreeVersion($id);
        return $this->find($id);
    }

    /**
     * Get the SEO of a page.
     *
     * @param string|int $id The page identifier
     * @return SEO|null Null if the page has no SEO or does not exist
     */
    public function getSeo(string|int $id): ?SEO
    {
        return $this->model->find($id)?->seo;
    }

    /**
     * Dispatch an event when the application has an event dispatcher.
     *
     * @param object $event
     * @return void
     */
    protected function dispatchEvent(object $event): void
    {
        $container = Container::getInstance();
        if ($container->bound('events')) {
            $container->make('events')->dispatch($event);
        }
    }

    /**
     * Increment a page's tree_version after a change to content that is
     * part of its snapshot (blocks, widgets, SEO, metatags, template, fields).
     *
     * @param string|int $id
     * @return void
     */
    protected function bumpTreeVersion(string|int $id): void
    {
        $this->model->newQuery()->whereKey($id)->increment('tree_version');
    }
}
