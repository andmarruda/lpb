<?php
namespace Andmarruda\Lpb\ValueObjects;

use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Illuminate\Support\Str;

/**
 * URL slug: lowercase letters and digits separated by single hyphens.
 */
final readonly class Slug
{
    private const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private function __construct(private string $value)
    {
        if (!preg_match(self::PATTERN, $value)) {
            throw new InvalidValueException("Invalid slug '{$value}'. Use lowercase letters, digits and single hyphens.");
        }
    }

    /**
     * Create a slug from an already slugified string.
     *
     * @param string $value
     * @return self
     */
    public static function from(string $value): self
    {
        return new self($value);
    }

    /**
     * Create a slug from free text such as a page title.
     *
     * @param string $text
     * @return self
     */
    public static function fromTitle(string $text): self
    {
        return new self(Str::slug($text));
    }

    public function value(): string
    {
        return $this->value;
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
