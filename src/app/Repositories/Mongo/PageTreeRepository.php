<?php

namespace Andmarruda\Lpb\Repositories\Mongo;

use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Andmarruda\Lpb\Exceptions\RecordNotFoundException;
use Andmarruda\Lpb\Exceptions\TreeValidationException;
use Andmarruda\Lpb\Exceptions\TreeVersionConflictException;
use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Tree\FlatTree;
use Andmarruda\Lpb\Tree\PageTree;
use Andmarruda\Lpb\Validation\TreeValidator;

/**
 * MongoDB implementation of the working tree.
 *
 * The tree lives in the page document, so saveTree is a single update
 * filtered by the page id and the expected tree_version: it sets the nested
 * blocks and block_ids and increments tree_version, and matching no
 * document means another save won. No multi-document transaction is needed.
 */
class PageTreeRepository implements PageTreeRepositoryInterface
{
    public function __construct(protected Page $pages)
    {
    }

    public function getWorkingTree(string $pageId): ?array
    {
        $page = $this->pages->newQuery()->find($pageId);
        if (!$page) {
            return null;
        }

        $data = $page->toArray();
        $template = $page->template_id ? ['id' => $page->template_id, 'slug' => $page->template_slug] : null;
        $blocks = FlatTree::nest(FlatTree::flatten($page->blocks ?? []));

        return PageTree::make($data, $page->metatags ?? [], $template, PageTree::upgrade($blocks));
    }

    public function saveTree(string $pageId, array $tree, int $expectedVersion): array
    {
        $blocks = FlatTree::nest(FlatTree::flatten(TreeValidator::make()->validate($tree)), withOrder: true);
        $blockIds = FlatTree::blockIds($blocks);

        $foreign = $blockIds === [] ? null : $this->pages->newQuery()->whereIn('block_ids', $blockIds)->where('id', '!=', $pageId)->first();
        if ($foreign) {
            $id = current(array_intersect($blockIds, $foreign->block_ids ?? []));
            throw new TreeValidationException([['path' => '/blocks', 'message' => "Node id {$id} belongs to another page."]]);
        }

        $updated = $this->pages->newQuery()
            ->whereKey($pageId)
            ->where('tree_version', $expectedVersion)
            ->update(['blocks' => $blocks, 'block_ids' => $blockIds, '$inc' => ['tree_version' => 1]]);

        if ($updated === 0) {
            $page = $this->pages->newQuery()->find($pageId) ?? throw new RecordNotFoundException("Page '{$pageId}' not found.");
            throw new TreeVersionConflictException((int) $page->tree_version, $expectedVersion);
        }

        return $this->getWorkingTree($pageId);
    }

    public function upgradeStoredContent(string $pageId): int
    {
        $page = $this->pages->newQuery()->find($pageId);
        $stored = $page?->blocks ?? [];
        $count = PageTree::outdatedWidgets($stored);
        if ($count === 0) {
            return 0;
        }

        // Upgrade in place, keeping orders, and only if nobody saved meanwhile
        $flat = FlatTree::flatten($stored);
        $upgraded = PageTree::upgrade(FlatTree::nest($flat, withOrder: true));
        $this->pages->newQuery()
            ->whereKey($pageId)
            ->where('tree_version', (int) $page->tree_version)
            ->update(['blocks' => $upgraded]);

        return $count;
    }
}
