<?php

namespace Andmarruda\Lpb\Tests\Feature\Concerns;

use Andmarruda\Lpb\Contracts\MediaRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Andmarruda\Lpb\Contracts\PublishingServiceInterface;
use Andmarruda\Lpb\Contracts\RevisionRepositoryInterface;
use Andmarruda\Lpb\Contracts\TemplateRepositoryInterface;
use Andmarruda\Lpb\Delivery\DesignVersion;
use Andmarruda\Lpb\Delivery\PageDeliveryService;
use Andmarruda\Lpb\Events\DesignVersionBumped;
use Andmarruda\Lpb\Support\Ulid;
use Andmarruda\Lpb\ValueObjects\CacheKey;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\Webhooks\SendWebhook;
use Andmarruda\Lpb\Webhooks\WebhookSignature;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * Delivery payloads, cache keys and webhooks (ADR 09, ADR 12) shared by
 * every driver. The using class prepares the database and registers the
 * fixture widgets.
 */
trait DeliveryScenario
{
    private function delivery(): PageDeliveryService
    {
        return app(PageDeliveryService::class);
    }

    /**
     * A page whose tree has a hero widget titled $title with an image.
     */
    private function deliverablePage(string $slug, string $title): array
    {
        $media = app(MediaRepositoryInterface::class)->create([
            'disk' => 'public', 'path' => "img/{$slug}.png", 'filename' => "{$slug}.png",
            'mime_type' => 'image/png', 'size' => 10, 'meta' => ['alt' => 'Hero'],
        ]);
        $page = app(PageRepositoryInterface::class)->create(['title' => ucfirst($slug), 'slug' => $slug]);
        app(PageTreeRepositoryInterface::class)->saveTree($page['id'], ['blocks' => [[
            'id' => Ulid::generate(), 'type' => 'flex', 'styles' => [], 'children' => [],
            'widgets' => [['id' => Ulid::generate(), 'widget' => 'hero', 'schema_version' => 1, 'config' => [
                'title' => $title, 'image' => ['$media' => $media['id']],
            ], 'styles' => [['breakpoint' => null, 'state' => null, 'props' => ['color' => ['$var' => 'color-primary']]]]]],
        ]]], 0);
        return ['page' => app(PageRepositoryInterface::class)->find($page['id']), 'media' => $media];
    }

    private function retitle(string $pageId, string $title): void
    {
        $trees = app(PageTreeRepositoryInterface::class);
        $tree = $trees->getWorkingTree($pageId);
        $tree['blocks'][0]['widgets'][0]['config']['title'] = $title;
        $trees->saveTree($pageId, $tree, $tree['tree_version']);
    }

    private function servedTitle(string $slug): ?string
    {
        return $this->delivery()->published($slug)['blocks'][0]['widgets'][0]['config']['title'] ?? null;
    }

    /**
     * Test the published payload: canonical tree with references resolved
     */
    public function test_published_payload(): void
    {
        ['page' => $page, 'media' => $media] = $this->deliverablePage('home', 'Welcome');
        $this->assertNull($this->delivery()->published('home'));

        $revision = app(PublishingServiceInterface::class)->publish($page['id']);
        $payload = $this->delivery()->published('home');

        $this->assertSame(1, $payload['format_version']);
        $this->assertSame('home', $payload['page']['slug']);
        $this->assertSame((string) $revision['id'], $payload['revision_id']);
        $this->assertSame(1, $payload['design_version']);
        $this->assertArrayNotHasKey('tree_version', $payload);

        $widget = $payload['blocks'][0]['widgets'][0];
        $this->assertSame('Welcome', $widget['config']['title']);
        $this->assertSame($media['id'], $widget['config']['image']['id']);
        $this->assertSame('image/png', $widget['config']['image']['mime_type']);
        $this->assertSame(['alt' => 'Hero'], $widget['config']['image']['meta']);
        $this->assertStringEndsWith('img/home.png', $widget['config']['image']['url']);
        $this->assertSame(['$var' => 'color-primary'], $widget['styles'][0]['props']['color']);
        $this->assertNull($this->delivery()->published('missing'));
    }

