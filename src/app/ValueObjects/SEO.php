<?php
namespace Andmarruda\Lpb\ValueObjects;

use Andmarruda\Lpb\Exceptions\InvalidValueException;
use JsonException;

/**
 * Primary SEO fields of a page or template.
 *
 * Empty strings are stored as null. canonical and og_image must be absolute URLs.
 */
final readonly class SEO
{
    /**
     * Array/JSON keys mapped to their metatag names.
     */
    private const METATAGS = [
        'title' => 'title',
        'description' => 'description',
        'og_title' => 'og:title',
        'og_description' => 'og:description',
        'og_image' => 'og:image',
        'canonical' => 'canonical',
        'robots' => 'robots',
    ];

    public ?string $title;
    public ?string $description;
    public ?string $ogTitle;
    public ?string $ogDescription;
    public ?string $ogImage;
    public ?string $canonical;
    public ?string $robots;

    public function __construct(
        ?string $title = null,
        ?string $description = null,
        ?string $ogTitle = null,
        ?string $ogDescription = null,
        ?string $ogImage = null,
        ?string $canonical = null,
        ?string $robots = null,
    ) {
        $this->title = self::clean($title);
        $this->description = self::clean($description);
        $this->ogTitle = self::clean($ogTitle);
        $this->ogDescription = self::clean($ogDescription);
        $this->ogImage = self::url('og_image', $ogImage);
        $this->canonical = self::url('canonical', $canonical);
        $this->robots = self::clean($robots);
    }

    /**
     * Create from an array with snake_case keys; unknown keys are rejected.
     *
     * @param array<string, string|null> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $unknown = array_diff(array_keys($data), array_keys(self::METATAGS));
        if ($unknown !== []) {
            throw new InvalidValueException('Unknown SEO keys: ' . implode(', ', $unknown) . '.');
        }

        return new self(
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            ogTitle: $data['og_title'] ?? null,
            ogDescription: $data['og_description'] ?? null,
            ogImage: $data['og_image'] ?? null,
            canonical: $data['canonical'] ?? null,
            robots: $data['robots'] ?? null,
        );
    }

    /**
     * Create from the JSON stored in the seo column.
     *
     * @param string $json
     * @return self
     */
    public static function fromJsonString(string $json): self
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidValueException('Invalid SEO JSON: ' . $e->getMessage(), previous: $e);
        }

        if (!is_array($data)) {
            throw new InvalidValueException('SEO JSON must decode to an object.');
        }

        return self::fromArray($data);
    }

    /**
     * Fields with snake_case keys, omitting nulls.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'description' => $this->description,
            'og_title' => $this->ogTitle,
            'og_description' => $this->ogDescription,
            'og_image' => $this->ogImage,
            'canonical' => $this->canonical,
            'robots' => $this->robots,
        ], fn (?string $value) => $value !== null);
    }

    /**
     * Fields keyed by metatag name (e.g. 'og:title'), omitting nulls.
     *
     * @return array<string, string>
     */
    public function toMetatags(): array
    {
        $metatags = [];
        foreach ($this->toArray() as $key => $value) {
            $metatags[self::METATAGS[$key]] = $value;
        }
        return $metatags;
    }

    public function toJsonString(): string
    {
        return json_encode((object) $this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function isEmpty(): bool
    {
        return $this->toArray() === [];
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    public function __toString(): string
    {
        return $this->toJsonString();
    }

    private static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }

    private static function url(string $field, ?string $value): ?string
    {
        $value = self::clean($value);
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) {
            throw new InvalidValueException("SEO {$field} must be an absolute URL, got '{$value}'.");
        }
        return $value;
    }
}
