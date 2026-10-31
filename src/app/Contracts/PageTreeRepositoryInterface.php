<?php
namespace Andmarruda\Lpb\Contracts;

/**
 * The working tree of a page as the canonical tree (ADR 12), saved
 * atomically with optimistic concurrency on tree_version (ADR 11).
 */
interface PageTreeRepositoryInterface
{
    /**
     * Working tree as canonical tree (unresolved references, configs
     * upgraded to their widget's current version), with tree_version.
     *
     * @param string $pageId
     * @return array|null Null if the page does not exist
     */
    public function getWorkingTree(string $pageId): ?array;

    /**
     * Replace the working tree atomically.
     *
     * Only the tree's blocks are used. The whole input is validated before
     * anything is written; node ids are kept and new nodes bring
     * client-generated ULIDs.
     *
     * @param string $pageId
     * @param array $tree Canonical tree, or at least ['blocks' => [...]]
     * @param int $expectedVersion The tree_version the client edited
     * @return array The stored working tree with the new tree_version
     * @throws \Andmarruda\Lpb\Exceptions\TreeVersionConflictException When tree_version != $expectedVersion
     * @throws \Andmarruda\Lpb\Exceptions\TreeValidationException On invalid input
     * @throws \Andmarruda\Lpb\Exceptions\InvalidRelationException If the page does not exist
     */
    public function saveTree(string $pageId, array $tree, int $expectedVersion): array;

    /**
     * Rewrite stored widget configs older than their definition to the
     * current version, without changing tree_version (the content is the
     * same, only its stored shape changes).
     *
     * @param string $pageId
     * @return int Number of widget instances rewritten
     */
    public function upgradeStoredContent(string $pageId): int;
}
