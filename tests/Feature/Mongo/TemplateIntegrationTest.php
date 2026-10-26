<?php

namespace Andmarruda\Lpb\Tests\Feature\Mongo;

use Tests\TestCase;
use Andmarruda\Lpb\Contracts\TemplateRepositoryInterface;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\Repositories\Mongo\TemplateRepository;
use Andmarruda\Lpb\Repositories\Mongo\PageRepository;
use Andmarruda\Lpb\Models\Mongo\Template;
use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\ValueObjects\TemplateStatus;

/**
 * MongoDB TemplateRepository integration tests
 */
class TemplateIntegrationTest extends TestCase
{
    protected TemplateRepository $repository;
    protected PageRepository $pages;

    protected function setUp(): void
    {
        parent::setUp();

        Template::truncate();
        Page::truncate();

        $this->repository = new TemplateRepository(new Template(), new Page());
        $this->pages = new PageRepository(new Page());
    }

    protected function tearDown(): void
    {
        Template::truncate();
        Page::truncate();

        parent::tearDown();
    }

    protected function createTemplate(array $overrides = []): array
    {
        return $this->repository->create(array_merge([
            'name' => 'Landing',
            'slug' => 'landing',
            'view_path' => 'lpb.templates.landing',
        ], $overrides));
    }

    protected function createPage(): array
    {
        return $this->pages->create(['title' => 'Home', 'slug' => 'home', 'status' => 'draft']);
    }

    /**
     * Test the container binds the MongoDB template repository
     */
    public function test_container_binds_template_repository(): void
    {
        $this->assertInstanceOf(TemplateRepository::class, app(TemplateRepositoryInterface::class));
    }

    /**
     * Test create applies defaults and find by slug
     */
    public function test_create_and_find_by_slug(): void
    {
        $template = $this->createTemplate();

        $this->assertFalse($template['is_default']);
        $this->assertSame('active', $template['status']);

        $found = $this->repository->findBySlug('landing');
        $this->assertSame($template['id'], $found['id']);
        $this->assertSame(TemplateStatus::Active, Template::find($template['id'])->status);
        $this->assertNull($this->repository->findBySlug('missing'));
    }

    /**
     * Test an invalid slug is rejected
     */
    public function test_rejects_invalid_slug(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->createTemplate(['slug' => 'Landing Page']);
    }

    /**
     * Test only active templates are returned, ordered by name
     */
    public function test_get_active(): void
    {
        $this->createTemplate(['name' => 'Zeta', 'slug' => 'zeta']);
        $this->createTemplate(['name' => 'Alpha', 'slug' => 'alpha']);
        $this->createTemplate(['name' => 'Off', 'slug' => 'off', 'status' => TemplateStatus::Inactive]);

        $this->assertSame(['Alpha', 'Zeta'], array_column($this->repository->getActive(), 'name'));
    }

    /**
     * Test setDefault keeps a single default template
     */
    public function test_set_default_keeps_single_default(): void
    {
        $first = $this->createTemplate(['slug' => 'first']);
        $second = $this->createTemplate(['slug' => 'second']);

        $this->assertNull($this->repository->getDefault());

        $this->assertTrue($this->repository->setDefault($first['id']));
        $this->assertSame($first['id'], $this->repository->getDefault()['id']);

        $this->assertTrue($this->repository->setDefault($second['id']));
        $this->assertSame($second['id'], $this->repository->getDefault()['id']);
        $this->assertFalse($this->repository->find($first['id'])['is_default']);

        $this->assertFalse($this->repository->setDefault('000000000000000000000000'));
    }

    /**
     * Test attach and detach a template on a page
     */
    public function test_attach_and_detach_page(): void
    {
        $template = $this->createTemplate();
        $page = $this->createPage();

        $this->assertTrue($this->repository->attachToPage($template['id'], $page['id']));
        $this->assertSame($template['id'], $this->pages->find($page['id'])['template_id']);

        $this->assertTrue($this->repository->detachFromPage($page['id']));
        $this->assertNull($this->pages->find($page['id'])['template_id'] ?? null);
    }

    /**
     * Test attach rejects missing records and inactive templates
     */
    public function test_attach_rejects_missing_or_inactive(): void
    {
        $inactive = $this->createTemplate(['status' => 'inactive']);
        $page = $this->createPage();

        $this->assertFalse($this->repository->attachToPage($inactive['id'], $page['id']));
        $this->assertFalse($this->repository->attachToPage('000000000000000000000000', $page['id']));
        $this->assertFalse($this->repository->attachToPage($inactive['id'], '000000000000000000000000'));
        $this->assertFalse($this->repository->detachFromPage('000000000000000000000000'));
    }

    /**
     * Test deleting a template detaches it from its pages
     */
    public function test_delete_template_detaches_pages(): void
    {
        $template = $this->createTemplate();
        $page = $this->createPage();
        $this->repository->attachToPage($template['id'], $page['id']);

        $this->assertTrue($this->repository->delete($template['id']));

        $this->assertNull($this->pages->find($page['id'])['template_id'] ?? null);
    }

    /**
     * Test template_slug is denormalised on attach and kept in sync on update
     */
    public function test_template_slug_is_denormalised(): void
    {
        $template = $this->createTemplate();
        $page = $this->createPage();

        $this->repository->attachToPage($template['id'], $page['id']);
        $this->assertSame('landing', $this->pages->find($page['id'])['template_slug']);

        $this->repository->update($template['id'], ['slug' => 'landing-v2']);
        $this->assertSame('landing-v2', $this->pages->find($page['id'])['template_slug']);

        $this->repository->detachFromPage($page['id']);
        $this->assertNull($this->pages->find($page['id'])['template_slug'] ?? null);
    }
}
