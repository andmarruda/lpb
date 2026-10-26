<?php

namespace Andmarruda\Lpb\Repositories\Mongo;

use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Models\Mongo\Template;
use Andmarruda\Lpb\Repositories\BaseRepository;
use Andmarruda\Lpb\Contracts\TemplateRepositoryInterface;
use Andmarruda\Lpb\ValueObjects\TemplateStatus;

/**
 * MongoDB implementation of the Template repository.
 *
 * Page documents store template_id and a denormalised template_slug, which
 * this repository keeps in sync when a template's slug changes or the
 * template is deleted.
 */
class TemplateRepository extends BaseRepository implements TemplateRepositoryInterface
{
    /**
     * Create a new MongoDB template repository instance.
     *
     * @param Template $model The MongoDB Template model instance
     * @param Page $pages The MongoDB Page model instance
     */
    public function __construct(Template $model, protected Page $pages)
    {
        parent::__construct($model);
    }

    /**
     * Update a template and refresh template_slug on its pages.
     *
     * @param string|int $id The template identifier
     * @param array $data The data to update
     * @return array|null The updated template as array or null if not found
     */
    public function update(string|int $id, array $data): ?array
    {
        $template = parent::update($id, $data);
        if ($template && array_key_exists('slug', $data)) {
            $this->pages->where('template_id', (string) $id)->update(['template_slug' => $template['slug']]);
        }
        return $template;
    }

    /**
     * Delete a template and detach it from its pages.
     *
     * @param string|int $id The template identifier
     * @return bool True if deleted successfully, false otherwise
     */
    public function delete(string|int $id): bool
    {
        $deleted = parent::delete($id);
        if ($deleted) {
            $this->pages->where('template_id', (string) $id)->update(['template_id' => null, 'template_slug' => null]);
        }
        return $deleted;
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
     * Assign an active template to a page, copying its slug to the page.
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

        $page->template_id = (string) $template->getKey();
        $page->template_slug = $template->slug;
        $page->save();
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

        $page->template_id = null;
        $page->template_slug = null;
        $page->save();
        return true;
    }
}
