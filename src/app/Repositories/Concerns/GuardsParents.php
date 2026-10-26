<?php

namespace Andmarruda\Lpb\Repositories\Concerns;

use Andmarruda\Lpb\Exceptions\InvalidRelationException;
use Illuminate\Database\Eloquent\Model;

/**
 * Parent checks for self-referencing trees (blocks, menu items): the parent
 * must belong to the same owner and must not be the node or a descendant.
 */
trait GuardsParents
{
    /**
     * Check a parent stored as a row with a parent_id column.
     *
     * @param Model $model The tree model
     * @param string $ownerKey Owner foreign key column, e.g. page_id
     * @param string|int $ownerId
     * @param string|int $parentId
     * @param string|int|null $nodeId The node being updated, or null when creating
     * @param string $label Node name for error messages
     * @return void
     */
    protected function assertParentRecord(Model $model, string $ownerKey, string|int $ownerId, string|int $parentId, string|int|null $nodeId, string $label): void
    {
        $parent = $model->newQuery()->find($parentId);
        if (!$parent || (string) $parent->{$ownerKey} !== (string) $ownerId) {
            throw new InvalidRelationException("Parent {$label} '{$parentId}' not found under '{$ownerId}'.");
        }

        for ($current = $parent; $current; $current = $current->parent_id ? $model->newQuery()->find($current->parent_id) : null) {
            if ($nodeId !== null && (string) $current->getKey() === (string) $nodeId) {
                throw new InvalidRelationException(ucfirst($label) . " '{$nodeId}' cannot be nested inside itself.");
            }
        }
    }

    /**
     * Check a parent among embedded nodes that carry id and parent_id.
     *
     * @param array $nodes
     * @param string $parentId
     * @param string|null $nodeId The node being updated, or null when creating
     * @param string $label Node name for error messages
     * @return void
     */
    protected function assertEmbeddedParent(array $nodes, string $parentId, ?string $nodeId, string $label): void
    {
        $parents = array_column($nodes, 'parent_id', 'id');
        if (!array_key_exists($parentId, $parents)) {
            throw new InvalidRelationException("Parent {$label} '{$parentId}' not found.");
        }

        for ($current = $parentId; $current !== null; $current = $parents[$current] ?? null) {
            if ($current === $nodeId) {
                throw new InvalidRelationException(ucfirst($label) . " '{$nodeId}' cannot be nested inside itself.");
            }
        }
    }
}
