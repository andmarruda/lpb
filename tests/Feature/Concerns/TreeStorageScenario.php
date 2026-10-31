<?php

namespace Andmarruda\Lpb\Tests\Feature\Concerns;

use Andmarruda\Lpb\Contracts\BlockRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\TemplateRepositoryInterface;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\Exceptions\TreeValidationException;
use Andmarruda\Lpb\Support\Ulid;
use Andmarruda\Lpb\ValueObjects\SEO;

/**
 * Block storage rules shared by every driver: ULID ids, block types,
 * styles, tree limits and tree_version. The using class prepares the
 * database; repositories come from the container.
 */
trait TreeStorageScenario
{
    /**
     * Test a client-generated ULID is kept and normalised to uppercase
     */
    public function test_client_generated_block_id_is_kept(): void
    {
        $blocks = app(BlockRepositoryInterface::class);
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Ids', 'slug' => 'ids']);
        $id = strtolower(Ulid::generate());

        $block = $blocks->create(['id' => $id, 'page_id' => $page['id'], 'type' => 'flex']);

        $this->assertSame(strtoupper($id), $block['id']);
        $this->assertSame(strtoupper($id), $blocks->find(strtoupper($id))['id']);

        $this->expectException(InvalidValueException::class);
        $blocks->create(['id' => 'not-a-ulid', 'page_id' => $page['id'], 'type' => 'flex']);
    }

    /**
     * Test generated ids of blocks and widget instances are ULIDs
     */
    public function test_generated_ids_are_ulids(): void
    {
        $blocks = app(BlockRepositoryInterface::class);
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Ids', 'slug' => 'ids']);

        $block = $blocks->create(['page_id' => $page['id'], 'type' => 'grid']);
        $instance = $blocks->addWidgetInstance($block['id'], 'hero', []);

        $this->assertTrue(Ulid::isValid($block['id']));
        $this->assertTrue(Ulid::isValid($instance['id']));
        $this->assertSame(1, $instance['schema_version']);
    }

    /**
     * Test only the block types of format_version 1 are accepted
     */
    public function test_rejects_unknown_block_type(): void
    {
        $blocks = app(BlockRepositoryInterface::class);
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Types', 'slug' => 'types']);
        $block = $blocks->create(['page_id' => $page['id'], 'type' => 'grid']);

        try {
            $blocks->update($block['id'], ['type' => 'row']);
            $this->fail('A block type outside flex and grid was accepted');
        } catch (InvalidValueException) {
        }

        $this->assertSame('grid', $blocks->find($block['id'])['type']);
    }

    /**
     * Test styles are validated and stored on blocks and widget instances
     */
    public function test_styles_are_validated_and_stored(): void
    {
        $blocks = app(BlockRepositoryInterface::class);
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Styles', 'slug' => 'styles']);
        $styles = [
            ['breakpoint' => null, 'state' => null, 'props' => ['flex-direction' => 'column', 'gap' => '24px']],
            ['breakpoint' => 'mobile', 'state' => 'hover', 'props' => ['color' => ['$var' => 'color-primary'], 'opacity' => 0.5]],
        ];

        $block = $blocks->create(['page_id' => $page['id'], 'type' => 'flex', 'styles' => $styles]);
        $instance = $blocks->addWidgetInstance($block['id'], 'hero', [], 0, [$styles[1]]);

        $this->assertSameIgnoringKeyOrder($styles, $blocks->find($block['id'])['styles']);
        $this->assertSameIgnoringKeyOrder([$styles[1]], $blocks->find($block['id'])['widget_instances'][0]['styles']);
        $this->assertSame($instance['id'], $blocks->find($block['id'])['widget_instances'][0]['id']);

        $invalid = [
            [['breakpoint' => 'watch', 'props' => []]],
            [['state' => 'visited', 'props' => []]],
            [['props' => ['Color' => 'red']]],
            [['props' => ['color' => 'red; background: url(x)']]],
            [['props' => ['color' => ['$dynamic' => 'x']]]],
            [['props' => []], ['breakpoint' => null, 'state' => null, 'props' => []]],
        ];
        foreach ($invalid as $index => $variants) {
            try {
                $blocks->update($block['id'], ['styles' => $variants]);
                $this->fail("Invalid styles #{$index} were accepted");
            } catch (TreeValidationException $e) {
                $this->assertStringStartsWith('/styles/', $e->errors()[0]['path']);
            }
        }
    }

