<?php
namespace Andmarruda\Lpb\Tree;

use Andmarruda\Lpb\Widgets\WidgetRegistry;

/**
 * Builds the canonical page tree (ADR 12), identical for both drivers.
 *
 *     {format_version, page: {id, slug, title, seo, metatags},
 *      template: {id, slug} | null, tree_version, revision_id,
 *      design_version, blocks}
 *
 * revision_id and design_version are null here; delivery payloads set them.
 */
final class PageTree
{
    public const FORMAT_VERSION = 1;

    /**
     * @param array $page Page attributes (id, slug, title, seo, tree_version)
     * @param array $metatags Metatag rows (name or property, content)
     * @param array|null $template Template attributes (id, slug) or null
     * @param array $blocks Nested blocks
     * @return array
     */
    public static function make(array $page, array $metatags, ?array $template, array $blocks): array
    {
        return [
            'format_version' => self::FORMAT_VERSION,
            'page' => [
                'id' => (string) $page['id'],
                'slug' => $page['slug'],
                'title' => $page['title'],
                'seo' => $page['seo'] ?? null,
                'metatags' => self::metatags($metatags),
            ],
            'template' => $template ? ['id' => (string) $template['id'], 'slug' => $template['slug']] : null,
            'tree_version' => (int) ($page['tree_version'] ?? 0),
            'revision_id' => null,
            'design_version' => null,
            'blocks' => $blocks,
        ];
    }

    /**
     * Upgrade every widget config to its definition's current version.
     *
     * @param array $blocks Nested blocks
     * @return array
     */
    public static function upgrade(array $blocks): array
    {
        $widgets = app(WidgetRegistry::class);

        return array_map(function (array $block) use ($widgets) {
            $block['widgets'] = array_map(function (array $instance) use ($widgets) {
                [$instance['config'], $instance['schema_version']] = $widgets->upgrade($instance['widget'], $instance['config'] ?? [], $instance['schema_version'] ?? 1);
                return $instance;
            }, $block['widgets'] ?? []);
            $block['children'] = self::upgrade($block['children'] ?? []);
            return $block;
        }, $blocks);
    }

    /**
     * The blocks without widget instances whose widget is not registered.
     *
     * @param array $blocks Nested blocks
     * @return array
     */
    public static function onlyRegistered(array $blocks): array
    {
        $widgets = app(WidgetRegistry::class);

        return array_map(function (array $block) use ($widgets) {
            $block['widgets'] = array_values(array_filter($block['widgets'] ?? [], fn (array $instance) => $widgets->has((string) ($instance['widget'] ?? ''))));
            $block['children'] = self::onlyRegistered($block['children'] ?? []);
            return $block;
        }, $blocks);
    }

    /**
     * Number of widget instances whose config is older than its definition.
     *
     * @param array $blocks Nested blocks
     * @return int
     */
    public static function outdatedWidgets(array $blocks): int
    {
        $widgets = app(WidgetRegistry::class);
        $count = 0;
        foreach ($blocks as $block) {
            foreach ($block['widgets'] ?? [] as $instance) {
                $definition = $widgets->get($instance['widget']);
                if ($definition && ($instance['schema_version'] ?? 1) < $definition->version) {
                    $count++;
                }
            }
            $count += self::outdatedWidgets($block['children'] ?? []);
        }
        return $count;
    }

    /**
     * @param array $metatags
     * @return array<int, array{name?: string, property?: string, content: string}>
     */
    private static function metatags(array $metatags): array
    {
        return array_values(array_map(fn (array $tag) => array_filter([
            'name' => $tag['name'] ?? null,
            'property' => $tag['property'] ?? null,
            'content' => $tag['content'] ?? '',
        ], fn ($value, $key) => $key === 'content' || $value !== null, ARRAY_FILTER_USE_BOTH), $metatags));
    }
}
