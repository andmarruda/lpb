<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Andmarruda\Lpb\Contracts\MediaRepositoryInterface;
use Andmarruda\Lpb\Repositories\Sql\MediaRepository;
use Andmarruda\Lpb\Models\Sql\Media;
use Andmarruda\Lpb\Exceptions\InvalidValueException;

/**
 * SQL MediaRepository integration tests
 */
class MediaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected MediaRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MediaRepository(new Media());
    }

    protected function createMedia(array $overrides = []): array
    {
        return $this->repository->create(array_merge([
            'disk' => 'public',
            'path' => 'images/hero.png',
            'filename' => 'hero.png',
            'mime_type' => 'image/png',
            'size' => 2048,
        ], $overrides));
    }

    /**
     * Test the container binds the SQL media repository
     */
    public function test_container_binds_media_repository(): void
    {
        $this->assertInstanceOf(MediaRepository::class, app(MediaRepositoryInterface::class));
    }

    /**
     * Test create stores meta and findByPath reads it back
     */
    public function test_create_and_find_by_path(): void
    {
        $meta = ['width' => 1200, 'height' => 630, 'alt' => 'Hero', 'focal_point' => [0.5, 0.25]];

        $media = $this->createMedia(['mime_type' => 'Image/PNG', 'meta' => $meta, 'collection' => 'banners']);

        $this->assertSame('image/png', $media['mime_type']);
        $found = $this->repository->findByPath('public', 'images/hero.png');
        $this->assertSame($media['id'], $found['id']);
        $this->assertSame($meta, $found['meta']);
        $this->assertSame(2048, $found['size']);
        $this->assertNull($this->repository->findByPath('s3', 'images/hero.png'));
    }

    /**
     * Test invalid media input is rejected
     */
    public function test_rejects_invalid_media(): void
    {
        $invalid = [
            ['disk' => ''],
            ['mime_type' => 'png'],
            ['size' => -1],
            ['size' => '10'],
            ['meta' => 'alt text'],
        ];

        foreach ($invalid as $overrides) {
            try {
                $this->createMedia($overrides);
                $this->fail('Invalid media was accepted: ' . json_encode($overrides));
            } catch (InvalidValueException) {
            }
        }

        $media = $this->createMedia();
        $this->expectException(InvalidValueException::class);
        $this->repository->update($media['id'], ['mime_type' => 'image']);
    }

    /**
     * Test update changes known fields and ignores others
     */
    public function test_update_media(): void
    {
        $media = $this->createMedia();

        $updated = $this->repository->update($media['id'], ['collection' => 'gallery', 'meta' => ['alt' => 'New'], 'unknown' => 'x']);

        $this->assertSame('gallery', $updated['collection']);
        $this->assertSame(['alt' => 'New'], $updated['meta']);
        $this->assertArrayNotHasKey('unknown', $updated);
    }

    /**
     * Test collection queries are ordered by filename
     */
    public function test_get_by_collection(): void
    {
        $this->createMedia(['path' => 'b.png', 'filename' => 'b.png', 'collection' => 'gallery']);
        $this->createMedia(['path' => 'a.png', 'filename' => 'a.png', 'collection' => 'gallery']);
        $this->createMedia(['path' => 'c.png', 'filename' => 'c.png', 'collection' => 'banners']);

        $this->assertSame(['a.png', 'b.png'], array_column($this->repository->getByCollection('gallery'), 'filename'));
        $this->assertSame([], $this->repository->getByCollection('missing'));
    }

    /**
     * Test exact and wildcard mime type queries
     */
    public function test_get_by_mime_type(): void
    {
        $this->createMedia(['path' => 'a.png', 'filename' => 'a.png', 'mime_type' => 'image/png']);
        $this->createMedia(['path' => 'b.jpg', 'filename' => 'b.jpg', 'mime_type' => 'image/jpeg']);
        $this->createMedia(['path' => 'c.pdf', 'filename' => 'c.pdf', 'mime_type' => 'application/pdf']);
        $this->createMedia(['path' => 'd.svg', 'filename' => 'd.svg', 'mime_type' => 'image/svg+xml']);

        $this->assertSame(['a.png'], array_column($this->repository->getByMimeType('image/png'), 'filename'));
        $this->assertSame(['a.png', 'b.jpg', 'd.svg'], array_column($this->repository->getByMimeType('IMAGE/*'), 'filename'));
        $this->assertSame(['d.svg'], array_column($this->repository->getByMimeType('image/svg+xml'), 'filename'));
        $this->assertSame([], $this->repository->getByMimeType('video/*'));

        $this->expectException(InvalidValueException::class);
        $this->repository->getByMimeType('*');
    }

    /**
     * Test search is a case-insensitive literal substring match on filename or path
     */
    public function test_search(): void
    {
        $this->createMedia(['path' => 'uploads/Summer_Sale.png', 'filename' => 'Summer_Sale.png']);
        $this->createMedia(['path' => 'campaigns/2025/banner.jpg', 'filename' => 'banner.jpg']);
        $this->createMedia(['path' => 'misc/summerXsale.png', 'filename' => 'summerXsale.png']);
        $this->createMedia(['path' => 'misc/100%.png', 'filename' => '100%.png']);

        $this->assertSame(['Summer_Sale.png', 'summerXsale.png'], array_column($this->repository->search('SUMMER'), 'filename'));
        $this->assertSame(['banner.jpg'], array_column($this->repository->search('campaigns/2025'), 'filename'));
        $this->assertSame(['Summer_Sale.png'], array_column($this->repository->search('summer_sale'), 'filename'));
        $this->assertSame(['100%.png'], array_column($this->repository->search('%'), 'filename'));
        $this->assertSame([], $this->repository->search('.*'));
        $this->assertSame([], $this->repository->search('   '));
    }
}
