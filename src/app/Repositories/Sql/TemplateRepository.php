<?php

namespace Andmarruda\Lpb\Repositories\Sql;

use Andmarruda\Lpb\Models\Sql\Page;
use Andmarruda\Lpb\Models\Sql\Template;
use Andmarruda\Lpb\Repositories\BaseRepository;
use Andmarruda\Lpb\Contracts\TemplateRepositoryInterface;
use Andmarruda\Lpb\Events\TemplateChanged;
use Andmarruda\Lpb\ValueObjects\TemplateStatus;

/**
 * SQL implementation of the Template repository.
 *
 * Pages reference templates through the template_id foreign key, which is
 * set to null by the database when a template is deleted.
 */
class TemplateRepository extends BaseRepository implements TemplateRepositoryInterface
{
    /**
     * Create a new SQL template repository instance.
     *
     * @param Template $model The SQL Template model instance
     * @param Page $pages The SQL Page model instance
     */
    public function __construct(Template $model, protected Page $pages)
    {
        parent::__construct($model);
    }

    /**
     * Delete a template; its pages lose it (foreign key) and get a new tree_version.
     *
     * @param string|int $id The template identifier
     * @return bool True if deleted successfully, false otherwise
     */
    public function delete(string|int $id): bool
    {
        $template = $this->model->find($id);
        if (!$template) {
            return false;
        }

        $deleted = $this->model->getConnection()->transaction(function () use ($template, $id) {
            $this->pages->newQuery()->where('template_id', $id)->increment('tree_version');
            return (bool) $template->delete();
        });

        if ($deleted) {
            TemplateChanged::dispatch((string) $id);
        }
        return $deleted;
    }

    /**
     * Update a template; pages using it may look different (design version bump).
     *
     * @param string|int $id The template identifier
     * @param array $data The data to update
     * @return array|null The updated template as array or null if not found
     */
    public function update(string|int $id, array $data): ?array
    {
        $template = parent::update($id, $data);
        if ($template !== null) {
            TemplateChanged::dispatch((string) $id);
        }
        return $template;
    }

    /**
     * Find a template by its slug.
     *
     * @param string $slug The template slug
     * @return array|null The template data as array or null if not found
     */
    public function findBySlug(string $slug): ?array
    {
        $template = $this->model->where('slug', $slug)->first();
        return $template ? $template->toArray() : null;
    }

    /**
     * Retrieve all active templates ordered by name.
     *
     * @return array Collection of active templates as array
     */
    public function getActive(): array
    {
        return $this->model->active()->orderBy('name')->get()->toArray();
    }

    /**
     * Retrieve the default template.
     *
     * @return array|null The default template as array or null if none is set
     */
    public function getDefault(): ?array
    {
        $template = $this->model->default()->first();
        return $template ? $template->toArray() : null;
    }

    /**
     * Make a template the only default one.
     *
     * @param string|int $id The template identifier
     * @return bool False if the template was not found
     */
    public function setDefault(string|int $id): bool
    {
        $template = $this->model->find($id);
        if (!$template) {
            return false;
        }

        $template->setDefault();
        return true;
    }

    /**
     * Assign an active template to a page.
     *
     * @param string|int $templateId The template identifier
     * @param string|int $pageId The page identifier
     * @return bool False if either is missing or the template is inactive
     */
    public function attachToPage(string|int $templateId, string|int $pageId): bool
    {
        $template = $this->model->find($templateId);
        $page = $this->pages->find($pageId);
        if (!$template || !$page || $template->status !== TemplateStatus::Active) {
            return false;
        }

        $page->template()->associate($template)->save();
        $page->newQuery()->whereKey($pageId)->increment('tree_version');
        return true;
    }

    /**
     * Remove the template from a page.
     *
     * @param string|int $pageId The page identifier
     * @return bool False if the page was not found
     */
    public function detachFromPage(string|int $pageId): bool
    {
        $page = $this->pages->find($pageId);
        if (!$page) {
            return false;
        }

        $page->template()->dissociate()->save();
        $page->newQuery()->whereKey($pageId)->increment('tree_version');
        return true;
    }
}
