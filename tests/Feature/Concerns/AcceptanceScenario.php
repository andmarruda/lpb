<?php

namespace Andmarruda\Lpb\Tests\Feature\Concerns;

use Andmarruda\Lpb\Contracts\BlockRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\RevisionRepositoryInterface;
use Andmarruda\Lpb\Contracts\TemplateRepositoryInterface;
use Andmarruda\Lpb\Exceptions\InvalidStateTransitionException;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\ValueObjects\SEO;

/**
 * Phase 1 acceptance scenario, run identically on every driver.
 *
 * Repositories are resolved from the container, so the scenario only
 * touches the contracts; the using class prepares the database.
 */
trait AcceptanceScenario
{
    use ComparesStoredArrays;

    /**
     * Fields restored from a page revision payload.
     */
    private const RESTORABLE_FIELDS = ['title', 'slug', 'status', 'template_id', 'seo', 'extra_css', 'extra_js'];

    /**
     * Test the full page lifecycle through the repository contracts
     */
    public function test_page_lifecycle(): void
    {
        $templates = app(TemplateRepositoryInterface::class);
        $pages = app(PageRepositoryInterface::class);
        $blocks = app(BlockRepositoryInterface::class);
        $revisions = app(RevisionRepositoryInterface::class);
        $type = PageRepositoryInterface::REVISION_TYPE;

        // 1. Template: active and default
        $template = $templates->create(['name' => 'Landing', 'slug' => 'landing', 'view_path' => 'lpb.templates.landing']);
        $this->assertTrue($templates->setDefault($template['id']));
        $this->assertSame($template['id'], $templates->getDefault()['id']);
        $this->assertSame([$template['id']], array_column($templates->getActive(), 'id'));

        // 2. Draft page with the template
        $page = $pages->create(['title' => 'Home', 'slug' => 'home']);
        $this->assertSame('draft', $page['status']);
        $this->assertTrue($templates->attachToPage($template['id'], $page['id']));

        // 3. Parent and child blocks
        $row = $blocks->create(['page_id' => $page['id'], 'type' => 'row']);
        $column = $blocks->create(['page_id' => $page['id'], 'type' => 'column', 'parent_id' => $row['id']]);

        // 4. A widget instance with JSON config on each block
        $heroConfig = ['title' => 'Welcome', 'buttons' => [['label' => 'Start', 'url' => '/start']]];
        $blocks->addWidgetInstance($row['id'], 'App\\Widgets\\Hero', $heroConfig);
        $blocks->addWidgetInstance($column['id'], 'App\\Widgets\\Text', ['body' => '<p>Hello</p>']);

        // 5. SEO through the value object, plus a supplementary metatag
        $seo = new SEO(title: 'Home', description: 'Welcome home', canonical: 'https://example.com/');
        $pages->setSeo($page['id'], $seo);
        $pages->setMetatag($page['id'], 'google-site-verification', 'abc123');

        // 6. Publish: transition succeeds and records a revision
        $published = $pages->transition($page['id'], PublishState::Published, 'editor-1');
        $this->assertSame('published', $published['status']);
        $revisionsAfterPublish = $revisions->getFor($type, $page['id']);
        $this->assertCount(1, $revisionsAfterPublish);
        $firstRevision = $revisionsAfterPublish[0];
        $this->assertSame('published', $firstRevision['status']);
        $this->assertSame('editor-1', $firstRevision['created_by']);

        // 7. Retrieve by slug with every relation
        $loaded = $pages->findBySlugWithRelations('home');
        $this->assertSame('published', $loaded['status']);
        $this->assertSame('landing', $loaded['template']['slug']);
        $this->assertSameIgnoringKeyOrder($seo->toArray(), $loaded['seo']);
        $this->assertTrue($pages->getSeo($page['id'])->equals($seo));
        $this->assertSame([['google-site-verification', 'abc123']], array_map(
            fn (array $tag) => [$tag['name'], $tag['content']],
            $loaded['metatags']
        ));
        $this->assertSame([$row['id'], $column['id']], array_column($loaded['blocks'], 'id'));
        $this->assertSame([null, $row['id']], array_column($loaded['blocks'], 'parent_id'));
        $this->assertSame('App\\Widgets\\Hero', $loaded['blocks'][0]['widget_instances'][0]['widget']);
        $this->assertSameIgnoringKeyOrder($heroConfig, $loaded['blocks'][0]['widget_instances'][0]['config']);
        $this->assertSame(['body' => '<p>Hello</p>'], $loaded['blocks'][1]['widget_instances'][0]['config']);
        $this->assertNull($pages->findBySlugWithRelations('missing'));

        // 8. Unpublish: back to draft with a second revision, then edit the draft
        $pages->transition($page['id'], PublishState::Draft, 'editor-2');
        $this->assertSame(['draft', 'published'], array_column($revisions->getFor($type, $page['id']), 'status'));
        $pages->update($page['id'], ['title' => 'Home (draft)']);
        $pages->setSeo($page['id'], new SEO(title: 'Draft'));

        // 9. Restore the first revision
        $payload = $revisions->restore($type, $page['id'], $firstRevision['id']);
        $pages->update($page['id'], array_intersect_key($payload, array_flip(self::RESTORABLE_FIELDS)));

        $restored = $pages->findBySlugWithRelations('home');
        foreach (self::RESTORABLE_FIELDS as $field) {
            $this->assertSameIgnoringKeyOrder($payload[$field] ?? null, $restored[$field] ?? null, "Field {$field} was not restored");
        }
        $this->assertSame('Home', $restored['title']);
        $this->assertSame('published', $restored['status']);
        $this->assertTrue($pages->getSeo($page['id'])->equals($seo));
        $this->assertSameIgnoringKeyOrder($payload['blocks'], $restored['blocks']);
        $this->assertCount(3, $revisions->getFor($type, $page['id']));
    }

    /**
     * Test an archived page cannot be published directly and stays untouched
     */
    public function test_archived_page_must_return_to_draft_before_publishing(): void
    {
        $pages = app(PageRepositoryInterface::class);
        $revisions = app(RevisionRepositoryInterface::class);
        $page = $pages->create(['title' => 'Old', 'slug' => 'old']);

        $pages->transition($page['id'], PublishState::Archived);

        try {
            $pages->transition($page['id'], PublishState::Published);
            $this->fail('An archived page was published directly');
        } catch (InvalidStateTransitionException) {
        }

        $this->assertSame('archived', $pages->find($page['id'])['status']);

        $pages->transition($page['id'], PublishState::Draft);
        $pages->transition($page['id'], PublishState::Published);
        $this->assertSame(['published', 'draft', 'archived'], array_column($revisions->getFor(PageRepositoryInterface::REVISION_TYPE, $page['id']), 'status'));
    }
}
