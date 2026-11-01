<?php

namespace Andmarruda\Lpb\Tests\Feature\Concerns;

use Andmarruda\Lpb\Contracts\MediaRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Andmarruda\Lpb\Contracts\PublishingServiceInterface;
use Andmarruda\Lpb\Delivery\DesignVersion;
use Andmarruda\Lpb\Delivery\PageDeliveryService;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\Media\MediaStorage;
use Andmarruda\Lpb\Support\Ulid;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Media uploads to a filesystem disk (local, S3, R2, ... all go through the
 * same Laravel disk API) or to the database, replace and delete, shared by
 * every driver. The using class prepares the database.
 */
trait MediaStorageScenario
{
    /** A 2x1 PNG. */
    private function png(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAYAAAD0In+KAAAAEUlEQVR4nGP8z8Dwn4GBgQEADwMBAP2jx2kAAAAASUVORK5CYII='));
    }

    /** A 1x3 GIF. */
    private function gif(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('other.gif', base64_decode('R0lGODlhAQADAIAAAP///wAAACH5BAAAAAAALAAAAAABAAMAAAICRF4AOw=='));
    }

    private function storage(): MediaStorage
    {
        return app(MediaStorage::class);
    }

    /**
     * Test an upload to a disk stores the file under a generated name and records it
     */
    public function test_upload_to_a_disk(): void
    {
        Storage::fake('s3');
        config(['laravel-page-builder.media.disk' => 's3']);

        $media = $this->storage()->upload($this->png('../../My photo.png'), ['collection' => 'hero', 'meta' => ['alt' => 'A photo', 'onload' => 'x']]);

        $this->assertSame('s3', $media['disk']);
        $this->assertMatchesRegularExpression('#^lpb/\d{4}/\d{2}/[0-9a-z]{26}\.png$#', $media['path']);
        $this->assertSame('My photo.png', basename($media['filename']));
        $this->assertStringNotContainsString('/', $media['filename']);
        $this->assertSame('image/png', $media['mime_type']);
        $this->assertSame(['width' => 2, 'height' => 1, 'alt' => 'A photo'], $media['meta']);
        $this->assertSame('hero', $media['collection']);
        Storage::disk('s3')->assertExists($media['path']);
        $this->assertSame(Storage::disk('s3')->url($media['path']), $this->storage()->url($media));
    }

