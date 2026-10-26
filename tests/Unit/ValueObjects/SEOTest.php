<?php

namespace Andmarruda\Lpb\Tests\Unit\ValueObjects;

use PHPUnit\Framework\TestCase;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\ValueObjects\SEO;

/**
 * Unit tests for the SEO value object
 */
class SEOTest extends TestCase
{
    /**
     * Test construction from named arguments
     */
    public function test_named_arguments(): void
    {
        $seo = new SEO(title: 'Home', ogImage: 'https://example.com/og.png');

        $this->assertSame('Home', $seo->title);
        $this->assertSame('https://example.com/og.png', $seo->ogImage);
        $this->assertNull($seo->description);
    }

    /**
     * Test fromArray and toArray roundtrip with snake_case keys
     */
    public function test_array_roundtrip(): void
    {
        $data = [
            'title' => 'Home',
            'description' => 'Welcome',
            'og_title' => 'Home OG',
            'og_description' => 'Welcome OG',
            'og_image' => 'https://example.com/og.png',
            'canonical' => 'https://example.com/',
            'robots' => 'index,follow',
        ];

        $this->assertSame($data, SEO::fromArray($data)->toArray());
    }

    /**
     * Test fromArray rejects unknown keys
     */
    public function test_from_array_rejects_unknown_keys(): void
    {
        $this->expectException(InvalidValueException::class);

        SEO::fromArray(['title' => 'Home', 'keywords' => 'a,b']);
    }

    /**
     * Test empty strings become null and are omitted
     */
    public function test_empty_strings_are_null(): void
    {
        $seo = new SEO(title: '  ', description: 'Welcome');

        $this->assertNull($seo->title);
        $this->assertSame(['description' => 'Welcome'], $seo->toArray());
        $this->assertTrue((new SEO())->isEmpty());
    }

    /**
     * Test canonical and og_image must be absolute URLs
     */
    public function test_rejects_relative_urls(): void
    {
        $this->expectException(InvalidValueException::class);

        new SEO(canonical: '/home');
    }

    /**
     * Test og_image must be an absolute URL
     */
    public function test_rejects_invalid_og_image(): void
    {
        $this->expectException(InvalidValueException::class);

        new SEO(ogImage: 'og.png');
    }

    /**
     * Test toMetatags uses metatag names
     */
    public function test_to_metatags(): void
    {
        $seo = new SEO(title: 'Home', ogTitle: 'Home OG', robots: 'noindex');

        $this->assertSame(
            ['title' => 'Home', 'og:title' => 'Home OG', 'robots' => 'noindex'],
            $seo->toMetatags()
        );
    }

    /**
     * Test JSON roundtrip
     */
    public function test_json_roundtrip(): void
    {
        $seo = new SEO(title: 'Início', canonical: 'https://example.com/inicio');

        $json = $seo->toJsonString();

        $this->assertSame('{"title":"Início","canonical":"https://example.com/inicio"}', $json);
        $this->assertTrue(SEO::fromJsonString($json)->equals($seo));
        $this->assertSame('{}', (new SEO())->toJsonString());
    }

    /**
     * Test invalid JSON is rejected
     */
    public function test_from_json_rejects_invalid_json(): void
    {
        $this->expectException(InvalidValueException::class);

        SEO::fromJsonString('{not json');
    }

    /**
     * Test non-object JSON is rejected
     */
    public function test_from_json_rejects_non_object(): void
    {
        $this->expectException(InvalidValueException::class);

        SEO::fromJsonString('"title"');
    }
}
