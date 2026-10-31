<?php

namespace Andmarruda\Lpb\Tests\Feature\Mongo;

use Andmarruda\Lpb\Tests\Feature\Concerns\RegistersTestWidgets;
use Tests\TestCase;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\Repositories\Mongo\BlockRepository;
use Andmarruda\Lpb\Repositories\Mongo\PageRepository;
use Andmarruda\Lpb\Repositories\Mongo\TemplateRepository;
use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Models\Mongo\Revision;
use Andmarruda\Lpb\Models\Mongo\Template;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\ValueObjects\SEO;

/**
 * MongoDB page fields, publication state and SEO integration tests
 */
class PageLifecycleIntegrationTest extends TestCase
{
    use RegistersTestWidgets;

    protected PageRepository $pages;
    protected BlockRepository $blocks;
    protected TemplateRepository $templates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerTestWidgets();

        Page::truncate();
        Revision::truncate();
        Template::truncate();

        $this->pages = new PageRepository(new Page());
        $this->blocks = new BlockRepository(new Page());
        $this->templates = new TemplateRepository(new Template(), new Page());
    }

    protected function tearDown(): void
    {
        Page::truncate();
        Revision::truncate();
        Template::truncate();

        parent::tearDown();
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
     * Test update rejects status changes, which go through the publishing service
     */
    public function test_update_rejects_status(): void
    {
        $page = $this->createPage();

        try {
            $this->pages->update($page['id'], ['title' => 'Changed', 'status' => 'published']);
            $this->fail('A status change through update was accepted');
        } catch (InvalidValueException) {
        }

        $this->assertSame('Home', $this->pages->find($page['id'])['title']);
        $this->assertSame('draft', $this->pages->find($page['id'])['status']);
    }

    /**
     * Test setPublication sets the status and the served revision without a new tree_version
     */
    public function test_set_publication(): void
    {
        $page = $this->createPage();
        $version = $this->pages->find($page['id'])['tree_version'];

        $published = $this->pages->setPublication($page['id'], PublishState::Published, '01JB0000000000000000000000');

        $this->assertSame('published', $published['status']);
        $this->assertSame('01JB0000000000000000000000', $published['published_revision_id']);
        $this->assertSame($version, $published['tree_version']);
        $this->assertSame('archived', $this->pages->setPublication($page['id'], PublishState::Archived, null)['status']);
        $this->assertNull($this->pages->setPublication('000000000000000000000000', PublishState::Draft, null));
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

        $this->assertTrue(Page::where('seo.title', 'Welcome')->exists(), 'SEO is not stored as an embedded document');

        $seo = $this->pages->getSeo($page['id']);
        $this->assertInstanceOf(SEO::class, $seo);
        $this->assertSame('https://example.com/og.png', $seo->ogImage);

        $this->pages->setSeo($page['id'], new SEO());
        $this->assertNull($this->pages->getSeo($page['id']));
        $this->assertNull($this->pages->setSeo('000000000000000000000000', new SEO(title: 'x')));
        $this->assertNull($this->pages->getSeo('000000000000000000000000'));
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