    /**
     * Test the depth limit applies to nesting through create and update
     */
    public function test_depth_limit(): void
    {
        config(['laravel-page-builder.limits.max_depth' => 2]);
        $blocks = app(BlockRepositoryInterface::class);
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Deep', 'slug' => 'deep']);

        $root = $blocks->create(['page_id' => $page['id'], 'type' => 'flex']);
        $child = $blocks->create(['page_id' => $page['id'], 'type' => 'flex', 'parent_id' => $root['id']]);
        $other = $blocks->create(['page_id' => $page['id'], 'type' => 'flex']);
        $otherChild = $blocks->create(['page_id' => $page['id'], 'type' => 'flex', 'parent_id' => $other['id']]);

        try {
            $blocks->create(['page_id' => $page['id'], 'type' => 'flex', 'parent_id' => $child['id']]);
            $this->fail('A third level was created');
        } catch (InvalidValueException) {
        }

        try {
            $blocks->update($other['id'], ['parent_id' => $child['id']]);
            $this->fail('A subtree was moved below the depth limit');
        } catch (InvalidValueException) {
        }

        $this->assertNull($blocks->find($other['id'])['parent_id']);
        $this->assertSame($other['id'], $blocks->find($otherChild['id'])['parent_id']);
        $this->assertCount(4, $blocks->getForPage($page['id']));
    }

    /**
     * Test the node limit counts blocks and widget instances
     */
    public function test_node_limit(): void
    {
        config(['laravel-page-builder.limits.max_nodes' => 3]);
        $blocks = app(BlockRepositoryInterface::class);
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Big', 'slug' => 'big']);

        $block = $blocks->create(['page_id' => $page['id'], 'type' => 'flex']);
        $blocks->addWidgetInstance($block['id'], 'hero', []);
        $blocks->create(['page_id' => $page['id'], 'type' => 'flex']);

        try {
            $blocks->addWidgetInstance($block['id'], 'hero', []);
            $this->fail('A fourth node was added');
        } catch (InvalidValueException) {
        }

        $this->assertCount(1, $blocks->find($block['id'])['widget_instances']);
    }

    /**
     * Test every change to snapshot content increments tree_version
     */
    public function test_tree_version_increments(): void
    {
        $pages = app(PageRepositoryInterface::class);
        $blocks = app(BlockRepositoryInterface::class);
        $templates = app(TemplateRepositoryInterface::class);
        $page = $pages->create(['title' => 'Versions', 'slug' => 'versions']);
        $this->assertSame(0, $pages->find($page['id'])['tree_version']);

        $block = $blocks->create(['page_id' => $page['id'], 'type' => 'flex']);
        $second = $blocks->create(['page_id' => $page['id'], 'type' => 'flex']);
        $blocks->update($block['id'], ['order' => 2]);
        $instance = $blocks->addWidgetInstance($block['id'], 'hero', []);
        $blocks->updateWidgetInstance($block['id'], $instance['id'], ['title' => 'Hi']);
        $blocks->removeWidgetInstance($block['id'], $instance['id']);
        $blocks->reorder($page['id'], [$second['id'], $block['id']]);
        $blocks->delete($second['id']);
        $this->assertSame(8, $pages->find($page['id'])['tree_version']);

        $pages->setSeo($page['id'], new SEO(title: 'Versions'));
        $pages->setMetatag($page['id'], 'robots', 'noindex');
        $pages->update($page['id'], ['title' => 'Versions 2']);
        $template = $templates->create(['name' => 'Landing', 'slug' => 'landing', 'view_path' => 'lpb::shell']);
        $templates->attachToPage($template['id'], $page['id']);
        $templates->detachFromPage($page['id']);
        $this->assertSame(13, $pages->find($page['id'])['tree_version']);
    }
}
