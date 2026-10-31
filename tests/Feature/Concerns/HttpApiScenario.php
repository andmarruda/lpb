<?php

namespace Andmarruda\Lpb\Tests\Feature\Concerns;

use Andmarruda\Lpb\Contracts\MenuRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\TemplateRepositoryInterface;
use Andmarruda\Lpb\Facades\Lpb;
use Andmarruda\Lpb\Support\Ulid;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * HTTP API, shell views and the view and Inertia flows (ADR 12), shared by
 * every driver. The using class prepares the database and registers the
 * fixture widgets.
 */
trait HttpApiScenario
{
    private const API = '/lpb/api';

    private function editor(bool $publish = true): static
    {
        Gate::define('lpb.edit', fn ($user = null) => $user !== null);
        Gate::define('lpb.publish', fn ($user = null) => $publish && $user !== null);
        return $this->actingAs(new GenericUser(['id' => 7, 'name' => 'Editor']));
    }

    private function heroTree(string $title): array
    {
        return ['blocks' => [[
            'id' => Ulid::generate(), 'type' => 'flex', 'styles' => [], 'children' => [],
            'widgets' => [['id' => Ulid::generate(), 'widget' => 'hero', 'schema_version' => 1, 'config' => ['title' => $title], 'styles' => []]],
        ]]];
    }

    /**
     * Create, fill and publish a page through the editor API.
     */
    private function publishedThroughApi(string $slug, string $title): array
    {
        $page = $this->postJson(self::API . '/editor/pages', ['title' => ucfirst($slug), 'slug' => $slug])->assertCreated()->json();
        $this->putJson(self::API . "/editor/pages/{$page['id']}/tree", ['tree' => $this->heroTree($title), 'expected_version' => $page['tree_version']])->assertOk();
        return $this->postJson(self::API . "/editor/pages/{$page['id']}/publish")->assertOk()->json();
    }

    /**
     * Test the editor API needs a user and the Gates, which deny by default
     */
    public function test_editor_requires_authentication_and_gates(): void
    {
        $this->getJson(self::API . '/editor/pages')->assertUnauthorized();

        $this->actingAs(new GenericUser(['id' => 1]))->getJson(self::API . '/editor/pages')->assertForbidden();

        $this->editor(publish: false);
        $page = $this->postJson(self::API . '/editor/pages', ['title' => 'Home', 'slug' => 'home'])->assertCreated()->json();
        $this->postJson(self::API . "/editor/pages/{$page['id']}/publish")->assertForbidden();
    }

    /**
     * Test the editor page flow: create, save the tree, publish and read back
     */
    public function test_editor_page_flow(): void
    {
        $this->editor();
        $this->getJson(self::API . '/editor/widgets')->assertOk()->assertJsonFragment(['name' => 'hero', 'label' => 'Hero banner']);

        $page = $this->postJson(self::API . '/editor/pages', ['title' => 'Home', 'slug' => 'home'])->assertCreated()->json();
        $this->assertSame([], $page['tree']['blocks']);
        $this->assertTrue($page['has_unpublished_changes']);

        $tree = $this->heroTree('Welcome');
        $saved = $this->putJson(self::API . "/editor/pages/{$page['id']}/tree", ['tree' => $tree, 'expected_version' => $page['tree_version']])
            ->assertOk()->json();
        $this->assertSame($page['tree_version'] + 1, $saved['tree_version']);

        $this->putJson(self::API . "/editor/pages/{$page['id']}/tree", ['tree' => $tree, 'expected_version' => $page['tree_version']])
            ->assertStatus(409)->assertJson(['current_version' => $saved['tree_version']]);

        $invalid = $tree;
        $invalid['blocks'][0]['widgets'][0]['config']['title'] = str_repeat('x', 200);
        $this->putJson(self::API . "/editor/pages/{$page['id']}/tree", ['tree' => $invalid, 'expected_version' => $saved['tree_version']])
            ->assertUnprocessable()->assertJsonPath('errors.0.path', '/blocks/0/widgets/0/config/title');

        $template = $this->postJson(self::API . '/editor/templates', ['name' => 'Landing', 'slug' => 'landing', 'view_path' => 'lpb::shell'])->assertCreated()->json();
        $updated = $this->patchJson(self::API . "/editor/pages/{$page['id']}", [
            'title' => 'Welcome home',
            'seo' => ['title' => 'Home', 'description' => 'Hello'],
            'metatags' => [['name' => 'robots', 'content' => 'index']],
            'template_id' => (string) $template['id'],
        ])->assertOk()->json();
        $this->assertSame('Welcome home', $updated['title']);
        $this->assertSame(['id' => (string) $template['id'], 'slug' => 'landing'], $updated['tree']['template']);
        $this->assertSame('Hello', $updated['tree']['page']['seo']['description']);
        $this->patchJson(self::API . "/editor/pages/{$page['id']}", ['template_id' => Ulid::generate()])->assertUnprocessable();

        $published = $this->postJson(self::API . "/editor/pages/{$page['id']}/publish")->assertOk()->json();
        $this->assertSame('published', $published['status']);
        $this->assertFalse($published['has_unpublished_changes']);

        $list = $this->getJson(self::API . '/editor/pages')->assertOk()->json('data');
        $this->assertSame(['home'], array_column($list, 'slug'));
        $this->getJson(self::API . '/editor/pages/' . Ulid::generate())->assertNotFound();
    }

