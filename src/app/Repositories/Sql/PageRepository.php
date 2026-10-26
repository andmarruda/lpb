<?php

namespace Andmarruda\Lpb\Repositories\Sql;

use Andmarruda\Lpb\Models\Sql\Page;
use Andmarruda\Lpb\Models\Sql\PageWidget;
use Andmarruda\Lpb\Repositories\BaseRepository;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageContentRepositoryInterface;
use Illuminate\Support\Arr;

/**
 * SQL implementation of the Page repository.
 *
 * This repository handles page data stored in SQL databases using Eloquent.
 * It extends BaseRepository for common CRUD operations and adds SQL-specific
 * methods for page management including eager loading of relationships.
 */
class PageRepository extends BaseRepository implements PageRepositoryInterface, PageContentRepositoryInterface
{
    /**
     * Create a new SQL page repository instance.
     *
     * @param Page $model The SQL Page model instance
     */
    public function __construct(Page $model)
    {
        parent::__construct($model);
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
     * Retrieve all published pages.
     *
     * @return array Collection of published pages as array
     */
    public function getPublished(): array
    {
        return $this->model->published()->get()->toArray();
    }

    /**
     * Find a page with its widgets eager loaded.
     *
     * @param string|int $id The page identifier
     * @return array|null The page with widgets as array or null if not found
     */
    public function withWidgets(string|int $id): ?array
    {
        $page = $this->model->with('widgets')->find($id);
        return $page ? $page->toArray() : null;
    }

    /**
     * Find a page with its metatags eager loaded.
     *
     * @param string|int $id The page identifier
     * @return array|null The page with metatags as array or null if not found
     */
    public function withMetatags(string|int $id): ?array
    {
        $page = $this->model->with('metatags')->find($id);
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
     * Add a widget to an existing page.
     *
     * Settings may be passed as `settings => [['key' => ..., 'value' => ...]]`.
     *
     * @param string|int $pageId The page identifier
     * @param array $widget The widget data to add
     * @return array|null The updated page as array or null if not found
     */
    public function addWidget(string|int $pageId, array $widget): ?array
    {
        $page = $this->model->find($pageId);
        if (!$page) {
            return null;
        }

        $page->getConnection()->transaction(function () use ($page, $widget) {
            $created = $page->widgets()->create(array_merge(
                ['position_x' => 0, 'position_y' => 0],
                Arr::except($widget, ['settings'])
            ));
            $this->replaceSettings($created, $widget['settings'] ?? []);
        });

        return $this->withContent($page);
    }

    /**
     * Update a widget in an existing page.
     *
     * When `settings` is present it replaces all existing settings of the widget.
     *
     * @param string|int $pageId The page identifier
     * @param int $widgetKey The widget id
     * @param array $data The widget data to update
     * @return array|null The updated page as array or null if not found
     */
    public function updateWidget(string|int $pageId, int $widgetKey, array $data): ?array
    {
        $page = $this->model->find($pageId);
        if (!$page) {
            return null;
        }

        $widget = $page->widgets()->find($widgetKey);
        if ($widget) {
            $page->getConnection()->transaction(function () use ($widget, $data) {
                $widget->update(Arr::except($data, ['settings']));
                if (array_key_exists('settings', $data)) {
                    $this->replaceSettings($widget, $data['settings']);
                }
            });
        }

        return $this->withContent($page);
    }

    /**
     * Remove a widget from an existing page.
     *
     * @param string|int $pageId The page identifier
     * @param int $widgetKey The widget id
     * @return array|null The updated page as array or null if not found
     */
    public function removeWidget(string|int $pageId, int $widgetKey): ?array
    {
        $page = $this->model->find($pageId);
        if (!$page) {
            return null;
        }

        $page->widgets()->find($widgetKey)?->delete();

        return $this->withContent($page);
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
        if (!$page) {
            return null;
        }

        $page->metatags()->updateOrCreate(['name' => $name], ['content' => $content]);

        return $this->withContent($page);
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
        return $this->model->find($pageId)?->metatags()->where('name', $name)->value('content');
    }

    /**
     * Replace all settings of a widget.
     *
     * @param PageWidget $widget
     * @param array $settings List of ['key' => ..., 'value' => ...]
     * @return void
     */
    protected function replaceSettings(PageWidget $widget, array $settings): void
    {
        $widget->settings()->delete();
        $widget->settings()->createMany($settings);
    }

    /**
     * Reload a page with widgets, widget settings and metatags.
     *
     * @param Page $page
     * @return array
     */
    protected function withContent(Page $page): array
    {
        return $page->fresh(['widgets.settings', 'metatags'])->toArray();
    }
}
