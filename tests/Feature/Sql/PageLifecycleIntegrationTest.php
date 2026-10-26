<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Andmarruda\Lpb\Exceptions\InvalidStateTransitionException;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\Repositories\Sql\BlockRepository;
use Andmarruda\Lpb\Repositories\Sql\PageRepository;
use Andmarruda\Lpb\Repositories\Sql\RevisionRepository;
use Andmarruda\Lpb\Repositories\Sql\TemplateRepository;
use Andmarruda\Lpb\Models\Sql\Page;
use Andmarruda\Lpb\Models\Sql\Revision;
use Andmarruda\Lpb\Models\Sql\Template;
use Andmarruda\Lpb\Models\Sql\Block;
use Andmarruda\Lpb\Models\Sql\WidgetInstance;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\ValueObjects\SEO;

/**
 * SQL page publish lifecycle, revisions and SEO integration tests
 */
class PageLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected PageRepository $pages;
    protected BlockRepository $blocks;
    protected RevisionRepository $revisions;
    protected TemplateRepository $templates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->revisions = new RevisionRepository(new Revision());
        $this->pages = new PageRepository(new Page(), $this->revisions);
        $this->blocks = new BlockRepository(new Block(), new Page(), new WidgetInstance());
        $this->templates = new TemplateRepository(new Template(), new Page());
    }

    protected function createPage(array $overrides = []): array
    {
        return $this->pages->create(array_merge(['title' => 'Home', 'slug' => 'home'], $overrides));
    }

    /**
     * Test pages default to draft
     */
    public function test_pages_default_to_draft(): void
    {
        $this->assertSame('draft', $this->createPage()['status']);
    }

    /**
     * Test transition records a revision with the full page state
     */
    public function test_transition_records_revision(): void
    {
        $page = $this->createPage();
        $block = $this->blocks->create(['page_id' => $page['id'], 'type' => 'row']);
        $this->blocks->addWidgetInstance($block['id'], 'hero', ['title' => 'Welcome']);
        $this->pages->setMetatag($page['id'], 'description', 'Home page');
        $this->pages->setSeo($page['id'], new SEO(title: 'Home'));

        $published = $this->pages->transition($page['id'], PublishState::Published, 'user-7');

        $this->assertSame('published', $published['status']);
        $revision = $this->revisions->getLatestFor('page', $page['id']);
        $this->assertSame('published', $revision['status']);
        $this->assertSame('user-7', $revision['created_by']);
        $this->assertSame('published', $revision['payload']['status']);
        $this->assertSame(['title' => 'Home'], $revision['payload']['seo']);
        $this->assertSame('description', $revision['payload']['metatags'][0]['name']);
        $this->assertSame(['title' => 'Welcome'], $revision['payload']['blocks'][0]['widget_instances'][0]['config']);
    }

    /**
     * Test an illegal transition throws and records nothing
     */
    public function test_illegal_transition(): void
    {
        $page = $this->createPage();
        $this->pages->transition($page['id'], PublishState::Archived);

        try {
            $this->pages->transition($page['id'], PublishState::Published);
            $this->fail('Archived page was published');
        } catch (InvalidStateTransitionException) {
        }

        $this->assertSame('archived', $this->pages->find($page['id'])['status']);
        $this->assertCount(1, $this->revisions->getFor('page', $page['id']));
        $this->assertNull($this->pages->transition('00000000-0000-0000-0000-000000000000', PublishState::Published));
    }

    /**
     * Test publish then unpublish records two revisions
     */
    public function test_publish_and_unpublish(): void
    {
        $page = $this->createPage();

        $this->pages->transition($page['id'], PublishState::Published);
        $this->pages->transition($page['id'], PublishState::Draft);

        $this->assertSame(['draft', 'published'], array_column($this->revisions->getFor('page', $page['id']), 'status'));
    }

    /**
     * Test a status change through update is a guarded transition
     */
    public function test_update_with_status_change(): void
    {
        $page = $this->createPage();

        $updated = $this->pages->update($page['id'], ['title' => 'Welcome', 'status' => 'published']);

        $this->assertSame('Welcome', $updated['title']);
        $this->assertSame('published', $updated['status']);
        $this->assertSame('Welcome', $this->revisions->getLatestFor('page', $page['id'])['payload']['title']);

        $this->pages->update($page['id'], ['title' => 'Again', 'status' => PublishState::Published]);
        $this->assertCount(1, $this->revisions->getFor('page', $page['id']));
    }

    /**
     * Test a rejected status change through update changes nothing
     */
    public function test_update_with_illegal_status_changes_nothing(): void
    {
        $page = $this->createPage();
        $this->pages->transition($page['id'], PublishState::Archived);

        try {
            $this->pages->update($page['id'], ['title' => 'Changed', 'status' => 'published']);
            $this->fail('Archived page was published through update');
        } catch (InvalidStateTransitionException) {
        }

        $this->assertSame('Home', $this->pages->find($page['id'])['title']);
    }

    /**
     * Test SEO set, get, clear and create from an array
     */
    public function test_seo(): void
    {
        $page = $this->createPage(['seo' => ['title' => 'Home', 'canonical' => 'https://example.com/']]);
        $this->assertSame(['title' => 'Home', 'canonical' => 'https://example.com/'], $page['seo']);

        $result = $this->pages->setSeo($page['id'], new SEO(title: 'Welcome', ogImage: 'https://example.com/og.png'));
        $this->assertSame(['title' => 'Welcome', 'og_image' => 'https://example.com/og.png'], $result['seo']);

        $seo = $this->pages->getSeo($page['id']);
        $this->assertInstanceOf(SEO::class, $seo);
        $this->assertSame('https://example.com/og.png', $seo->ogImage);

        $this->pages->setSeo($page['id'], new SEO());
        $this->assertNull($this->pages->getSeo($page['id']));
        $this->assertNull($this->pages->setSeo('00000000-0000-0000-0000-000000000000', new SEO(title: 'x')));
        $this->assertNull($this->pages->getSeo('00000000-0000-0000-0000-000000000000'));
    }

    /**
     * Test invalid SEO input is rejected
     */
    public function test_rejects_invalid_seo(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->createPage(['seo' => ['canonical' => '/relative']]);
    }

    /**
     * Test restoring a revision payload brings back the earlier state
     */
    public function test_restore_revision(): void
    {
        $page = $this->createPage();
        $first = $this->pages->transition($page['id'], PublishState::Published);
        $revision = $this->revisions->getLatestFor('page', $page['id']);

        $this->pages->update($page['id'], ['title' => 'Changed', 'status' => 'draft']);

        $payload = $this->revisions->restore('page', $page['id'], $revision['id']);
        $restored = $this->pages->update($page['id'], array_intersect_key($payload, array_flip(['title', 'status', 'seo'])));

        $this->assertSame($first['title'], $restored['title']);
        $this->assertSame('published', $restored['status']);
    }

    /**
     * Test templates store SEO
     */
    public function test_template_seo(): void
    {
        $template = $this->templates->create([
            'name' => 'Landing',
            'slug' => 'landing',
            'view_path' => 'lpb.templates.landing',
            'seo' => new SEO(robots: 'noindex'),
        ]);

        $this->assertSame(['robots' => 'noindex'], $template['seo']);
        $this->assertSame(['robots' => 'noindex'], $this->templates->find($template['id'])['seo']);
    }
}
