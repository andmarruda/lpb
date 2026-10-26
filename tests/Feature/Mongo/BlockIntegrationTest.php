<?php

namespace Andmarruda\Lpb\Tests\Feature\Mongo;

use Tests\TestCase;
use Andmarruda\Lpb\Contracts\BlockRepositoryInterface;
use Andmarruda\Lpb\Exceptions\InvalidRelationException;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\Repositories\Mongo\BlockRepository;
use Andmarruda\Lpb\Repositories\Mongo\PageRepository;
use Andmarruda\Lpb\Models\Mongo\Page;

/**
 * MongoDB BlockRepository integration tests
 */
class BlockIntegrationTest extends TestCase
{
    protected BlockRepository $repository;
    protected PageRepository $pages;

    protected function setUp(): void
    {
        parent::setUp();

        Page::truncate();

        $this->repository = new BlockRepository(new Page());
        $this->pages = new PageRepository(new Page());
    }

    protected function tearDown(): void
    {
        Page::truncate();

        parent::tearDown();
    }

    protected function createPage(string $slug = 'home'): array
    {
        return $this->pages->create(['title' => 'Home', 'slug' => $slug, 'status' => 'draft']);
    }

    /**
     * Test the container binds the MongoDB block repository
     */
    public function test_container_binds_block_repository(): void
    {
        $this->assertInstanceOf(BlockRepository::class, app(BlockRepositoryInterface::class));
    }

    /**
     * Test create returns the block with defaults and find reads it back
     */
    public function test_create_and_find_block(): void
    {
        $page = $this->createPage();

        $block = $this->repository->create(['page_id' => $page['id'], 'type' => 'row']);

        $this->assertSame($page['id'], $block['page_id']);
        $this->assertNull($block['parent_id']);
        $this->assertSame('row', $block['type']);
        $this->assertSame(0, $block['order']);
        $this->assertSame([], $block['widget_instances']);
        $this->assertSame($block['id'], $this->repository->find($block['id'])['id']);
        $this->assertNull($this->repository->find('missing-block'));
    }

    /**
     * Test create rejects a missing page and a missing type
     */
    public function test_create_rejects_missing_page_or_type(): void
    {
        $page = $this->createPage();

        try {
            $this->repository->create(['page_id' => '000000000000000000000000', 'type' => 'row']);
            $this->fail('Block was created on a missing page');
        } catch (InvalidRelationException) {
        }

        $this->expectException(InvalidValueException::class);
        $this->repository->create(['page_id' => $page['id']]);
    }

    /**
     * Test blocks nest through parent_id within the same page
     */
    public function test_nested_blocks(): void
    {
        $page = $this->createPage();
        $row = $this->repository->create(['page_id' => $page['id'], 'type' => 'row']);

        $column = $this->repository->create(['page_id' => $page['id'], 'type' => 'column', 'parent_id' => $row['id']]);

        $this->assertSame($row['id'], $column['parent_id']);
    }

    /**
     * Test a parent from another page is rejected
     */
    public function test_rejects_parent_from_another_page(): void
    {
        $other = $this->repository->create(['page_id' => $this->createPage('other')['id'], 'type' => 'row']);
        $page = $this->createPage();

        $this->expectException(InvalidRelationException::class);
        $this->repository->create(['page_id' => $page['id'], 'type' => 'column', 'parent_id' => $other['id']]);
    }

    /**
     * Test update rejects nesting a block inside itself or its descendants
     */
    public function test_update_rejects_cycles(): void
    {
        $page = $this->createPage();
        $row = $this->repository->create(['page_id' => $page['id'], 'type' => 'row']);
        $column = $this->repository->create(['page_id' => $page['id'], 'type' => 'column', 'parent_id' => $row['id']]);

        try {
            $this->repository->update($row['id'], ['parent_id' => $row['id']]);
            $this->fail('Block was nested inside itself');
        } catch (InvalidRelationException) {
        }

        $this->expectException(InvalidRelationException::class);
        $this->repository->update($row['id'], ['parent_id' => $column['id']]);
    }

    /**
     * Test update changes type, order and parent and ignores page_id
     */
    public function test_update_block(): void
    {
        $page = $this->createPage();
        $row = $this->repository->create(['page_id' => $page['id'], 'type' => 'row']);
        $column = $this->repository->create(['page_id' => $page['id'], 'type' => 'column', 'parent_id' => $row['id']]);

        $updated = $this->repository->update($column['id'], [
            'type' => 'custom',
            'order' => 3,
            'parent_id' => null,
            'page_id' => $this->createPage('other')['id'],
        ]);

        $this->assertSame('custom', $updated['type']);
        $this->assertSame(3, $updated['order']);
        $this->assertNull($updated['parent_id']);
        $this->assertSame($page['id'], $updated['page_id']);
        $this->assertNull($this->repository->update('missing-block', ['type' => 'row']));
    }

    /**
     * Test invalid orders are rejected
     */
    public function test_rejects_invalid_order(): void
    {
        $page = $this->createPage();

        $this->expectException(InvalidValueException::class);
        $this->repository->create(['page_id' => $page['id'], 'type' => 'row', 'order' => -1]);
    }

    /**
     * Test getForPage orders by order, then insertion
     */
    public function test_get_for_page_orders_blocks(): void
    {
        $page = $this->createPage();
        $this->repository->create(['page_id' => $page['id'], 'type' => 'footer', 'order' => 2]);
        $this->repository->create(['page_id' => $page['id'], 'type' => 'hero', 'order' => 1]);
        $this->repository->create(['page_id' => $page['id'], 'type' => 'body', 'order' => 1]);
        $this->repository->create(['page_id' => $this->createPage('other')['id'], 'type' => 'elsewhere']);

        $this->assertSame(['hero', 'body', 'footer'], array_column($this->repository->getForPage($page['id']), 'type'));
        $this->assertSame([], $this->repository->getForPage('000000000000000000000000'));
        $this->assertCount(4, $this->repository->all());
    }

