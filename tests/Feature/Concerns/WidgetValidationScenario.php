<?php

namespace Andmarruda\Lpb\Tests\Feature\Concerns;

use Andmarruda\Lpb\Contracts\BlockRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Exceptions\TreeValidationException;

/**
 * Widget config validation on node-level writes, shared by every driver.
 * The using class registers the fixture widgets.
 */
trait WidgetValidationScenario
{
    /**
     * Test unknown widgets and invalid configs are rejected and change nothing
     */
    public function test_widget_instances_are_validated(): void
    {
        $blocks = app(BlockRepositoryInterface::class);
        $pages = app(PageRepositoryInterface::class);
        $page = $pages->create(['title' => 'Widgets', 'slug' => 'widgets']);
        $block = $blocks->create(['page_id' => $page['id'], 'type' => 'flex']);
        $version = $pages->find($page['id'])['tree_version'];

        foreach ([['missing', [], '/widget'], ['hero', ['title' => str_repeat('x', 121)], '/config/title'], ['hero', ['unknown' => 1], '/config']] as [$widget, $config, $path]) {
            try {
                $blocks->addWidgetInstance($block['id'], $widget, $config);
                $this->fail("Widget {$widget} with an invalid config was accepted");
            } catch (TreeValidationException $e) {
                $this->assertSame($path, $e->errors()[0]['path']);
            }
        }

        $this->assertSame([], $blocks->find($block['id'])['widget_instances']);
        $this->assertSame($version, $pages->find($page['id'])['tree_version']);
    }

    /**
     * Test config updates are validated against the current schema and stored at its version
     */
    public function test_widget_config_updates_are_validated(): void
    {
        $blocks = app(BlockRepositoryInterface::class);
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Cards', 'slug' => 'cards']);
        $block = $blocks->create(['page_id' => $page['id'], 'type' => 'flex']);

        $card = $blocks->addWidgetInstance($block['id'], 'card', ['title' => 'One', 'size' => 'sm']);
        $this->assertSame(3, $card['schema_version']);

        try {
            $blocks->updateWidgetInstance($block['id'], $card['id'], ['title' => 'Two', 'size' => 'xl']);
            $this->fail('An invalid config update was accepted');
        } catch (TreeValidationException $e) {
            $this->assertSame('/config/size', $e->errors()[0]['path']);
        }

        $updated = $blocks->updateWidgetInstance($block['id'], $card['id'], ['title' => 'Two', 'size' => 'lg']);
        $this->assertSame('Two', $updated['config']['title']);
        $this->assertSame(3, $updated['schema_version']);
        $this->assertSame('Two', $blocks->find($block['id'])['widget_instances'][0]['config']['title']);
    }
}