    /**
     * Test revisions, rollback, restore, unpublish, archive and unarchive over HTTP
     */
    public function test_editor_publishing_flow(): void
    {
        $this->editor();
        $first = $this->publishedThroughApi('home', 'First');
        $tree = $this->getJson(self::API . "/editor/pages/{$first['id']}")->json('tree');
        $tree['blocks'][0]['widgets'][0]['config']['title'] = 'Second';
        $this->putJson(self::API . "/editor/pages/{$first['id']}/tree", ['tree' => $tree, 'expected_version' => $tree['tree_version']])->assertOk();
        $second = $this->postJson(self::API . "/editor/pages/{$first['id']}/publish")->assertOk()->json();

        $revisions = $this->getJson(self::API . "/editor/pages/{$first['id']}/revisions")->assertOk()->json('data');
        $this->assertSame([$second['revision_id'], $first['revision_id']], array_column($revisions, 'id'));

        $this->postJson(self::API . "/editor/pages/{$first['id']}/rollback/{$first['revision_id']}")->assertOk();
        $this->getJson(self::API . '/pages/home')->assertOk()->assertJsonPath('revision_id', $first['revision_id']);

        $version = $this->getJson(self::API . "/editor/pages/{$first['id']}")->json('tree_version');
        $restored = $this->postJson(self::API . "/editor/pages/{$first['id']}/restore/{$first['revision_id']}", ['expected_version' => $version])->assertOk()->json();
        $this->assertSame('First', $restored['blocks'][0]['widgets'][0]['config']['title']);
        $this->postJson(self::API . "/editor/pages/{$first['id']}/restore/" . Ulid::generate(), ['expected_version' => $restored['tree_version']])->assertNotFound();

        $this->postJson(self::API . "/editor/pages/{$first['id']}/unpublish")->assertOk()->assertJsonPath('status', 'draft');
        $this->getJson(self::API . '/pages/home')->assertNotFound();
        $this->postJson(self::API . "/editor/pages/{$first['id']}/unpublish")->assertStatus(409);
        $this->postJson(self::API . "/editor/pages/{$first['id']}/archive")->assertOk()->assertJsonPath('status', 'archived');
        $this->postJson(self::API . "/editor/pages/{$first['id']}/publish")->assertStatus(409);
        $this->postJson(self::API . "/editor/pages/{$first['id']}/unarchive")->assertOk()->assertJsonPath('status', 'draft');
        $this->deleteJson(self::API . "/editor/pages/{$first['id']}")->assertNoContent();
    }

