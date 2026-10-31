<?php

namespace Andmarruda\Lpb\Tests\Unit\Tree;

use PHPUnit\Framework\TestCase;
use Andmarruda\Lpb\Tree\FlatTree;

/**
 * Unit tests for flat and nested tree conversion
 */
class FlatTreeTest extends TestCase
{
    private function flat(): array
    {
        return [
            ['id' => 'B', 'parent_id' => null, 'type' => 'grid', 'order' => 1, 'styles' => [], 'widget_instances' => []],
            ['id' => 'A', 'parent_id' => null, 'type' => 'flex', 'order' => 0, 'styles' => [], 'widget_instances' => [
                ['id' => 'W2', 'widget' => 'text', 'schema_version' => 1, 'config' => [], 'styles' => [], 'order' => 1],
                ['id' => 'W1', 'widget' => 'hero', 'schema_version' => 2, 'config' => ['title' => 'Hi'], 'styles' => [], 'order' => 0],
            ]],
            ['id' => 'C', 'parent_id' => 'A', 'type' => 'flex', 'order' => 0, 'styles' => [], 'widget_instances' => []],
        ];
    }

    /**
     * Test flat blocks nest by parent and sort by order
     */
    public function test_nest(): void
    {
        $tree = FlatTree::nest($this->flat());

        $this->assertSame(['A', 'B'], array_column($tree, 'id'));
        $this->assertSame(['W1', 'W2'], array_column($tree[0]['widgets'], 'id'));
        $this->assertSame(['C'], array_column($tree[0]['children'], 'id'));
        $this->assertSame(['id', 'type', 'styles', 'widgets', 'children'], array_keys($tree[0]));
        $this->assertSame(['id', 'widget', 'schema_version', 'config', 'styles'], array_keys($tree[0]['widgets'][0]));
        $this->assertSame(2, FlatTree::depth($tree));
        $this->assertSame(5, FlatTree::count($tree));
        $this->assertSame(['A', 'C', 'B'], FlatTree::blockIds($tree));
    }

    /**
     * Test nesting with orders keeps them, and flattening restores parents and orders
     */
    public function test_round_trip_with_order(): void
    {
        $tree = FlatTree::nest($this->flat(), withOrder: true);
        $this->assertSame(1, $tree[1]['order']);
        $this->assertSame(1, $tree[0]['widgets'][1]['order']);

        $flat = FlatTree::flatten($tree);
        $this->assertSame(['A', 'C', 'B'], array_column($flat, 'id'));
        $this->assertSame([null, 'A', null], array_column($flat, 'parent_id'));
        $this->assertSame([0, 0, 1], array_column($flat, 'order'));
        $this->assertSame(['W1', 'W2'], array_column($flat[0]['widget_instances'], 'id'));
    }

    /**
     * Test nodes without an order get their position, and orphans become roots
     */
    public function test_positions_and_orphans(): void
    {
        $flat = FlatTree::flatten([['id' => 'X', 'type' => 'flex', 'widgets' => [], 'children' => []], ['id' => 'Y', 'type' => 'flex']]);
        $this->assertSame([0, 1], array_column($flat, 'order'));

        $tree = FlatTree::nest([['id' => 'O', 'parent_id' => 'gone', 'type' => 'flex'], ['id' => 'S', 'parent_id' => 'S', 'type' => 'flex']]);
        $this->assertSame(['O'], array_column($tree, 'id'));
        $this->assertSame(0, FlatTree::depth([]));
    }
}
