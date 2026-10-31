<?php
namespace Andmarruda\Lpb\Widgets;

use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Closure;

/**
 * Fluent builder of a widget config schema, producing JSON Schema 2020-12.
 *
 * Each field method adds a property and makes it current; modifiers apply
 * to the current field:
 *
 *     $s->string('title')->required()->maxLength(120)
 *       ->media('image')
 *       ->array('buttons', fn (Schema $b) => $b->string('label')->link('url'));
 *
 * Objects reject unknown properties. label() and help() become the title
 * and description annotations shown by the editor. media, link and
 * richText fields carry an "x-lpb-type" annotation so editors pick the
 * right control.
 */
final class Schema
{
    public const DIALECT = 'https://json-schema.org/draft/2020-12/schema';

    /** @var array<string, array> */
    private array $properties = [];

    /** @var string[] */
    private array $required = [];

    private ?string $current = null;

    private ?array $raw = null;

    public static function make(): self
    {
        return new self();
    }

    /**
     * Use a hand-written JSON Schema instead of the builder.
     *
     * @param array $schema
     * @return self
     */
    public static function raw(array $schema): self
    {
        $instance = new self();
        $instance->raw = $schema;
        return $instance;
    }

    public function string(string $name): self
    {
        return $this->field($name, ['type' => 'string']);
    }

    public function integer(string $name): self
    {
        return $this->field($name, ['type' => 'integer']);
    }

    public function number(string $name): self
    {
        return $this->field($name, ['type' => 'number']);
    }

    public function boolean(string $name): self
    {
        return $this->field($name, ['type' => 'boolean']);
    }

    /**
     * @param string $name
     * @param array<int, string|int|float|bool|null> $values
     * @return self
     */
    public function enum(string $name, array $values): self
    {
        return $this->field($name, ['enum' => array_values($values)]);
    }

    /**
     * A list; items are objects described by the callback, or any value.
     *
     * @param string $name
     * @param Closure(Schema): Schema|null $items
     * @return self
     */
    public function array(string $name, ?Closure $items = null): self
    {
        $definition = ['type' => 'array'];
        if ($items !== null) {
            $definition['items'] = $items(new self())->objectSchema();
        }
        return $this->field($name, $definition);
    }

    /**
     * A list of strings, e.g. rotating words or tags.
     */
    public function strings(string $name): self
    {
        return $this->field($name, ['type' => 'array', 'items' => ['type' => 'string']]);
    }

    /**
     * A nested object described by the callback.
     *
     * @param string $name
     * @param Closure(Schema): Schema $properties
     * @return self
     */
    public function object(string $name, Closure $properties): self
    {
        return $this->field($name, $properties(new self())->objectSchema());
    }

    /**
     * A media reference: {"$media": id}, resolved in delivery payloads.
     */
    public function media(string $name): self
    {
        return $this->field($name, [
            'type' => 'object',
            'properties' => ['$media' => ['type' => ['integer', 'string']]],
            'required' => ['$media'],
            'additionalProperties' => false,
            'x-lpb-type' => 'media',
        ]);
    }

    /**
     * A link: {"url": "...", "target": "_self|_blank", "label": "..."}.
     */
    public function link(string $name): self
    {
        return $this->field($name, [
            'type' => 'object',
            'properties' => [
                'url' => ['type' => 'string', 'maxLength' => 2048],
                'target' => ['enum' => ['_self', '_blank']],
                'label' => ['type' => 'string'],
            ],
            'required' => ['url'],
            'additionalProperties' => false,
            'x-lpb-type' => 'link',
        ]);
    }

    /**
     * Rich text as an HTML string. Renderers must sanitise it before output.
     */
    public function richText(string $name): self
    {
        return $this->field($name, ['type' => 'string', 'x-lpb-type' => 'richText']);
    }

    public function required(): self
    {
        $name = $this->currentField();
        if (!in_array($name, $this->required, true)) {
            $this->required[] = $name;
        }
        return $this;
    }

    public function default(mixed $value): self
    {
        return $this->set('default', $value);
    }

    /**
     * Minimum value (numbers), length (strings) or item count (arrays).
     */
    public function min(int|float $value): self
    {
        return $this->set($this->boundKeyword('minimum', 'minLength', 'minItems'), $value);
    }

    /**
     * Maximum value (numbers), length (strings) or item count (arrays).
     */
    public function max(int|float $value): self
    {
        return $this->set($this->boundKeyword('maximum', 'maxLength', 'maxItems'), $value);
    }

    public function maxLength(int $value): self
    {
        return $this->set('maxLength', $value);
    }

    public function pattern(string $regex): self
    {
        return $this->set('pattern', $regex);
    }

    public function label(string $label): self
    {
        return $this->set('title', $label);
    }

    public function help(string $help): self
    {
        return $this->set('description', $help);
    }

    /**
     * The complete JSON Schema document.
     *
     * @return array
     */
    public function toArray(): array
    {
        if ($this->raw !== null) {
            return $this->raw + ['$schema' => self::DIALECT];
        }
        return ['$schema' => self::DIALECT] + $this->objectSchema();
    }

    /**
     * @return array
     */
    private function objectSchema(): array
    {
        if ($this->raw !== null) {
            return $this->raw;
        }

        $schema = ['type' => 'object', 'properties' => $this->properties ?: new \stdClass(), 'additionalProperties' => false];
        if ($this->required !== []) {
            $schema['required'] = $this->required;
        }
        return $schema;
    }

    private function field(string $name, array $definition): self
    {
        if ($name === '' || str_starts_with($name, '$')) {
            throw new InvalidValueException("Invalid widget field name '{$name}'.");
        }
        $this->properties[$name] = $definition;
        $this->current = $name;
        return $this;
    }

    private function set(string $keyword, mixed $value): self
    {
        $this->properties[$this->currentField()][$keyword] = $value;
        return $this;
    }

    private function currentField(): string
    {
        return $this->current ?? throw new InvalidValueException('Add a field before setting its options.');
    }

    private function boundKeyword(string $number, string $string, string $array): string
    {
        return match ($this->properties[$this->currentField()]['type'] ?? null) {
            'string' => $string,
            'array' => $array,
            default => $number,
        };
    }
}
