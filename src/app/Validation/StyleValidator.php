<?php
namespace Andmarruda\Lpb\Validation;

use Andmarruda\Lpb\Exceptions\TreeValidationException;

/**
 * Validation of style variants on blocks and widget instances.
 *
 * Styles are a list of variants, each with a breakpoint (null or a key of
 * laravel-page-builder.breakpoints), a state (null, hover, focus, active)
 * and props: kebab-case CSS property names mapped to strings, numbers or a
 * {"$var": "token"} reference. At most one variant per (breakpoint, state).
 */
final class StyleValidator
{
    public const STATES = ['hover', 'focus', 'active'];

    private const PROPERTY_PATTERN = '/^(?:--|-)?[a-z][a-z0-9-]*$/';
    private const TOKEN_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    // Characters that would let a value escape its CSS declaration
    private const UNSAFE_VALUE = '/[;{}<>\\\\]|\/\*/';

    /**
     * @param string[] $breakpoints
     */
    public function __construct(private array $breakpoints)
    {
    }

    public static function fromConfig(): self
    {
        return new self(array_keys((array) config('laravel-page-builder.breakpoints', [])));
    }

    /**
     * @param mixed $styles
     * @param string $path JSON pointer of the styles
     * @return array Normalized styles (a list; null becomes empty)
     * @throws TreeValidationException
     */
    public function assertValid(mixed $styles, string $path = '/styles'): array
    {
        $errors = $this->validate($styles, $path);
        if ($errors !== []) {
            throw new TreeValidationException($errors);
        }
        return $this->normalize($styles);
    }

    /**
     * @param mixed $styles
     * @param string $path JSON pointer of the styles
     * @return array<int, array{path: string, message: string}>
     */
    public function validate(mixed $styles, string $path = '/styles'): array
    {
        if ($styles === null) {
            return [];
        }
        if (!is_array($styles) || !array_is_list($styles)) {
            return [['path' => $path, 'message' => 'Styles must be a list of variants.']];
        }

        $errors = [];
        $seen = [];
        foreach ($styles as $index => $variant) {
            $at = JsonPointer::append($path, $index);
            if (!is_array($variant) || ($variant !== [] && array_is_list($variant))) {
                $errors[] = ['path' => $at, 'message' => 'A style variant must be an object.'];
                continue;
            }

            foreach (array_diff(array_keys($variant), ['breakpoint', 'state', 'props']) as $unknown) {
                $errors[] = ['path' => JsonPointer::append($at, $unknown), 'message' => "Unknown style variant key '{$unknown}'."];
            }

            $breakpoint = $variant['breakpoint'] ?? null;
            if ($breakpoint !== null && !in_array($breakpoint, $this->breakpoints, true)) {
                $errors[] = ['path' => JsonPointer::append($at, 'breakpoint'), 'message' => 'Breakpoint must be null or one of: ' . implode(', ', $this->breakpoints) . '.'];
            }

            $state = $variant['state'] ?? null;
            if ($state !== null && !in_array($state, self::STATES, true)) {
                $errors[] = ['path' => JsonPointer::append($at, 'state'), 'message' => 'State must be null or one of: ' . implode(', ', self::STATES) . '.'];
            }

            $key = json_encode([$breakpoint, $state]);
            if (isset($seen[$key])) {
                $errors[] = ['path' => $at, 'message' => 'Only one variant is allowed per breakpoint and state.'];
            }
            $seen[$key] = true;

            array_push($errors, ...$this->validateProps($variant['props'] ?? [], JsonPointer::append($at, 'props')));
        }

        return $errors;
    }

    /**
     * @param mixed $props
     * @param string $path
     * @return array
     */
    private function validateProps(mixed $props, string $path): array
    {
        if (!is_array($props) || ($props !== [] && array_is_list($props))) {
            return [['path' => $path, 'message' => 'Style props must be an object of CSS properties.']];
        }

        $errors = [];
        foreach ($props as $property => $value) {
            $at = JsonPointer::append($path, $property);
            if (!is_string($property) || !preg_match(self::PROPERTY_PATTERN, $property)) {
                $errors[] = ['path' => $at, 'message' => "Invalid CSS property name '{$property}'; use kebab-case."];
                continue;
            }

            if (is_int($value) || is_float($value)) {
                continue;
            }
            if (is_string($value)) {
                if (preg_match(self::UNSAFE_VALUE, $value) || str_contains($value, "\n")) {
                    $errors[] = ['path' => $at, 'message' => 'Style values cannot contain ; { } < > \\ or comments.'];
                }
                continue;
            }
            if (is_array($value) && array_keys($value) === ['$var']) {
                if (!is_string($value['$var']) || !preg_match(self::TOKEN_PATTERN, $value['$var'])) {
                    $errors[] = ['path' => JsonPointer::append($at, '$var'), 'message' => 'A $var reference must name a kebab-case design token.'];
                }
                continue;
            }

            $errors[] = ['path' => $at, 'message' => 'Style values must be strings, numbers or a {"$var": "token"} reference.'];
        }

        return $errors;
    }

    /**
     * @param mixed $styles
     * @return array
     */
    private function normalize(mixed $styles): array
    {
        return array_map(fn (array $variant) => [
            'breakpoint' => $variant['breakpoint'] ?? null,
            'state' => $variant['state'] ?? null,
            'props' => $variant['props'] ?? [],
        ], $styles ?? []);
    }
}
