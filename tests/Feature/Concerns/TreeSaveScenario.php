<?php

namespace Andmarruda\Lpb\Tests\Feature\Concerns;

use Andmarruda\Lpb\Contracts\BlockRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Andmarruda\Lpb\Contracts\TemplateRepositoryInterface;
use Andmarruda\Lpb\Exceptions\InvalidRelationException;
use Andmarruda\Lpb\Exceptions\TreeValidationException;
use Andmarruda\Lpb\Exceptions\TreeVersionConflictException;
use Andmarruda\Lpb\Support\Ulid;
use Andmarruda\Lpb\ValueObjects\SEO;

/**
 * Atomic tree save (ADR 11) shared by every driver. The using class
 * prepares the database and registers the fixture widgets.
 */
trait TreeSaveScenario
{
    use ComparesStoredArrays;

    private function treeBlocks(): array
    {
        return [
            [
                'id' => Ulid::generate(), 'type' => 'flex',
                'styles' => [['breakpoint' => null, 'state' => null, 'props' => ['flex-direction' => 'column']]],
                'widgets' => [
                    ['id' => Ulid::generate(), 'widget' => 'hero', 'schema_version' => 1, 'config' => ['title' => 'Welcome', 'image' => ['$media' => 7]], 'styles' => []],
                    ['id' => Ulid::generate(), 'widget' => 'text', 'schema_version' => 1, 'config' => ['body' => '<p>Hi</p>'], 'styles' => []],
                ],
                'children' => [
                    ['id' => Ulid::generate(), 'type' => 'grid', 'styles' => [], 'widgets' => [
                        ['id' => Ulid::generate(), 'widget' => 'footer', 'schema_version' => 1, 'config' => [], 'styles' => []],
                    ], 'children' => []],
                ],
            ],
            ['id' => Ulid::generate(), 'type' => 'grid', 'styles' => [], 'widgets' => [], 'children' => []],
        ];
    }

    /**
     * Test the working tree is the canonical tree with page, template and tree_version
     */
    public function test_working_tree_shape(): void
    {
        $pages = app(PageRepositoryInterface::class);
        $trees = app(PageTreeRepositoryInterface::class);
        $templates = app(TemplateRepositoryInterface::class);
        $page = $pages->create(['title' => 'Home', 'slug' => 'home']);
        $template = $templates->create(['name' => 'Landing', 'slug' => 'landing', 'view_path' => 'lpb::shell']);
        $templates->attachToPage($template['id'], $page['id']);
        $pages->setSeo($page['id'], new SEO(title: 'Home', canonical: 'https://example.com/'));
        $pages->setMetatag($page['id'], 'google-site-verification', 'abc');

        $tree = $trees->getWorkingTree($page['id']);

        $this->assertSame(1, $tree['format_version']);
        $this->assertSame(['id' => $page['id'], 'slug' => 'home', 'title' => 'Home'], array_intersect_key($tree['page'], array_flip(['id', 'slug', 'title'])));
        $this->assertSame('https://example.com/', $tree['page']['seo']['canonical']);
        $this->assertSame([['name' => 'google-site-verification', 'content' => 'abc']], $tree['page']['metatags']);
        $this->assertSame(['id' => $template['id'], 'slug' => 'landing'], $tree['template']);
        $this->assertSame($pages->find($page['id'])['tree_version'], $tree['tree_version']);
        $this->assertNull($tree['revision_id']);
        $this->assertNull($tree['design_version']);
        $this->assertSame([], $tree['blocks']);
        $this->assertNull($trees->getWorkingTree(Ulid::generate()));
    }

    /**
     * Test a saved tree reads back identically, nested and ordered
     */
    public function test_save_tree_round_trip(): void
    {
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Home', 'slug' => 'home']);
        $trees = app(PageTreeRepositoryInterface::class);
        $blocks = $this->treeBlocks();

        $saved = $trees->saveTree($page['id'], ['blocks' => $blocks], 0);

        $this->assertSame(1, $saved['tree_version']);
        $this->assertSameIgnoringKeyOrder($blocks, $saved['blocks']);
        $this->assertSameIgnoringKeyOrder($blocks, $trees->getWorkingTree($page['id'])['blocks']);

        // The node-level repository sees the same nodes
        $flat = app(BlockRepositoryInterface::class)->getForPage($page['id']);
        $this->assertEqualsCanonicalizing(
            [$blocks[0]['id'], $blocks[0]['children'][0]['id'], $blocks[1]['id']],
            array_column($flat, 'id')
        );
    }

