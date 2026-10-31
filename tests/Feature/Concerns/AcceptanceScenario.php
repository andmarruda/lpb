<?php

namespace Andmarruda\Lpb\Tests\Feature\Concerns;

use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Andmarruda\Lpb\Contracts\PublishingServiceInterface;
use Andmarruda\Lpb\Contracts\TemplateRepositoryInterface;
use Andmarruda\Lpb\Exceptions\InvalidStateTransitionException;
use Andmarruda\Lpb\Support\Ulid;
use Andmarruda\Lpb\ValueObjects\SEO;

/**
 * Acceptance scenario of the page lifecycle, run identically on every driver.
 *
 * Repositories and services are resolved from the container, so the
 * scenario only touches the contracts; the using class prepares the
 * database and registers the fixture widgets.
 */
trait AcceptanceScenario
{
    use ComparesStoredArrays;

    /**
     * Test the full page lifecycle through the contracts
     */
    public function test_page_lifecycle(): void
    {
        $templates = app(TemplateRepositoryInterface::class);
        $pages = app(PageRepositoryInterface::class);
        $trees = app(PageTreeRepositoryInterface::class);
        $publishing = app(PublishingServiceInterface::class);

        // 1. Template: active and default
        $template = $templates->create(['name' => 'Landing', 'slug' => 'landing', 'view_path' => 'lpb::shell']);
        $this->assertTrue($templates->setDefault($template['id']));
        $this->assertSame($template['id'], $templates->getDefault()['id']);

        // 2. Draft page with the template, SEO and a supplementary metatag
        $page = $pages->create(['title' => 'Home', 'slug' => 'home']);
        $this->assertSame('draft', $page['status']);
        $this->assertTrue($templates->attachToPage($template['id'], $page['id']));
        $seo = new SEO(title: 'Home', description: 'Welcome home', canonical: 'https://example.com/');
        $pages->setSeo($page['id'], $seo);
        $pages->setMetatag($page['id'], 'google-site-verification', 'abc123');

        // 3. A nested tree saved atomically
        $heroConfig = ['title' => 'Welcome', 'buttons' => [['label' => 'Start', 'url' => '/start']]];
        $blocks = [[
            'id' => Ulid::generate(), 'type' => 'flex',
            'styles' => [['breakpoint' => null, 'state' => null, 'props' => ['flex-direction' => 'column', 'gap' => '24px']]],
            'widgets' => [['id' => Ulid::generate(), 'widget' => 'hero', 'schema_version' => 1, 'config' => $heroConfig, 'styles' => []]],
            'children' => [[
                'id' => Ulid::generate(), 'type' => 'grid', 'styles' => [], 'children' => [],
                'widgets' => [['id' => Ulid::generate(), 'widget' => 'text', 'schema_version' => 1, 'config' => ['body' => '<p>Hello</p>'], 'styles' => []]],
            ]],
        ]];
        $working = $trees->getWorkingTree($page['id']);
        $working = $trees->saveTree($page['id'], ['blocks' => $blocks] + $working, $working['tree_version']);
        $this->assertSameIgnoringKeyOrder($blocks, $working['blocks']);
        $this->assertSame(['id' => $template['id'], 'slug' => 'landing'], $working['template']);
        $this->assertSameIgnoringKeyOrder($seo->toArray(), $working['page']['seo']);
        $this->assertSame([['name' => 'google-site-verification', 'content' => 'abc123']], $working['page']['metatags']);

        // 4. Publish: a revision of the working tree is served
        $first = $publishing->publish($page['id'], 'editor-1');
        $this->assertSame('published', $pages->find($page['id'])['status']);
        $this->assertSameIgnoringKeyOrder($blocks, $first['payload']['blocks']);
        $this->assertFalse($publishing->hasUnpublishedChanges($page['id']));

        // 5. Editing the draft does not change what is served
        $edited = $working;
        $edited['blocks'][0]['widgets'][0]['config']['title'] = 'Welcome back';
        $working = $trees->saveTree($page['id'], $edited, $working['tree_version']);
        $this->assertTrue($publishing->hasUnpublishedChanges($page['id']));
        $this->assertSame((string) $first['id'], $pages->find($page['id'])['published_revision_id']);

        // 6. Publishing five times keeps the newest three revisions
        $ids = [(string) $first['id']];
        foreach (range(2, 5) as $n) {
            $pages->update($page['id'], ['title' => "Home {$n}"]);
            $ids[] = (string) $publishing->publish($page['id'])['id'];
        }
        $this->assertSame([$ids[4], $ids[3], $ids[2]], array_column($publishing->revisions($page['id']), 'id'));

        // 7. Rollback serves an older revision and leaves the working tree alone
        $before = $trees->getWorkingTree($page['id']);
        $publishing->rollback($page['id'], $ids[2], 'editor-2');
        $this->assertSame($ids[2], $pages->find($page['id'])['published_revision_id']);
        $this->assertSame($before, $trees->getWorkingTree($page['id']));

        // 8. Restore a revision into the working tree
        $restored = $publishing->restoreToDraft($page['id'], $ids[2], $before['tree_version']);
        $this->assertSame('Welcome back', $restored['blocks'][0]['widgets'][0]['config']['title']);
        $this->assertSame($before['tree_version'] + 1, $restored['tree_version']);

        // 9. Unpublish, archive, unarchive and publish again
        $publishing->unpublish($page['id']);
        $publishing->archive($page['id']);
        try {
            $publishing->publish($page['id']);
            $this->fail('An archived page was published directly');
        } catch (InvalidStateTransitionException) {
        }
        $publishing->unarchive($page['id']);
        $publishing->publish($page['id']);
        $this->assertSame('published', $pages->find($page['id'])['status']);
        $this->assertCount(3, $publishing->revisions($page['id']));
    }
}
