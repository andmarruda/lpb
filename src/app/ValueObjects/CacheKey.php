<?php
namespace Andmarruda\Lpb\ValueObjects;

use Andmarruda\Lpb\Exceptions\InvalidValueException;

/**
 * Cache key for an entity, e.g. 'lpb:page:sql:home'.
 */
final readonly class CacheKey
{
    private const DRIVERS = ['sql', 'mongo'];
    private const PART_PATTERN = '/^[a-z0-9_-]+$/';

    private string $driver;
    private string $identifier;

    /**
     * @param string $type Entity type, e.g. page, template, menu
     * @param string $driver 'sql' or 'mongo' ('mongodb' is accepted as an alias)
     * @param string|int $identifier Entity id or slug
     * @param string $prefix Key namespace
     */
    private function __construct(
        private string $type,
        string $driver,
        string|int $identifier,
        private string $prefix,
    ) {
        $driver = $driver === 'mongodb' ? 'mongo' : $driver;

        if (!in_array($driver, self::DRIVERS, true)) {
            throw new InvalidValueException("Invalid cache driver '{$driver}'. Use 'sql' or 'mongo'.");
        }

        foreach (['type' => $type, 'prefix' => $prefix] as $name => $part) {
            if (!preg_match(self::PART_PATTERN, $part)) {
                throw new InvalidValueException("Invalid cache key {$name} '{$part}'.");
            }
        }

        $identifier = (string) $identifier;
        if ($identifier === '' || preg_match('/[\s:]/', $identifier)) {
            throw new InvalidValueException("Invalid cache key identifier '{$identifier}'.");
        }

        $this->driver = $driver;
        $this->identifier = $identifier;
    }

    /**
     * @param string $type
     * @param string $driver
     * @param string|int $identifier
     * @param string $prefix
     * @return self
     */
    public static function for(string $type, string $driver, string|int $identifier, string $prefix = 'lpb'): self
    {
        return new self($type, $driver, $identifier, $prefix);
    }

    public function toString(): string
    {
        return implode(':', [$this->prefix, $this->type, $this->driver, $this->identifier]);
    }

    /**
     * Tags for invalidating everything, everything of this type, or this entity.
     *
     * @return string[]
     */
    public function tagged(): array
    {
        return [
            $this->prefix,
            "{$this->prefix}:{$this->type}",
            "{$this->prefix}:{$this->type}:{$this->identifier}",
        ];
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
