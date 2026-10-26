<?php
namespace Andmarruda\Lpb\ValueObjects;

use Andmarruda\Lpb\Exceptions\InvalidValueException;

/**
 * URL path with a leading slash and no empty segments.
 *
 * A trailing slash is removed, so '/blog/' and '/blog' are the same path.
 */
final readonly class RoutePath
{
    private string $value;

    private function __construct(string $value)
    {
        if (!str_starts_with($value, '/')) {
            throw new InvalidValueException("Route path '{$value}' must start with '/'.");
        }

        if (str_contains($value, '//')) {
            throw new InvalidValueException("Route path '{$value}' must not contain '//'.");
        }

        if (preg_match('/[\s?#]/', $value)) {
            throw new InvalidValueException("Route path '{$value}' must not contain whitespace, '?' or '#'.");
        }

        $this->value = $value === '/' ? '/' : rtrim($value, '/');
    }

    /**
     * @param string $value
     * @return self
     */
    public static function from(string $value): self
    {
        return new self($value);
    }

    /**
     * The root path '/'.
     *
     * @return self
     */
    public static function root(): self
    {
        return new self('/');
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Path segments without slashes; the root path has none.
     *
     * @return string[]
     */
    public function segments(): array
    {
        return $this->value === '/' ? [] : explode('/', ltrim($this->value, '/'));
    }

    /**
     * Return a new path with one segment appended.
     *
     * @param string $segment
     * @return self
     */
    public function append(string $segment): self
    {
        if ($segment === '' || str_contains($segment, '/')) {
            throw new InvalidValueException("Route segment '{$segment}' must be non-empty and contain no '/'.");
        }

        return new self(rtrim($this->value, '/') . '/' . $segment);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