    /**
     * Test publishing serves the new payload without any manual invalidation
     */
    public function test_publish_serves_new_payload_without_invalidation(): void
    {
        ['page' => $page] = $this->deliverablePage('home', 'First');
        $publishing = app(PublishingServiceInterface::class);
        $first = $publishing->publish($page['id']);
        $this->assertSame('First', $this->servedTitle('home'));
        $this->assertTrue(Cache::has(CacheKey::content((string) $first['id'], 1)->toString()));

        $this->retitle($page['id'], 'Second');
        $this->assertSame('First', $this->servedTitle('home'));

        $publishing->publish($page['id']);
        $this->assertSame('Second', $this->servedTitle('home'));

        $publishing->rollback($page['id'], (string) $first['id']);
        $this->assertSame('First', $this->servedTitle('home'));

        $publishing->unpublish($page['id']);
        $this->assertNull($this->delivery()->published('home'));

        $publishing->publish($page['id']);
        $publishing->archive($page['id']);
        $this->assertNull($this->delivery()->published('home'));
    }

    /**
     * Test renaming or deleting a published page updates what each slug serves
     */
    public function test_slug_change_and_delete(): void
    {
        ['page' => $page] = $this->deliverablePage('home', 'Welcome');
        app(PublishingServiceInterface::class)->publish($page['id']);
        $this->assertSame('Welcome', $this->servedTitle('home'));

        app(PageRepositoryInterface::class)->update($page['id'], ['slug' => 'start']);
        $this->assertNull($this->delivery()->published('home'));
        $this->assertSame('Welcome', $this->servedTitle('start'));

        app(PageRepositoryInterface::class)->delete($page['id']);
        $this->assertNull($this->delivery()->published('start'));
    }

    /**
     * Test a design bump, by command or by a template change, moves to new content keys
     */
    public function test_design_version(): void
    {
        Event::fake([DesignVersionBumped::class]);
        ['page' => $page] = $this->deliverablePage('home', 'Welcome');
        $revision = app(PublishingServiceInterface::class)->publish($page['id']);
        $this->delivery()->published('home');
        $design = app(DesignVersion::class);
        $this->assertSame(1, $design->current());

        $this->artisan('lpb:design:bump')->expectsOutputToContain('Design version is now 2')->assertSuccessful();
        $this->assertSame(2, $this->delivery()->published('home')['design_version']);
        $this->assertTrue(Cache::has(CacheKey::content((string) $revision['id'], 2)->toString()));
        Event::assertDispatched(DesignVersionBumped::class, fn (DesignVersionBumped $e) => $e->designVersion === 2);

        $template = app(TemplateRepositoryInterface::class)->create(['name' => 'Landing', 'slug' => 'landing', 'view_path' => 'lpb::shell']);
        app(TemplateRepositoryInterface::class)->update($template['id'], ['name' => 'Landing page']);
        $this->assertSame(3, $design->current());
        $this->assertSame(3, $this->delivery()->published('home')['design_version']);
    }

    /**
     * Test the preview is the working tree, resolved and never cached
     */
    public function test_preview(): void
    {
        ['page' => $page, 'media' => $media] = $this->deliverablePage('home', 'Draft');

        $preview = $this->delivery()->preview($page['id']);

        $this->assertNull($preview['revision_id']);
        $this->assertNull($preview['design_version']);
        $this->assertSame('Draft', $preview['blocks'][0]['widgets'][0]['config']['title']);
        $this->assertSame($media['id'], $preview['blocks'][0]['widgets'][0]['config']['image']['id']);

        $this->retitle($page['id'], 'Edited');
        $this->assertSame('Edited', $this->delivery()->preview($page['id'])['blocks'][0]['widgets'][0]['config']['title']);
        $this->assertNull($this->delivery()->preview(Ulid::generate()));
    }

    /**
     * Test revisions stored with an old widget version are upgraded when delivered
     */
    public function test_delivery_upgrades_old_revisions(): void
    {
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Cards', 'slug' => 'cards']);
        $tree = app(PageTreeRepositoryInterface::class)->getWorkingTree($page['id']);
        $tree['blocks'] = [['id' => Ulid::generate(), 'type' => 'flex', 'styles' => [], 'children' => [], 'widgets' => [
            ['id' => Ulid::generate(), 'widget' => 'card', 'schema_version' => 1, 'config' => ['heading' => 'Old'], 'styles' => []],
        ]]];
        $revision = app(RevisionRepositoryInterface::class)->record('page', $page['id'], $tree, $tree['tree_version']);
        app(PageRepositoryInterface::class)->setPublication($page['id'], PublishState::Published, (string) $revision['id']);

        $widget = $this->delivery()->published('cards')['blocks'][0]['widgets'][0];

        $this->assertSame(3, $widget['schema_version']);
        $this->assertSame(['title' => 'Old', 'size' => 'md'], $widget['config']);
    }

