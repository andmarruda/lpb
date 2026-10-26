<?php

namespace Andmarruda\Lpb\Tests\Unit\ValueObjects;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\ValueObjects\Slug;

/**
 * Unit tests for the Slug value object
 */
class SlugTest extends TestCase
{
    /**
     * Test valid slugs are accepted unchanged
     */
    #[DataProvider('validSlugs')]
    public function test_accepts_valid_slug(string $value): void
    {
        $slug = Slug::from($value);

        $this->assertSame($value, $slug->value());
        $this->assertSame($value, (string) $slug);
    }

    public static function validSlugs(): array
    {
        return [['home'], ['about-us'], ['page-2'], ['2025']];
    }

    /**
     * Test invalid slugs are rejected
     */
    #[DataProvider('invalidSlugs')]
    public function test_rejects_invalid_slug(string $value): void
    {
        $this->expectException(InvalidValueException::class);

        Slug::from($value);
    }

    public static function invalidSlugs(): array
    {
        return [[''], ['About'], ['about us'], ['-about'], ['about-'], ['about--us'], ['about_us'], ['café']];
    }

    /**
     * Test fromTitle slugifies free text
     */
    public function test_from_title_slugifies_text(): void
    {
        $this->assertSame('hello-world', Slug::fromTitle('  Hello, World! ')->value());
    }

    /**
     * Test fromTitle rejects text without slug characters
     */
    public function test_from_title_rejects_text_without_slug_characters(): void
    {
        $this->expectException(InvalidValueException::class);

        Slug::fromTitle('!!!');
    }

    /**
     * Test equality compares values
     */
    public function test_equals_compares_values(): void
    {
        $this->assertTrue(Slug::from('home')->equals(Slug::from('home')));
        $this->assertFalse(Slug::from('home')->equals(Slug::from('about')));
    }
}
