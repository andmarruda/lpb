<?php
namespace Andmarruda\Lpb\Contracts;

/**
 * Widget and metatag mutations for a page, implemented by every driver.
 *
 * Widgets are addressed by an integer key: the widget id on SQL and the
 * position in the embedded widgets array on MongoDB.
 */
interface PageContentRepositoryInterface
{
    /**
     * Add a widget to a page.
     *
     * @param string|int $pageId
     * @param array $widget
     * @return array|null The updated page or null if the page was not found
     */
    public function addWidget(string|int $pageId, array $widget): ?array;

    /**
     * Update a widget of a page.
     *
     * @param string|int $pageId
     * @param int $widgetKey Widget id (SQL) or array index (MongoDB)
     * @param array $data
     * @return array|null The updated page or null if the page was not found
     */
    public function updateWidget(string|int $pageId, int $widgetKey, array $data): ?array;

    /**
     * Remove a widget from a page.
     *
     * @param string|int $pageId
     * @param int $widgetKey Widget id (SQL) or array index (MongoDB)
     * @return array|null The updated page or null if the page was not found
     */
    public function removeWidget(string|int $pageId, int $widgetKey): ?array;

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