    /**
     * Test widgets that are no longer registered are left out of payloads
     */
    public function test_unregistered_widgets_are_not_delivered(): void
    {
        ['page' => $page] = $this->deliverablePage('home', 'Welcome');
        $tree = app(PageTreeRepositoryInterface::class)->getWorkingTree($page['id']);
        $tree['blocks'][0]['widgets'][] = ['id' => Ulid::generate(), 'widget' => 'text', 'schema_version' => 1, 'config' => ['body' => '<p>x</p>'], 'styles' => []];
        app(PageTreeRepositoryInterface::class)->saveTree($page['id'], $tree, $tree['tree_version']);
        app(PublishingServiceInterface::class)->publish($page['id']);

        $this->app->forgetInstance(\Andmarruda\Lpb\Widgets\WidgetRegistry::class);
        app(\Andmarruda\Lpb\Widgets\WidgetRegistry::class)->register(\Andmarruda\Lpb\Tests\Fixtures\Widgets\Hero::class);
        $this->artisan('lpb:design:bump')->assertSuccessful();

        $widgets = $this->delivery()->published('home')['blocks'][0]['widgets'];
        $this->assertSame(['hero'], array_column($widgets, 'widget'));
    }

    /**
     * Test deleting or renaming a published page notifies the webhooks
     */
    public function test_webhooks_for_deleted_and_renamed_pages(): void
    {
        config(['laravel-page-builder.webhooks.endpoints' => ['https://front.example/hook'], 'laravel-page-builder.webhooks.secret' => 'shh']);
        Bus::fake([SendWebhook::class]);
        ['page' => $page] = $this->deliverablePage('home', 'Welcome');

        app(PageRepositoryInterface::class)->update($page['id'], ['slug' => 'start']);
        app(PageRepositoryInterface::class)->delete($page['id']);

        $bodies = Bus::dispatched(SendWebhook::class)->map(fn (SendWebhook $job) => json_decode($job->body, true))->all();
        $this->assertSame(['page.slug_changed', 'page.deleted'], array_column($bodies, 'event'));
        $this->assertSame(['start', 'home'], [$bodies[0]['slug'], $bodies[0]['old_slug']]);
        $this->assertSame('start', $bodies[1]['slug']);
    }

    /**
     * Test publishing events queue signed webhooks to every endpoint
     */
    public function test_webhooks(): void
    {
        config([
            'laravel-page-builder.webhooks.endpoints' => ['https://front.example/hook', 'https://cdn.example/hook'],
            'laravel-page-builder.webhooks.secret' => 'shh',
        ]);
        Bus::fake([SendWebhook::class]);
        ['page' => $page] = $this->deliverablePage('home', 'Welcome');

        $revision = app(PublishingServiceInterface::class)->publish($page['id']);
        app(DesignVersion::class)->bump();

        $jobs = Bus::dispatched(SendWebhook::class);
        $this->assertCount(4, $jobs);
        $this->assertSame(['https://front.example/hook', 'https://cdn.example/hook'], $jobs->take(2)->pluck('url')->all());
        $this->assertSame(
            ['event' => 'page.published', 'site' => 'default', 'locale' => 'default', 'slug' => 'home', 'revision_id' => (string) $revision['id'], 'design_version' => 1],
            json_decode($jobs->first()->body, true)
        );
        $this->assertSame('design.bumped', json_decode($jobs->last()->body, true)['event']);
        $this->assertSame(2, json_decode($jobs->last()->body, true)['design_version']);

        // The job signs the exact body it posts and fails (to be retried) on errors
        Http::fake(['front.example/*' => Http::response(null, 204), 'cdn.example/*' => Http::response(null, 500)]);
        $jobs->first()->handle(app(\Illuminate\Http\Client\Factory::class));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://front.example/hook'
            && $request->body() === $jobs->first()->body
            && WebhookSignature::verify($request->body(), $request->header(WebhookSignature::HEADER)[0], 'shh'));

        $this->expectException(\Illuminate\Http\Client\RequestException::class);
        $jobs->get(1)->handle(app(\Illuminate\Http\Client\Factory::class));
    }

    /**
     * Test nothing is sent without a secret
     */
    public function test_webhooks_need_a_secret(): void
    {
        config(['laravel-page-builder.webhooks.endpoints' => ['https://front.example/hook'], 'laravel-page-builder.webhooks.secret' => null]);
        Bus::fake([SendWebhook::class]);
        ['page' => $page] = $this->deliverablePage('home', 'Welcome');

        app(PublishingServiceInterface::class)->publish($page['id']);

        Bus::assertNotDispatched(SendWebhook::class);
    }
}
