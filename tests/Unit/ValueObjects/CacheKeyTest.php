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
     * Test the key string is composed of prefix, type, driver and identifier
     */
    public function test_to_string(): void
    {
        $key = CacheKey::for('page', 'sql', 'home');

        $this->assertSame('lpb:page:sql:home', $key->toString());
        $this->assertSame('lpb:page:sql:home', (string) $key);
    }

    /**
     * Test mongodb is normalised to mongo and integer ids are accepted
     */
    public function test_mongodb_alias_and_integer_identifier(): void
    {
        $this->assertSame('lpb:menu:mongo:42', CacheKey::for('menu', 'mongodb', 42)->toString());
    }

    /**
     * Test custom prefix
     */
    public function test_custom_prefix(): void
    {
        $this->assertSame('site:template:sql:blog', CacheKey::for('template', 'sql', 'blog', 'site')->toString());
    }

    /**
     * Test tags cover all entities, the entity type and the single entity
     */
    public function test_tagged(): void
    {
        $this->assertSame(
            ['lpb', 'lpb:page', 'lpb:page:home'],
            CacheKey::for('page', 'sql', 'home')->tagged()
        );
    }

    /**
     * Test invalid parts are rejected
     */
    #[DataProvider('invalidParts')]
    public function test_rejects_invalid_parts(string $type, string $driver, string|int $identifier, string $prefix): void
    {
        $this->expectException(InvalidValueException::class);

        CacheKey::for($type, $driver, $identifier, $prefix);
    }

    public static function invalidParts(): array
    {
        return [
            'unknown driver' => ['page', 'redis', 'home', 'lpb'],
            'empty type' => ['', 'sql', 'home', 'lpb'],
            'type with colon' => ['pa:ge', 'sql', 'home', 'lpb'],
            'empty identifier' => ['page', 'sql', '', 'lpb'],
            'identifier with colon' => ['page', 'sql', 'a:b', 'lpb'],
            'identifier with space' => ['page', 'sql', 'a b', 'lpb'],
            'empty prefix' => ['page', 'sql', 'home', ''],
        ];
    }
}
