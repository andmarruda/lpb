<?php

namespace Andmarruda\Lpb\Tests\Feature\Concerns;

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
use Andmarruda\Lpb\Exceptions\TreeVersionConflictException;
use Andmarruda\Lpb\Support\Ulid;
use Illuminate\Support\Facades\Event;

/**
 * Publishing, retention and rollback (ADR 08) shared by every driver. The
 * using class prepares the database and registers the fixture widgets.
 */
trait PublishingScenario
{
    use ComparesStoredArrays;

    private function publishing(): PublishingServiceInterface
    {
        return app(PublishingServiceInterface::class);
    }

    /**
     * Create a page with one hero widget titled $title.
     */
    private function pageWithHero(string $slug, string $title): array
    {
        $page = app(PageRepositoryInterface::class)->create(['title' => ucfirst($slug), 'slug' => $slug]);
        app(PageTreeRepositoryInterface::class)->saveTree($page['id'], ['blocks' => [$this->heroBlock($title)]], 0);
        return app(PageRepositoryInterface::class)->find($page['id']);
    }

    private function heroBlock(string $title): array
    {
        return ['id' => Ulid::generate(), 'type' => 'flex', 'styles' => [], 'children' => [], 'widgets' => [
            ['id' => Ulid::generate(), 'widget' => 'hero', 'schema_version' => 1, 'config' => ['title' => $title], 'styles' => []],
        ]];
    }

    private function heroTitle(array $tree): string
    {
        return $tree['blocks'][0]['widgets'][0]['config']['title'];
    }

    /**
     * Test publish snapshots the working tree into a revision and serves it
     */
    public function test_publish(): void
    {
        Event::fake([PagePublished::class]);
        $page = $this->pageWithHero('home', 'Welcome');
        app(PageRepositoryInterface::class)->setMetatag($page['id'], 'robots', 'index');
        $this->assertTrue($this->publishing()->hasUnpublishedChanges($page['id']));

        $revision = $this->publishing()->publish($page['id'], 'editor-1');

        $stored = app(PageRepositoryInterface::class)->find($page['id']);
        $this->assertSame('published', $stored['status']);
        $this->assertSame((string) $revision['id'], $stored['published_revision_id']);
        $this->assertSame('published', $revision['status']);
        $this->assertSame('editor-1', $revision['created_by']);
        $this->assertSame($stored['tree_version'], $revision['tree_version']);
        $this->assertSame('Welcome', $this->heroTitle($revision['payload']));
        $this->assertSameIgnoringKeyOrder([['name' => 'robots', 'content' => 'index']], $revision['payload']['page']['metatags']);
        $this->assertFalse($this->publishing()->hasUnpublishedChanges($page['id']));

        Event::assertDispatched(PagePublished::class, fn (PagePublished $e) => $e->pageId === $page['id'] && $e->slug === 'home' && $e->revisionId === (string) $revision['id'] && $e->by === 'editor-1');
    }

    /**
     * Test editing a published page changes only the working tree until it is published again
     */
    public function test_editing_does_not_change_the_published_revision(): void
    {
        $page = $this->pageWithHero('home', 'Welcome');
        $revision = $this->publishing()->publish($page['id']);
        $trees = app(PageTreeRepositoryInterface::class);

        $tree = $trees->getWorkingTree($page['id']);
        $tree['blocks'][0]['widgets'][0]['config']['title'] = 'Draft title';
        $trees->saveTree($page['id'], $tree, $tree['tree_version']);

        $stored = app(PageRepositoryInterface::class)->find($page['id']);
        $served = app(RevisionRepositoryInterface::class)->findFor('page', $page['id'], $stored['published_revision_id']);
        $this->assertSame((string) $revision['id'], $stored['published_revision_id']);
        $this->assertSame('Welcome', $this->heroTitle($served['payload']));
        $this->assertTrue($this->publishing()->hasUnpublishedChanges($page['id']));

        $this->publishing()->publish($page['id']);
        $this->assertFalse($this->publishing()->hasUnpublishedChanges($page['id']));
    }

    /**
     * Test publishing five times keeps three revisions, including the published one
     */
    public function test_retention(): void
    {
        $page = $this->pageWithHero('home', 'v1');
        $ids = [];
        foreach (range(1, 5) as $n) {
            app(PageRepositoryInterface::class)->update($page['id'], ['title' => "Home {$n}"]);
            $ids[] = (string) $this->publishing()->publish($page['id'])['id'];
        }

        $retained = $this->publishing()->revisions($page['id']);
        $this->assertSame([$ids[4], $ids[3], $ids[2]], array_column($retained, 'id'));
        $this->assertSame([true, false, false], array_column($retained, 'is_published'));

        // Publishing after a rollback serves and keeps the new revision
        $this->publishing()->rollback($page['id'], $ids[2]);
        config(['laravel-page-builder.revisions.keep' => 1]);
        app(PageRepositoryInterface::class)->update($page['id'], ['title' => 'Home 6']);
        $latest = (string) $this->publishing()->publish($page['id'])['id'];
        $this->assertSame([$latest], array_column($this->publishing()->revisions($page['id']), 'id'));
    }