    /**
     * Test ids are kept, removed nodes are deleted and nodes can move
     */
    public function test_save_tree_replaces_nodes(): void
    {
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Home', 'slug' => 'home']);
        $trees = app(PageTreeRepositoryInterface::class);
        $blocks = $this->treeBlocks();
        $trees->saveTree($page['id'], ['blocks' => $blocks], 0);

        // Move the text widget into the second block, drop the nested grid, reverse the roots
        $text = $blocks[0]['widgets'][1];
        $first = $blocks[0];
        $first['widgets'] = [$first['widgets'][0]];
        $first['children'] = [];
        $second = $blocks[1];
        $second['widgets'] = [$text];
        $next = [$second, $first];

        $saved = $trees->saveTree($page['id'], ['blocks' => $next], 1);

        $this->assertSame(2, $saved['tree_version']);
        $this->assertSameIgnoringKeyOrder($next, $saved['blocks']);
        $this->assertCount(2, app(BlockRepositoryInterface::class)->getForPage($page['id']));
        $this->assertSame([], $trees->saveTree($page['id'], ['blocks' => []], 2)['blocks']);
        $this->assertSame([], app(BlockRepositoryInterface::class)->getForPage($page['id']));
    }

    /**
     * Test two saves against the same version: one wins, the other conflicts and changes nothing
     */
    public function test_save_tree_conflict(): void
    {
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Home', 'slug' => 'home']);
        $trees = app(PageTreeRepositoryInterface::class);
        $first = $this->treeBlocks();

        $trees->saveTree($page['id'], ['blocks' => $first], 0);

        try {
            $trees->saveTree($page['id'], ['blocks' => $this->treeBlocks()], 0);
            $this->fail('A save against a stale version was accepted');
        } catch (TreeVersionConflictException $e) {
            $this->assertSame(1, $e->currentVersion());
        }

        $tree = $trees->getWorkingTree($page['id']);
        $this->assertSame(1, $tree['tree_version']);
        $this->assertSameIgnoringKeyOrder($first, $tree['blocks']);
    }

    /**
     * Test an invalid node anywhere rejects the whole save with its path
     */
    public function test_save_tree_is_all_or_nothing(): void
    {
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Home', 'slug' => 'home']);
        $trees = app(PageTreeRepositoryInterface::class);
        $trees->saveTree($page['id'], ['blocks' => $this->treeBlocks()], 0);
        $before = $trees->getWorkingTree($page['id']);

        $invalid = $this->treeBlocks();
        $invalid[0]['children'][0]['widgets'][0] = ['id' => Ulid::generate(), 'widget' => 'hero', 'config' => ['title' => str_repeat('x', 200)]];

        try {
            $trees->saveTree($page['id'], ['blocks' => $invalid], 1);
            $this->fail('A tree with an invalid widget was saved');
        } catch (TreeValidationException $e) {
            $this->assertSame('/blocks/0/children/0/widgets/0/config/title', $e->errors()[0]['path']);
        }

        $this->assertSame($before, $trees->getWorkingTree($page['id']));
    }

