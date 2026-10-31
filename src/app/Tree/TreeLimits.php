<?php
namespace Andmarruda\Lpb\Tree;

use Andmarruda\Lpb\Exceptions\InvalidValueException;

/**
 * Depth and size limits of a page tree (laravel-page-builder.limits).
 */
final class TreeLimits
{
    public function __construct(private int $maxDepth, private int $maxNodes)
    {
    }

    public static function fromConfig(): self
    {
        return new self(
            (int) config('laravel-page-builder.limits.max_depth', 10),
            (int) config('laravel-page-builder.limits.max_nodes', 1000),
        );
    }

    public function maxDepth(): int
    {
        return $this->maxDepth;
    }

    public function maxNodes(): int
    {
        return $this->maxNodes;
    }

    /**
     * @param int $depth Block levels; root blocks are level 1
     * @param int $nodes Blocks plus widget instances
     * @return void
     * @throws InvalidValueException
     */
    public function assertWithin(int $depth, int $nodes): void
    {
        if ($depth > $this->maxDepth) {
            throw new InvalidValueException("The page tree is {$depth} levels deep; at most {$this->maxDepth} are allowed.");
        }
        if ($nodes > $this->maxNodes) {
            throw new InvalidValueException("The page tree has {$nodes} nodes; at most {$this->maxNodes} are allowed.");
        }
    }

    /**
     * Check a tree of flat blocks (id, parent_id, widget_instances).
     *
     * @param array $blocks
     * @return void
     */
    public function assertFlat(array $blocks): void
    {
        $nested = FlatTree::nest($blocks);
        $this->assertWithin(FlatTree::depth($nested), FlatTree::count($nested));
    }
}
