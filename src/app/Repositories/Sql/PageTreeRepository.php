<?php

namespace Andmarruda\Lpb\Repositories\Sql;

use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Andmarruda\Lpb\Exceptions\InvalidRelationException;
use Andmarruda\Lpb\Exceptions\TreeValidationException;
use Andmarruda\Lpb\Exceptions\TreeVersionConflictException;
use Andmarruda\Lpb\Models\Sql\Block;
use Andmarruda\Lpb\Models\Sql\Page;
use Andmarruda\Lpb\Models\Sql\WidgetInstance;
use Andmarruda\Lpb\Tree\FlatTree;
use Andmarruda\Lpb\Tree\PageTree;
use Andmarruda\Lpb\Validation\TreeValidator;
use Andmarruda\Lpb\Widgets\WidgetRegistry;
use Illuminate\Support\Carbon;

/**
 * SQL implementation of the working tree.
 *
 * saveTree validates the whole tree, then in one transaction locks the page
 * row, checks tree_version, upserts the nodes (parents before children),
 * deletes the nodes that are gone and increments tree_version.
 */
class PageTreeRepository implements PageTreeRepositoryInterface
{
    private const CHUNK = 100;

    public function __construct(protected Page $pages, protected Block $blocks, protected WidgetInstance $widgetInstances)
    {
    }

    public function getWorkingTree(string $pageId): ?array
    {
        $page = $this->pages->newQuery()->with(['template', 'metatags', 'blocks.widgetInstances'])->find($pageId);
        if (!$page) {
            return null;
        }

        $data = $page->toArray();
        return PageTree::make($data, $data['metatags'], $data['template'], PageTree::upgrade(FlatTree::nest($data['blocks'])));
    }

    public function saveTree(string $pageId, array $tree, int $expectedVersion): array
    {
        $flat = FlatTree::flatten(TreeValidator::make()->validate($tree));
        $blockIds = array_column($flat, 'id');
        $widgetIds = array_merge([], ...array_map(fn (array $block) => array_column($block['widget_instances'], 'id'), $flat));

        $this->pages->getConnection()->transaction(function () use ($pageId, $expectedVersion, $flat, $blockIds, $widgetIds) {
            $page = $this->pages->newQuery()->whereKey($pageId)->lockForUpdate()->first()
                ?? throw new InvalidRelationException("Page '{$pageId}' not found.");
            if ($page->tree_version !== $expectedVersion) {
                throw new TreeVersionConflictException($page->tree_version, $expectedVersion);
            }

            $this->assertOwnedNodes($pageId, $blockIds, $widgetIds);

            $now = Carbon::now();
            $blockRows = [];
            $widgetRows = [];
            foreach ($flat as $block) {
                $blockRows[] = [
                    'id' => $block['id'],
                    'page_id' => $pageId,
                    'parent_id' => $block['parent_id'],
                    'type' => $block['type'],
                    'order' => $block['order'],
                    'styles' => json_encode($block['styles'], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                foreach ($block['widget_instances'] as $instance) {
                    $widgetRows[] = [
                        'id' => $instance['id'],
                        'block_id' => $block['id'],
                        'widget' => $instance['widget'],
                        'schema_version' => $instance['schema_version'],
                        'config' => json_encode((object) $instance['config'], JSON_THROW_ON_ERROR),
                        'styles' => json_encode($instance['styles'], JSON_THROW_ON_ERROR),
                        'order' => $instance['order'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // Parents come before their children, so the parent foreign key holds row by row
            $blocks = $this->blocks->getConnection()->table($this->blocks->getTable());
            foreach (array_chunk($blockRows, self::CHUNK) as $chunk) {
                $blocks->clone()->upsert($chunk, ['id'], ['parent_id', 'type', 'order', 'styles', 'updated_at']);
            }
            $this->blocks->newQuery()->where('page_id', $pageId)->whereNotIn('id', $blockIds)->delete();

            $widgets = $this->widgetInstances->getConnection()->table($this->widgetInstances->getTable());
            foreach (array_chunk($widgetRows, self::CHUNK) as $chunk) {
                $widgets->clone()->upsert($chunk, ['id'], ['block_id', 'widget', 'schema_version', 'config', 'styles', 'order', 'updated_at']);
            }
            $this->widgetInstances->newQuery()
                ->whereIn('block_id', $this->blocks->newQuery()->select('id')->where('page_id', $pageId))
                ->whereNotIn('id', $widgetIds)
                ->delete();

            $this->pages->newQuery()->whereKey($pageId)->update(['tree_version' => $expectedVersion + 1, 'updated_at' => $now]);
        });

        return $this->getWorkingTree($pageId);
    }

    public function upgradeStoredContent(string $pageId): int
    {
        $widgets = app(WidgetRegistry::class);
        $count = 0;

        $instances = $this->widgetInstances->newQuery()
            ->whereIn('block_id', $this->blocks->newQuery()->select('id')->where('page_id', $pageId))
            ->get();
        foreach ($instances as $instance) {
            [$config, $version] = $widgets->upgrade($instance->widget, $instance->getConfig(), $instance->schema_version);
            if ($version !== $instance->schema_version) {
                $instance->update(['config' => $config, 'schema_version' => $version]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Reject ids that already belong to nodes of another page.
     *
     * @param string $pageId
     * @param string[] $blockIds
     * @param string[] $widgetIds
     * @return void
     */
    private function assertOwnedNodes(string $pageId, array $blockIds, array $widgetIds): void
    {
        $foreignBlock = $this->blocks->newQuery()->whereIn('id', $blockIds)->where('page_id', '!=', $pageId)->value('id');
        $foreignWidget = $this->widgetInstances->newQuery()
            ->whereIn('id', $widgetIds)
            ->whereNotIn('block_id', $this->blocks->newQuery()->select('id')->where('page_id', $pageId))
            ->value('id');

        $foreign = $foreignBlock ?? $foreignWidget;
        if ($foreign !== null) {
            throw new TreeValidationException([['path' => '/blocks', 'message' => "Node id {$foreign} belongs to another page."]]);
        }
    }
}
