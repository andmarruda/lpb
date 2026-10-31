<?php

namespace Andmarruda\Lpb\Tests\Unit\ValueObjects;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\ValueObjects\CacheKey;

/**
 * Unit tests for the CacheKey value object
 */
class CacheKeyTest extends TestCase
{
    /**
     * Test content keys are made of the revision and design version
     */
    public function test_content_key(): void
    {
        $key = CacheKey::content('01JB0000000000000000000000', 7);

        $this->assertSame('lpb:content:01JB0000000000000000000000:7', $key->toString());
        $this->assertSame('lpb:content:01JB0000000000000000000000:7', (string) $key);
    }

    /**
     * Test pointer keys carry site and locale, default until multi-site and localisation
     */
    public function test_pointer_key(): void
    {
        $this->assertSame('lpb:pointer:default:default:home', CacheKey::pointer('home')->toString());
        $this->assertSame('lpb:pointer:shop:pt-br:about-us', CacheKey::pointer('about-us', 'pt-br', 'shop')->toString());
        $this->assertSame('site:pointer:default:default:home', CacheKey::pointer('home', prefix: 'site')->toString());
    }

    /**
     * Test invalid parts are rejected
     */
    #[DataProvider('invalidKeys')]
    public function test_rejects_invalid_parts(callable $make): void
    {
        $this->expectException(InvalidValueException::class);

        $make();
    }

    public static function invalidKeys(): array
    {
        return [
            'empty revision' => [fn () => CacheKey::content('', 1)],
            'design version zero' => [fn () => CacheKey::content('01JB', 0)],
            'colon in slug' => [fn () => CacheKey::pointer('a:b')],
            'space in locale' => [fn () => CacheKey::pointer('home', 'pt br')],
            'bad prefix' => [fn () => CacheKey::pointer('home', prefix: 'Bad Prefix')],
        ];
    }
}
