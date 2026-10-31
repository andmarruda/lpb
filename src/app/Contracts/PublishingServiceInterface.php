<?php
namespace Andmarruda\Lpb\Contracts;

/**
 * Publishing, retention and rollback of pages (ADR 08).
 *
 * | Operation      | Allowed from      | Effect                                                         |
 * |----------------|-------------------|----------------------------------------------------------------|
 * | publish        | draft, published  | Snapshot the working tree into a revision and serve it; prune  |
 * | unpublish      | published         | Stop serving; status draft                                     |
 * | archive        | draft, published  | Stop serving; status archived                                  |
 * | unarchive      | archived          | Status draft                                                   |
 * | rollback       | draft, published  | Serve a retained revision; the working tree is untouched       |
 * | restoreToDraft | draft, published  | Copy a retained revision's blocks into the working tree        |
 *
 * Disallowed operations throw InvalidStateTransitionException; a missing
 * page or a revision of another page throws InvalidRelationException.
 */
interface PublishingServiceInterface
{
    /**
     * @param string $pageId
     * @param string|null $by Opaque user identifier stored on the revision
     * @return array The new revision
     */
    public function publish(string $pageId, ?string $by = null): array;

    public function unpublish(string $pageId, ?string $by = null): void;

    public function archive(string $pageId, ?string $by = null): void;

    public function unarchive(string $pageId): void;

    public function rollback(string $pageId, string $revisionId, ?string $by = null): void;

    /**
     * @param string $pageId
     * @param string $revisionId
     * @param int $expectedVersion The tree_version the editor has
     * @return array The new working tree
     * @throws \Andmarruda\Lpb\Exceptions\TreeVersionConflictException
     */
    public function restoreToDraft(string $pageId, string $revisionId, int $expectedVersion): array;

    /**
     * Whether the working tree differs from what is served (or nothing is served).
     */
    public function hasUnpublishedChanges(string $pageId): bool;

    /**
     * Retained revisions of a page, newest first, without payloads.
     *
     * @param string $pageId
     * @return array<int, array{id: string, tree_version: int, created_by: string|null, created_at: mixed, is_published: bool}>
     */
    public function revisions(string $pageId): array;
}