    /**
     * Test rollback serves a retained revision without touching the working tree
     */
    public function test_rollback(): void
    {
        Event::fake([PageRolledBack::class]);
        $page = $this->pageWithHero('home', 'First');
        $first = $this->publishing()->publish($page['id']);
        $trees = app(PageTreeRepositoryInterface::class);
        $tree = $trees->getWorkingTree($page['id']);
        $tree['blocks'][0]['widgets'][0]['config']['title'] = 'Second';
        $trees->saveTree($page['id'], $tree, $tree['tree_version']);
        $this->publishing()->publish($page['id']);
        $working = $trees->getWorkingTree($page['id']);

        $this->publishing()->rollback($page['id'], (string) $first['id'], 'editor-2');

        $this->assertSame((string) $first['id'], app(PageRepositoryInterface::class)->find($page['id'])['published_revision_id']);
        $this->assertSame($working, $trees->getWorkingTree($page['id']));
        $this->assertTrue($this->publishing()->hasUnpublishedChanges($page['id']));
        Event::assertDispatched(PageRolledBack::class, fn (PageRolledBack $e) => $e->revisionId === (string) $first['id'] && $e->by === 'editor-2');

        $other = $this->pageWithHero('other', 'Other');
        $this->expectException(InvalidRelationException::class);
        $this->publishing()->rollback($other['id'], (string) $first['id']);
    }

    /**
     * Test unpublish, archive and unarchive and the states they are allowed from
     */
    public function test_unpublish_archive_and_unarchive(): void
    {
        Event::fake([PageUnpublished::class, PageArchived::class]);
        $page = $this->pageWithHero('home', 'Welcome');
        $pages = app(PageRepositoryInterface::class);

        $this->assertThrows(fn () => $this->publishing()->unpublish($page['id']), InvalidStateTransitionException::class);

        $revision = $this->publishing()->publish($page['id']);
        $this->publishing()->unpublish($page['id']);
        $this->assertSame(['draft', null], [$pages->find($page['id'])['status'], $pages->find($page['id'])['published_revision_id']]);
        Event::assertDispatched(PageUnpublished::class, fn (PageUnpublished $e) => $e->pageId === $page['id'] && $e->revisionId === null);

        $this->publishing()->archive($page['id']);
        $this->assertSame('archived', $pages->find($page['id'])['status']);
        Event::assertDispatched(PageArchived::class);

        $this->assertThrows(fn () => $this->publishing()->publish($page['id']), InvalidStateTransitionException::class);
        $this->assertThrows(fn () => $this->publishing()->rollback($page['id'], (string) $revision['id']), InvalidStateTransitionException::class);
        $this->assertThrows(fn () => $this->publishing()->archive($page['id']), InvalidStateTransitionException::class);

        $this->publishing()->unarchive($page['id']);
        $this->assertSame('draft', $pages->find($page['id'])['status']);
        $this->assertThrows(fn () => $this->publishing()->unarchive($page['id']), InvalidStateTransitionException::class);
    }

    /**
     * Test restore to draft copies a revision's blocks into the working tree
     */
    public function test_restore_to_draft(): void
    {
        $page = $this->pageWithHero('home', 'Original');
        $revision = $this->publishing()->publish($page['id']);
        $trees = app(PageTreeRepositoryInterface::class);
        $tree = $trees->getWorkingTree($page['id']);
        $tree['blocks'] = [$this->heroBlock('Replaced')];
        $trees->saveTree($page['id'], $tree, $tree['tree_version']);
        $version = $trees->getWorkingTree($page['id'])['tree_version'];

        $this->assertThrows(fn () => $this->publishing()->restoreToDraft($page['id'], (string) $revision['id'], $version - 1), TreeVersionConflictException::class);

        $restored = $this->publishing()->restoreToDraft($page['id'], (string) $revision['id'], $version);

        $this->assertSame('Original', $this->heroTitle($restored));
        $this->assertSameIgnoringKeyOrder($revision['payload']['blocks'], $restored['blocks']);
        $this->assertSame($version + 1, $restored['tree_version']);
        $this->assertTrue($this->publishing()->hasUnpublishedChanges($page['id']));

        $this->publishing()->archive($page['id']);
        $this->assertThrows(fn () => $this->publishing()->restoreToDraft($page['id'], (string) $revision['id'], $version + 1), InvalidStateTransitionException::class);
    }

    /**
     * Test a missing page is reported
     */
    public function test_publishing_a_missing_page(): void
    {
        $this->expectException(InvalidRelationException::class);

        $this->publishing()->publish(Ulid::generate());
    }
}
