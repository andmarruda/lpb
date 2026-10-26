<?php

namespace Andmarruda\Lpb\Repositories\Concerns;

use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\RevisionRepositoryInterface;
use Andmarruda\Lpb\Exceptions\InvalidStateTransitionException;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\ValueObjects\SEO;
use Illuminate\Database\Eloquent\Model;

/**
 * Publish state transitions with revisions, and SEO, for page repositories.
 *
 * Every status change goes through PublishState::transitionTo() and
 * records a revision of the page after the change. Expects $model and
 * $revisions properties.
 */
trait ManagesPageState
{
    /**
     * Move a page to another publish state and record a revision.
     *
     * @param string|int $id The page identifier
     * @param PublishState $to The target state
     * @param string|null $createdBy Opaque user identifier stored on the revision
     * @return array|null The updated page as array or null if not found
     * @throws InvalidStateTransitionException
     */
    public function transition(string|int $id, PublishState $to, ?string $createdBy = null): ?array
    {
        $page = $this->model->find($id);
        if (!$page) {
            return null;
        }

        $next = $page->status->transitionTo($to);
        $this->atomically(fn () => $this->applyTransition($page, $next, $createdBy));

        return $this->find($id);
    }

    /**
     * Update a page; a status change is applied as a guarded transition.
     *
     * @param string|int $id The page identifier
     * @param array $data The data to update
     * @return array|null The updated page as array or null if not found
     * @throws InvalidStateTransitionException
     */
    public function update(string|int $id, array $data): ?array
    {
        if (!array_key_exists('status', $data)) {
            return parent::update($id, $data);
        }

        $status = $data['status'] instanceof PublishState ? $data['status'] : PublishState::from($data['status']);
        unset($data['status']);

        $page = $this->model->find($id);
        if (!$page) {
            return null;
        }

        // Validate before writing anything so a rejected transition changes nothing
        $changesStatus = $status !== $page->status;
        if ($changesStatus) {
            $page->status->transitionTo($status);
        }

        $this->atomically(function () use ($page, $data, $status, $changesStatus) {
            if ($data !== []) {
                $page->update($data);
            }
            if ($changesStatus) {
                $this->applyTransition($page, $status, null);
            }
        });

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
     * Save the new state and record a revision of the page.
     *
     * @param Model $page
     * @param PublishState $state
     * @param string|null $createdBy
     * @return void
     */
    protected function applyTransition(Model $page, PublishState $state, ?string $createdBy): void
    {
        $page->status = $state;
        $page->save();

        $this->revisions->record(PageRepositoryInterface::REVISION_TYPE, $page->getKey(), $this->snapshot($page->getKey()), $state, $createdBy);
    }

    /**
     * Full page state stored in a revision.
     *
     * @param string|int $id
     * @return array
     */
    abstract protected function snapshot(string|int $id): array;

    /**
     * Run writes atomically where the driver allows it.
     *
     * @param callable $callback
     * @return void
     */
    abstract protected function atomically(callable $callback): void;
}