    /**
     * Test types are detected from the contents and size and disks are limited
     */
    public function test_upload_rules(): void
    {
        Storage::fake('public');

        foreach ([
            'script' => UploadedFile::fake()->createWithContent('x.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'php' => UploadedFile::fake()->createWithContent('x.jpg', '<?php echo 1;'),
        ] as $name => $file) {
            try {
                $this->storage()->upload($file);
                $this->fail("A {$name} file was accepted");
            } catch (InvalidValueException) {
            }
        }

        config(['laravel-page-builder.media.max_size' => 0]);
        try {
            $this->storage()->upload($this->png());
            $this->fail('An oversized file was accepted');
        } catch (InvalidValueException) {
        }
        config(['laravel-page-builder.media.max_size' => 10240]);

        $this->expectException(InvalidValueException::class);
        $this->storage()->upload($this->png(), [], 'local');
    }

    /**
     * Test the database disk stores contents and serves them from the media route
     */
    public function test_database_disk(): void
    {
        config(['laravel-page-builder.media.disk' => 'database']);

        $media = $this->storage()->upload($this->png());
        $url = $this->storage()->url($media);

        $this->assertSame('database', $media['disk']);
        $this->assertStringEndsWith('/lpb/api/media/' . $media['id'] . '/' . $media['path'], $url);
        $response = $this->get($url)->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame((string) $this->png()->getContent(), $response->getContent());
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->get('/lpb/api/media/' . $media['id'] . '/other.png')->assertNotFound();
    }

    /**
     * Test replace keeps the id, swaps the file and its URL, and refreshes payloads
     */
    public function test_replace(): void
    {
        Storage::fake('public');
        $old = $this->storage()->upload($this->png(), ['meta' => ['alt' => 'Kept']]);
        $page = $this->publishedPageWithImage($old['id']);
        $design = app(DesignVersion::class)->current();
        $before = app(PageDeliveryService::class)->published($page)['blocks'][0]['widgets'][0]['config']['image'];

        $new = $this->storage()->replace($old['id'], $this->gif());

        $this->assertEquals($old['id'], $new['id']);
        $this->assertSame('image/gif', $new['mime_type']);
        $this->assertSame(['alt' => 'Kept', 'width' => 1, 'height' => 3], $new['meta']);
        Storage::disk('public')->assertMissing($old['path']);
        Storage::disk('public')->assertExists($new['path']);
        $this->assertSame($design + 1, app(DesignVersion::class)->current());

        $after = app(PageDeliveryService::class)->published($page)['blocks'][0]['widgets'][0]['config']['image'];
        $this->assertNotSame($before['url'], $after['url']);
        $this->assertSame($this->storage()->url($new), $after['url']);
    }

    /**
     * Test replacing a database file changes its URL and deleting removes the contents
     */
    public function test_replace_and_delete_on_the_database_disk(): void
    {
        config(['laravel-page-builder.media.disk' => 'database']);
        $old = $this->storage()->upload($this->png());
        $oldUrl = $this->storage()->url($old);

        $new = $this->storage()->replace($old['id'], $this->gif());

        $this->get($oldUrl)->assertNotFound();
        $this->get($this->storage()->url($new))->assertOk()->assertHeader('Content-Type', 'image/gif');

        $this->assertTrue($this->storage()->delete($new['id']));
        $this->assertNull(app(MediaRepositoryInterface::class)->find($new['id']));
        $this->get($this->storage()->url($new))->assertNotFound();
        $this->assertFalse($this->storage()->delete($new['id']));
    }

    /**
     * Test the editor upload and replace endpoints
     */
    public function test_editor_upload_endpoints(): void
    {
        Storage::fake('public');
        Storage::fake('r2');
        config(['laravel-page-builder.media.disks' => ['r2']]);
        Gate::define('lpb.edit', fn ($user = null) => $user !== null);
        $this->actingAs(new GenericUser(['id' => 1]));

        $uploaded = $this->post('/lpb/api/editor/media/upload', ['file' => $this->png(), 'alt' => 'Hi', 'collection' => 'blog'], ['Accept' => 'application/json'])->assertCreated()->json();
        $this->assertSame('public', $uploaded['disk']);
        $this->assertSame('Hi', $uploaded['meta']['alt']);
        $this->assertSame(Storage::disk('public')->url($uploaded['path']), $uploaded['url']);

        $this->post('/lpb/api/editor/media/upload', ['file' => $this->png(), 'disk' => 'r2'], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('disk', 'r2');
        $this->post('/lpb/api/editor/media/upload', ['file' => $this->png(), 'disk' => 'local'], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/lpb/api/editor/media/upload', ['file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable();

        $replaced = $this->post("/lpb/api/editor/media/{$uploaded['id']}/replace", ['file' => $this->gif()], ['Accept' => 'application/json'])->assertOk()->json();
        $this->assertSame('image/gif', $replaced['mime_type']);
        $this->assertSame((string) $uploaded['id'], (string) $replaced['id']);

        $this->deleteJson("/lpb/api/editor/media/{$uploaded['id']}")->assertNoContent();
        Storage::disk('public')->assertMissing($replaced['path']);
    }

    private function publishedPageWithImage(string|int $mediaId): string
    {
        $page = app(PageRepositoryInterface::class)->create(['title' => 'Media', 'slug' => 'media']);
        app(PageTreeRepositoryInterface::class)->saveTree($page['id'], ['blocks' => [[
            'id' => Ulid::generate(), 'type' => 'flex', 'styles' => [], 'children' => [],
            'widgets' => [['id' => Ulid::generate(), 'widget' => 'hero', 'schema_version' => 1, 'config' => ['image' => ['$media' => $mediaId]], 'styles' => []]],
        ]]], 0);
        app(PublishingServiceInterface::class)->publish($page['id']);
        return 'media';
    }
}