    /**
     * Test the delivery API with ETags, and signed previews
     */
    public function test_delivery_api_and_preview(): void
    {
        $this->editor();
        $this->getJson(self::API . '/pages/home')->assertNotFound();
        $page = $this->publishedThroughApi('home', 'Welcome');

        $response = $this->getJson(self::API . '/pages/home')->assertOk()
            ->assertJsonPath('format_version', 1)
            ->assertJsonPath('blocks.0.widgets.0.config.title', 'Welcome');
        $etag = $response->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $this->getJson(self::API . '/pages/home', ['If-None-Match' => $etag])->assertStatus(304);

        $tree = $this->getJson(self::API . "/editor/pages/{$page['id']}")->json('tree');
        $tree['blocks'][0]['widgets'][0]['config']['title'] = 'Draft';
        $this->putJson(self::API . "/editor/pages/{$page['id']}/tree", ['tree' => $tree, 'expected_version' => $tree['tree_version']])->assertOk();

        $url = $this->postJson(self::API . "/editor/pages/{$page['id']}/preview-token")->assertOk()->json('url');
        $this->getJson($url)->assertOk()->assertJsonPath('blocks.0.widgets.0.config.title', 'Draft')->assertJsonPath('revision_id', null);
        $this->getJson($url . 'x')->assertForbidden();
        $this->getJson(self::API . '/pages/home')->assertJsonPath('blocks.0.widgets.0.config.title', 'Welcome');
    }

    /**
     * Test menus are delivered nested by location
     */
    public function test_menu_delivery(): void
    {
        $menus = app(MenuRepositoryInterface::class);
        $menu = $menus->create(['name' => 'Main', 'slug' => 'main', 'location' => 'header']);
        $about = $menus->addItem($menu['id'], ['label' => 'About', 'url' => '/about', 'order' => 1]);
        $menus->addItem($menu['id'], ['label' => 'Home', 'url' => '/', 'order' => 0]);
        $menus->addItem($menu['id'], ['label' => 'Team', 'url' => '/about/team', 'parent_id' => $about['id']]);

        $items = $this->getJson(self::API . '/menus/header')->assertOk()->assertJsonPath('slug', 'main')->json('items');

        $this->assertSame(['Home', 'About'], array_column($items, 'label'));
        $this->assertSame(['Team'], array_column($items[1]['children'], 'label'));
        $this->getJson(self::API . '/menus/footer')->assertNotFound();
    }

    /**
     * Test template, menu and media CRUD through the editor API
     */
    public function test_editor_crud(): void
    {
        $this->editor();

        $template = $this->postJson(self::API . '/editor/templates', ['name' => 'Blog', 'slug' => 'blog', 'view_path' => 'lpb::shell'])->assertCreated()->json();
        $this->patchJson(self::API . "/editor/templates/{$template['id']}", ['name' => 'Blog post'])->assertOk()->assertJsonPath('name', 'Blog post');
        $this->postJson(self::API . "/editor/templates/{$template['id']}/default")->assertOk()->assertJsonPath('is_default', true);
        $this->postJson(self::API . '/editor/templates', ['name' => 'Bad'])->assertUnprocessable();
        $this->deleteJson(self::API . "/editor/templates/{$template['id']}")->assertNoContent();

        $menu = $this->postJson(self::API . '/editor/menus', ['name' => 'Main', 'slug' => 'main', 'location' => 'header'])->assertCreated()->json();
        $first = $this->postJson(self::API . "/editor/menus/{$menu['id']}/items", ['label' => 'Home', 'url' => '/'])->assertCreated()->json();
        $second = $this->postJson(self::API . "/editor/menus/{$menu['id']}/items", ['label' => 'About', 'url' => '/about'])->assertCreated()->json();
        $this->putJson(self::API . "/editor/menus/{$menu['id']}/items/order", ['items' => [$second['id'], $first['id']]])->assertOk();
        $this->patchJson(self::API . "/editor/menus/{$menu['id']}/items/{$first['id']}", ['label' => 'Start'])->assertOk()->assertJsonPath('label', 'Start');
        $this->assertSame(['About', 'Start'], array_column($this->getJson(self::API . "/editor/menus/{$menu['id']}")->json('items'), 'label'));
        $this->postJson(self::API . "/editor/menus/{$menu['id']}/items", ['label' => 'x', 'url' => '/x', 'target' => 'popup'])->assertUnprocessable();
        $this->deleteJson(self::API . "/editor/menus/{$menu['id']}/items/{$first['id']}")->assertNoContent();

        $media = $this->postJson(self::API . '/editor/media', ['disk' => 'public', 'path' => 'a.png', 'filename' => 'a.png', 'mime_type' => 'image/png', 'size' => 3])->assertCreated()->json();
        $this->getJson(self::API . '/editor/media?mime_type=image/*')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson(self::API . "/editor/media/{$media['id']}", ['meta' => ['alt' => 'A']])->assertOk()->assertJsonPath('meta.alt', 'A');
        $this->postJson(self::API . '/editor/media', ['disk' => 'public'])->assertUnprocessable();
        $this->deleteJson(self::API . "/editor/media/{$media['id']}")->assertNoContent();
    }

