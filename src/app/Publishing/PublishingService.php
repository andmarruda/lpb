<?php
namespace Andmarruda\Lpb\Publishing;

use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Andmarruda\Lpb\Contracts\PublishingServiceInterface;
use Andmarruda\Lpb\Contracts\RevisionRepositoryInterface;
use Andmarruda\Lpb\Events\PageArchived;
use Andmarruda\Lpb\Events\PagePublished;
use Andmarruda\Lpb\Events\PageRolledBack;
use Andmarruda\Lpb\Events\PageUnpublished;
use Andmarruda\Lpb\Exceptions\InvalidRelationException;
use Andmarruda\Lpb\Exceptions\InvalidStateTransitionException;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Publishing, retention and rollback of pages over the repositories of the
 * configured driver (ADR 08). See PublishingServiceInterface for the rules.
 */
class PublishingService implements PublishingServiceInterface
{
    private const TYPE = PageRepositoryInterface::REVISION_TYPE;

    public function __construct(
        private PageRepositoryInterface $pages,
        private PageTreeRepositoryInterface $trees,
        private RevisionRepositoryInterface $revisions,
        private Dispatcher $events,
    ) {
    }

    public function publish(string $pageId, ?string $by = null): array
    {
        $page = $this->page($pageId);
        $this->assertFrom($page, PublishState::Published, [PublishState::Draft, PublishState::Published]);

        $snapshot = $this->trees->getWorkingTree($pageId);
        $revision = $this->revisions->record(self::TYPE, $pageId, $snapshot, $snapshot['tree_version'], $by);
        $revisionId = (string) $revision['id'];

        $this->pages->setPublication($pageId, PublishState::Published, $revisionId);
        $this->revisions->prune(self::TYPE, $pageId, max(1, (int) config('laravel-page-builder.revisions.keep', 3)), $revisionId);

        $this->events->dispatch(new PagePublished($pageId, $page['slug'], $revisionId, $by));
        return $revision;
    }

    public function unpublish(string $pageId, ?string $by = null): void
    {
        $page = $this->page($pageId);
        $this->assertFrom($page, PublishState::Draft, [PublishState::Published]);

        $this->pages->setPublication($pageId, PublishState::Draft, null);
        $this->events->dispatch(new PageUnpublished($pageId, $page['slug'], null, $by));
    }

    public function archive(string $pageId, ?string $by = null): void
    {
        $page = $this->page($pageId);
        $this->assertFrom($page, PublishState::Archived, [PublishState::Draft, PublishState::Published]);

        $this->pages->setPublication($pageId, PublishState::Archived, null);
        $this->events->dispatch(new PageArchived($pageId, $page['slug'], null, $by));
    }

    public function unarchive(string $pageId): void
    {
        $page = $this->page($pageId);
        $this->assertFrom($page, PublishState::Draft, [PublishState::Archived]);

        $this->pages->setPublication($pageId, PublishState::Draft, null);
    }

    public function rollback(string $pageId, string $revisionId, ?string $by = null): void
    {
        $page = $this->page($pageId);
        $this->assertFrom($page, PublishState::Published, [PublishState::Draft, PublishState::Published]);
        $revision = $this->revision($pageId, $revisionId);

        $this->pages->setPublication($pageId, PublishState::Published, (string) $revision['id']);
        $this->events->dispatch(new PageRolledBack($pageId, $page['slug'], (string) $revision['id'], $by));
    }

    public function restoreToDraft(string $pageId, string $revisionId, int $expectedVersion): array
    {
        $page = $this->page($pageId);
        if (PublishState::from($page['status']) === PublishState::Archived) {
            throw new InvalidStateTransitionException('Unarchive the page before restoring a revision into it.');
        }

        $revision = $this->revision($pageId, $revisionId);
        return $this->trees->saveTree($pageId, ['blocks' => $revision['payload']['blocks'] ?? []], $expectedVersion);
    }

    public function hasUnpublishedChanges(string $pageId): bool
    {
        $page = $this->page($pageId);
        if (empty($page['published_revision_id'])) {
            return true;
        }

        $revision = $this->revisions->findFor(self::TYPE, $pageId, (string) $page['published_revision_id']);
        return $revision === null || (int) $revision['tree_version'] !== (int) $page['tree_version'];
    }

    public function revisions(string $pageId): array
    {
        $page = $this->page($pageId);

        return array_map(fn (array $revision) => [
            'id' => (string) $revision['id'],
            'tree_version' => (int) $revision['tree_version'],
            'created_by' => $revision['created_by'] ?? null,
            'created_at' => $revision['created_at'] ?? null,
            'is_published' => (string) $revision['id'] === (string) ($page['published_revision_id'] ?? ''),
        ], $this->revisions->getFor(self::TYPE, $pageId));
    }

    private function page(string $pageId): array
    {
        return $this->pages->find($pageId) ?? throw new InvalidRelationException("Page '{$pageId}' not found.");
    }

    private function revision(string $pageId, string $revisionId): array
    {
        return $this->revisions->findFor(self::TYPE, $pageId, $revisionId)
            ?? throw new InvalidRelationException("Revision '{$revisionId}' is not a retained revision of page '{$pageId}'.");
    }

    /**
     * @param array $page
     * @param PublishState $to
     * @param PublishState[] $allowed
     * @return void
     */
    private function assertFrom(array $page, PublishState $to, array $allowed): void
    {
        $status = PublishState::from($page['status'] instanceof PublishState ? $page['status']->value : $page['status']);
        if (!in_array($status, $allowed, true)) {
            throw InvalidStateTransitionException::between($status, $to);
        }
    }
}