    /**
     * Test add, update and remove widget instances
     */
    public function test_widget_instance_lifecycle(): void
    {
        $page = $this->createPage();
        $block = $this->repository->create(['page_id' => $page['id'], 'type' => 'row']);
        $config = ['title' => 'Welcome', 'buttons' => [['label' => 'Go', 'url' => '/go']]];

        $instance = $this->repository->addWidgetInstance($block['id'], 'App\\Widgets\\Hero', $config, 1);

        $this->assertSame($block['id'], $instance['block_id']);
        $this->assertSame('App\\Widgets\\Hero', $instance['widget']);
        $this->assertSame($config, $instance['config']);
        $this->assertSame(1, $instance['order']);

        $updated = $this->repository->updateWidgetInstance($block['id'], $instance['id'], ['title' => 'Hi']);
        $this->assertSame(['title' => 'Hi'], $updated['config']);
        $this->assertSame(['title' => 'Hi'], $this->repository->find($block['id'])['widget_instances'][0]['config']);

        $this->assertTrue($this->repository->removeWidgetInstance($block['id'], $instance['id']));
        $this->assertSame([], $this->repository->find($block['id'])['widget_instances']);
        $this->assertFalse($this->repository->removeWidgetInstance($block['id'], $instance['id']));
        $this->assertNull($this->repository->updateWidgetInstance($block['id'], $instance['id'], []));
    }

    /**
     * Test widget instances are ordered inside their block
     */
    public function test_widget_instances_are_ordered(): void
    {
        $page = $this->createPage();
        $block = $this->repository->create(['page_id' => $page['id'], 'type' => 'row']);
        $this->repository->addWidgetInstance($block['id'], 'footer', [], 2);
        $this->repository->addWidgetInstance($block['id'], 'hero', [], 0);

        $this->assertSame(['hero', 'footer'], array_column($this->repository->find($block['id'])['widget_instances'], 'widget'));
    }

    /**
     * Test widget instances are scoped to their block
     */
    public function test_widget_instance_requires_matching_block(): void
    {
        $page = $this->createPage();
        $first = $this->repository->create(['page_id' => $page['id'], 'type' => 'row']);
        $second = $this->repository->create(['page_id' => $page['id'], 'type' => 'row']);
        $instance = $this->repository->addWidgetInstance($first['id'], 'hero', []);

        $this->assertNull($this->repository->updateWidgetInstance($second['id'], $instance['id'], []));
        $this->assertFalse($this->repository->removeWidgetInstance($second['id'], $instance['id']));

        $this->expectException(InvalidRelationException::class);
        $this->repository->addWidgetInstance('missing-block', 'hero', []);
    }

    /**
     * Test reorder sets orders by position and rejects invalid lists atomically
     */
    public function test_reorder(): void
    {
        $page = $this->createPage();
        $a = $this->repository->create(['page_id' => $page['id'], 'type' => 'a']);
        $b = $this->repository->create(['page_id' => $page['id'], 'type' => 'b']);
        $c = $this->repository->create(['page_id' => $page['id'], 'type' => 'c']);
        $foreign = $this->repository->create(['page_id' => $this->createPage('other')['id'], 'type' => 'x']);

        $this->assertTrue($this->repository->reorder($page['id'], [$c['id'], $a['id'], $b['id']]));
        $this->assertSame(['c', 'a', 'b'], array_column($this->repository->getForPage($page['id']), 'type'));

        $this->assertFalse($this->repository->reorder($page['id'], [$a['id'], $a['id']]));
        $this->assertFalse($this->repository->reorder($page['id'], [$b['id'], $foreign['id']]));
        $this->assertFalse($this->repository->reorder('000000000000000000000000', []));
        $this->assertSame(['c', 'a', 'b'], array_column($this->repository->getForPage($page['id']), 'type'));
    }

    /**
     * Test deleting a block removes its widget instances and orphans its children to the root
     */
    public function test_delete_block(): void
    {
        $page = $this->createPage();
        $row = $this->repository->create(['page_id' => $page['id'], 'type' => 'row']);
        $column = $this->repository->create(['page_id' => $page['id'], 'type' => 'column', 'parent_id' => $row['id']]);
        $instance = $this->repository->addWidgetInstance($row['id'], 'hero', []);

        $this->assertTrue($this->repository->delete($row['id']));

        $this->assertNull($this->repository->find($row['id']));
        $this->assertNull($this->repository->find($column['id'])['parent_id']);
        $this->assertFalse($this->repository->removeWidgetInstance($row['id'], $instance['id']));
        $this->assertFalse($this->repository->delete($row['id']));
    }

    /**
     * Test the page repository returns blocks with widget instances
     */
    public function test_page_with_blocks(): void
    {
        $page = $this->createPage();
        $block = $this->repository->create(['page_id' => $page['id'], 'type' => 'row']);
        $this->repository->addWidgetInstance($block['id'], 'hero', ['title' => 'Welcome']);

        $result = $this->pages->withBlocks($page['id']);

        $this->assertSame([$block['id']], array_column($result['blocks'], 'id'));
        $this->assertSame(['title' => 'Welcome'], $result['blocks'][0]['widget_instances'][0]['config']);
    }

    /**
     * Test deleting a page removes its blocks
     */
    public function test_delete_page_removes_blocks(): void
    {
        $page = $this->createPage();
        $block = $this->repository->create(['page_id' => $page['id'], 'type' => 'row']);

        $this->pages->delete($page['id']);

        $this->assertNull($this->repository->find($block['id']));
    }
}