    /**
     * Test the view flow renders the template's shell with the server-side head and the payload
     */
    public function test_view_flow(): void
    {
        View::addLocation(__DIR__ . '/../../Fixtures/views');
        $this->editor();
        $page = $this->publishedThroughApi('home', 'Welcome <b>');
        $pages = app(PageRepositoryInterface::class);
        $pages->setSeo($page['id'], \Andmarruda\Lpb\ValueObjects\SEO::fromArray(['title' => 'Home & more', 'description' => 'Hello', 'canonical' => 'https://example.com/']));
        $pages->setMetatag($page['id'], 'google-site-verification', 'abc');
        $this->postJson(self::API . "/editor/pages/{$page['id']}/publish")->assertOk();

        $html = Lpb::view('home')->getContent();
        $this->assertStringContainsString('<title>Home &amp; more</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Hello">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://example.com/">', $html);
        $this->assertStringContainsString('<meta name="google-site-verification" content="abc">', $html);
        $this->assertStringContainsString('<div id="lpb-app"></div>', $html);
        $this->assertMatchesRegularExpression('#<script type="application/json" id="lpb-page">(.+)</script>#', $html);
        preg_match('#<script type="application/json" id="lpb-page">(.+)</script>#', $html, $match);
        $this->assertStringNotContainsString('<b>', $match[1]);
        $this->assertSame('Welcome <b>', json_decode($match[1], true)['blocks'][0]['widgets'][0]['config']['title']);

        $template = app(TemplateRepositoryInterface::class)->create(['name' => 'Custom', 'slug' => 'custom', 'view_path' => 'custom-shell']);
        app(TemplateRepositoryInterface::class)->attachToPage($template['id'], $page['id']);
        $this->postJson(self::API . "/editor/pages/{$page['id']}/publish")->assertOk();
        $this->assertStringContainsString('class="custom-shell"', Lpb::view('home')->getContent());

        $this->expectException(NotFoundHttpException::class);
        Lpb::view('missing');
    }

    /**
     * Test the Inertia flow uses the template's shell as root view and the payload as the page prop
     */
    public function test_inertia_flow(): void
    {
        View::addLocation(__DIR__ . '/../../Fixtures/views');
        $this->editor();
        $page = $this->publishedThroughApi('home', 'Welcome');
        $template = app(TemplateRepositoryInterface::class)->create(['name' => 'App', 'slug' => 'app', 'view_path' => 'inertia-shell']);
        app(TemplateRepositoryInterface::class)->attachToPage($template['id'], $page['id']);
        $this->postJson(self::API . "/editor/pages/{$page['id']}/publish")->assertOk();

        $html = Lpb::inertia('home')->getContent();

        $this->assertStringContainsString('class="inertia-shell"', $html);
        $this->assertStringContainsString('<title>Home</title>', $html);
        // Inertia 1 and 2 put the page in a data-page attribute, Inertia 3 in a script
        preg_match('#<script data-page="[^"]*" type="application/json">(.+?)</script>#s', $html, $script)
            || preg_match('#data-page="([^"]+)"#', $html, $attribute);
        $inertia = json_decode(isset($script[1]) ? $script[1] : html_entity_decode($attribute[1] ?? ''), true);
        $this->assertSame('Lpb/Page', $inertia['component']);
        $this->assertSame('Welcome', $inertia['props']['page']['blocks'][0]['widgets'][0]['config']['title']);
    }
}