    /**
     * Test the shape, id and limit rules of a submitted tree
     */
    public function test_save_tree_validation(): void
    {
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Home', 'slug' => 'home']);
        $trees = app(PageTreeRepositoryInterface::class);
        $id = Ulid::generate();
        $node = fn (array $extra = []) => $extra + ['id' => Ulid::generate(), 'type' => 'flex', 'styles' => [], 'widgets' => [], 'children' => []];

        $cases = [
            'not a list' => [['blocks' => 'x'], '/blocks'],
            'missing id' => [['blocks' => [['type' => 'flex']]], '/blocks/0/id'],
            'not a ulid' => [['blocks' => [$node(['id' => 'block-1'])]], '/blocks/0/id'],
            'duplicate id' => [['blocks' => [$node(['id' => $id]), $node(['id' => $id])]], '/blocks/1/id'],
            'unknown type' => [['blocks' => [$node(['type' => 'row'])]], '/blocks/0/type'],
            'unknown key' => [['blocks' => [$node(['order' => 1])]], '/blocks/0/order'],
            'unknown widget' => [['blocks' => [$node(['widgets' => [['id' => Ulid::generate(), 'widget' => 'missing', 'config' => []]]])]], '/blocks/0/widgets/0/widget'],
            'bad styles' => [['blocks' => [$node(['styles' => [['state' => 'visited', 'props' => []]]])]], '/blocks/0/styles/0/state'],
            'newer schema' => [['blocks' => [$node(['widgets' => [['id' => Ulid::generate(), 'widget' => 'hero', 'schema_version' => 9, 'config' => []]]])]], '/blocks/0/widgets/0/schema_version'],
        ];
        foreach ($cases as $name => [$tree, $path]) {
            try {
                $trees->saveTree($page['id'], $tree, 0);
                $this->fail("Invalid tree ({$name}) was saved");
            } catch (TreeValidationException $e) {
                $this->assertSame($path, $e->errors()[0]['path'], $name);
            }
        }

        config(['laravel-page-builder.limits.max_depth' => 1]);
        try {
            $trees->saveTree($page['id'], ['blocks' => [$node(['children' => [$node()]])]], 0);
            $this->fail('A tree deeper than the limit was saved');
        } catch (TreeValidationException $e) {
            $this->assertSame('/blocks', $e->errors()[0]['path']);
        }

        $this->assertSame(0, $trees->getWorkingTree($page['id'])['tree_version']);
    }

    /**
     * Test node ids of another page are rejected and a missing page is reported
     */
    public function test_save_tree_rejects_foreign_nodes(): void
    {
        $pages = app(PageRepositoryInterface::class);
        $trees = app(PageTreeRepositoryInterface::class);
        $other = $pages->create(['title' => 'Other', 'slug' => 'other']);
        $page = $pages->create(['title' => 'Home', 'slug' => 'home']);
        $blocks = $this->treeBlocks();
        $trees->saveTree($other['id'], ['blocks' => $blocks], 0);

        try {
            $trees->saveTree($page['id'], ['blocks' => [$blocks[1]]], 0);
            $this->fail('A block of another page was taken over');
        } catch (TreeValidationException $e) {
            $this->assertStringContainsString($blocks[1]['id'], $e->getMessage());
        }

        $this->assertSameIgnoringKeyOrder($blocks, $trees->getWorkingTree($other['id'])['blocks']);

        $this->expectException(InvalidRelationException::class);
        $trees->saveTree(Ulid::generate(), ['blocks' => []], 0);
    }

    /**
     * Test old configs are upgraded on read and rewritten without a new tree_version
     */
    public function test_upgrade_on_read_and_stored_content(): void
    {
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Home', 'slug' => 'home']);
        $trees = app(PageTreeRepositoryInterface::class);
        $blocks = app(BlockRepositoryInterface::class);
        $block = $blocks->create(['page_id' => $page['id'], 'type' => 'flex']);
        $card = $blocks->addWidgetInstance($block['id'], 'card', ['title' => 'T', 'size' => 'sm']);
        $this->storeOutdatedCard($page['id'], $block['id'], $card['id']);
        $version = $trees->getWorkingTree($page['id'])['tree_version'];

        $widget = $trees->getWorkingTree($page['id'])['blocks'][0]['widgets'][0];
        $this->assertSame(3, $widget['schema_version']);
        $this->assertSame(['title' => 'Old', 'size' => 'md'], $widget['config']);

        $this->assertSame(1, $trees->upgradeStoredContent($page['id']));
        $this->assertSame(0, $trees->upgradeStoredContent($page['id']));
        $this->assertSame(3, $blocks->find($block['id'])['widget_instances'][0]['schema_version']);
        $this->assertSame($version, $trees->getWorkingTree($page['id'])['tree_version']);

        $this->storeOutdatedCard($page['id'], $block['id'], $card['id']);
        $this->artisan('lpb:upgrade-content')->expectsOutputToContain('1 widget instances upgraded')->assertSuccessful();
    }

    /**
     * Store the card widget instance as a version 1 config, around the repositories.
     */
    abstract protected function storeOutdatedCard(string $pageId, string $blockId, string $instanceId): void;
}
