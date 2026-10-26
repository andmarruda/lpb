<?php

namespace Andmarruda\Lpb\Tests\Unit\ValueObjects;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\ValueObjects\RoutePath;

/**
 * Unit tests for the RoutePath value object
 */
class RoutePathTest extends TestCase
{
    /**
     * Test valid paths and their segments
     */
    public function test_accepts_valid_path(): void
    {
        $path = RoutePath::from('/blog/2025/hello');

        $this->assertSame('/blog/2025/hello', $path->value());
        $this->assertSame(['blog', '2025', 'hello'], $path->segments());
    }

    /**
     * Test the root path has no segments
     */
    public function test_root_path(): void
    {
        $this->assertSame('/', RoutePath::root()->value());
        $this->assertSame([], RoutePath::from('/')->segments());
    }

    /**
     * Test trailing slash is removed
     */
    public function test_trailing_slash_is_removed(): void
    {
        $this->assertSame('/blog', (string) RoutePath::from('/blog/'));
        $this->assertTrue(RoutePath::from('/blog/')->equals(RoutePath::from('/blog')));
    }

    /**
     * Test invalid paths are rejected
     */
    #[DataProvider('invalidPaths')]
    public function test_rejects_invalid_path(string $value): void
    {
        $this->expectException(InvalidValueException::class);

        RoutePath::from($value);
    }

    public static function invalidPaths(): array
    {
        return [[''], ['blog'], ['//blog'], ['/blog//post'], ['/blog post'], ['/blog?page=2'], ['/blog#top']];
    }

    /**
     * Test append returns a new path
     */
    public function test_append_returns_new_path(): void
    {
        $blog = RoutePath::from('/blog');

        $this->assertSame('/blog/post', $blog->append('post')->value());
        $this->assertSame('/post', RoutePath::root()->append('post')->value());
        $this->assertSame('/blog', $blog->value());
    }

    /**
     * Test append rejects invalid segments
     */
    #[DataProvider('invalidSegments')]
    public function test_append_rejects_invalid_segment(string $segment): void
    {
        $this->expectException(InvalidValueException::class);

        RoutePath::from('/blog')->append($segment);
    }

    public static function invalidSegments(): array
    {
        return [[''], ['a/b']];
    }
}
