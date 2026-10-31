<?php
namespace Andmarruda\Lpb\ValueObjects;

use Andmarruda\Lpb\Exceptions\InvalidValueException;

/**
 * Delivery cache keys (ADR 09):
 *
 * - content: 'lpb:content:{revisionId}:{designVersion}', immutable, the
 *   canonical payload of a revision under a design version;
 * - pointer: 'lpb:pointer:{site}:{locale}:{slug}', short-lived, the
 *   revision a slug serves.
 *
 * site and locale are 'default' until multi-site and localisation exist.
 */
final readonly class CacheKey
{
    public const DEFAULT = 'default';

    private const PREFIX_PATTERN = '/^[a-z0-9_-]+$/';

    /**
     * @param string[] $parts
     */
    private function __construct(private array $parts)
    {
        foreach ($parts as $part) {
            if ($part === '' || preg_match('/[\s:]/', $part)) {
                throw new InvalidValueException("Invalid cache key part '{$part}'.");
            }
        }
    }

    public static function content(string $revisionId, int $designVersion, string $prefix = 'lpb'): self
    {
        if ($designVersion < 1) {
            throw new InvalidValueException('The design version must be 1 or higher.');
        }
        return new self([self::prefix($prefix), 'content', $revisionId, (string) $designVersion]);
    }

    public static function pointer(string $slug, string $locale = self::DEFAULT, string $site = self::DEFAULT, string $prefix = 'lpb'): self
    {
        return new self([self::prefix($prefix), 'pointer', $site, $locale, $slug]);
    }

    public function toString(): string
    {
        return implode(':', $this->parts);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private static function prefix(string $prefix): string
    {
        if (!preg_match(self::PREFIX_PATTERN, $prefix)) {
            throw new InvalidValueException("Invalid cache key prefix '{$prefix}'.");
        }
        return $prefix;
    }
}
