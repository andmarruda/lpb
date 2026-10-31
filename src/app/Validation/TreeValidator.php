<?php
namespace Andmarruda\Lpb\Validation;

use Andmarruda\Lpb\Exceptions\TreeValidationException;
use Andmarruda\Lpb\Support\Ulid;
use Andmarruda\Lpb\Tree\BlockType;
use Andmarruda\Lpb\Tree\FlatTree;
use Andmarruda\Lpb\Tree\TreeLimits;
use Andmarruda\Lpb\Widgets\WidgetRegistry;

/**
 * Validates a submitted page tree as a whole before it is written.
 *
 * Checks the shape, node ids (ULIDs, unique across blocks and widgets),
 * block types, styles, widgets (registered, config valid at its
 * schema_version) and the tree limits, and returns the normalised blocks:
 * uppercase ids, normalised styles and configs upgraded to the current
 * widget version.
 */
final class TreeValidator
{
    private const BLOCK_KEYS = ['id', 'type', 'styles', 'widgets', 'children'];
    private const WIDGET_KEYS = ['id', 'widget', 'schema_version', 'config', 'styles'];
    private const MAX_ERRORS = 50;

    private array $errors = [];

    private array $ids = [];

    public function __construct(private WidgetRegistry $widgets, private StyleValidator $styles, private TreeLimits $limits)
    {
    }

    public static function make(): self
    {
        return new self(app(WidgetRegistry::class), StyleValidator::fromConfig(), TreeLimits::fromConfig());
    }

    /**
     * @param array $tree Canonical tree, or at least ['blocks' => [...]]
     * @return array Normalised nested blocks
     * @throws TreeValidationException
     */
    public function validate(array $tree): array
    {
        $this->errors = [];
        $this->ids = [];

        $blocks = $tree['blocks'] ?? null;
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new TreeValidationException([['path' => '/blocks', 'message' => 'blocks must be a list.']]);
        }

        // Cheap size checks first, so an oversized tree is not walked in full
        $depth = $this->depth($blocks);
        $nodes = $this->count($blocks);
        try {
            $this->limits->assertWithin($depth, $nodes);
        } catch (\Andmarruda\Lpb\Exceptions\InvalidValueException $e) {
            throw new TreeValidationException([['path' => '/blocks', 'message' => $e->getMessage()]]);
        }

        $normalized = $this->blocks($blocks, '/blocks');
        if ($this->errors !== []) {
            throw new TreeValidationException(array_slice($this->errors, 0, self::MAX_ERRORS));
        }
        return $normalized;
    }

    private function blocks(array $blocks, string $path): array
    {
        $normalized = [];
        foreach ($blocks as $index => $block) {
            $at = JsonPointer::append($path, $index);
            if (!$this->isObject($block)) {
                $this->error($at, 'A block must be an object.');
                continue;
            }
            $this->unknownKeys($block, self::BLOCK_KEYS, $at);

            $type = $block['type'] ?? null;
            if (!is_string($type) || BlockType::tryFrom($type) === null) {
                $this->error(JsonPointer::append($at, 'type'), 'Block type must be one of: ' . implode(', ', BlockType::values()) . '.');
            }

            $widgets = $block['widgets'] ?? [];
            if (!is_array($widgets) || !array_is_list($widgets)) {
                $this->error(JsonPointer::append($at, 'widgets'), 'widgets must be a list.');
                $widgets = [];
            }
            $children = $block['children'] ?? [];
            if (!is_array($children) || !array_is_list($children)) {
                $this->error(JsonPointer::append($at, 'children'), 'children must be a list.');
                $children = [];
            }

            $normalized[] = [
                'id' => $this->id($block['id'] ?? null, JsonPointer::append($at, 'id')),
                'type' => $type,
                'styles' => $this->styles($block['styles'] ?? [], JsonPointer::append($at, 'styles')),
                'widgets' => $this->widgets($widgets, JsonPointer::append($at, 'widgets')),
                'children' => $this->blocks($children, JsonPointer::append($at, 'children')),
            ];
        }
        return $normalized;
    }

    private function widgets(array $widgets, string $path): array
    {
        $normalized = [];
        foreach ($widgets as $index => $widget) {
            $at = JsonPointer::append($path, $index);
            if (!$this->isObject($widget)) {
                $this->error($at, 'A widget instance must be an object.');
                continue;
            }
            $this->unknownKeys($widget, self::WIDGET_KEYS, $at);

            $id = $this->id($widget['id'] ?? null, JsonPointer::append($at, 'id'));
            $name = $widget['widget'] ?? null;
            if (!is_string($name)) {
                $this->error(JsonPointer::append($at, 'widget'), 'widget must be the name of a registered widget.');
                continue;
            }

            $schemaVersion = $widget['schema_version'] ?? null;
            if ($schemaVersion !== null && !is_int($schemaVersion)) {
                $this->error(JsonPointer::append($at, 'schema_version'), 'schema_version must be an integer.');
                continue;
            }

            [$config, $version, $errors] = $this->widgets->check($name, $widget['config'] ?? [], $schemaVersion, $at);
            array_push($this->errors, ...$errors);

            $normalized[] = [
                'id' => $id,
                'widget' => $name,
                'schema_version' => $version,
                'config' => $config,
                'styles' => $this->styles($widget['styles'] ?? [], JsonPointer::append($at, 'styles')),
            ];
        }
        return $normalized;
    }

    private function id(mixed $id, string $path): string
    {
        if (!Ulid::isValid($id)) {
            $this->error($path, 'Node ids must be ULIDs generated by the client.');
            return (string) (is_scalar($id) ? $id : '');
        }

        $id = Ulid::normalize($id);
        if (isset($this->ids[$id])) {
            $this->error($path, "Node id {$id} is already used at {$this->ids[$id]}.");
        }
        $this->ids[$id] ??= $path;
        return $id;
    }

    private function styles(mixed $styles, string $path): array
    {
        $errors = $this->styles->validate($styles, $path);
        if ($errors !== []) {
            array_push($this->errors, ...$errors);
            return [];
        }
        return $this->styles->assertValid($styles, $path);
    }

    private function unknownKeys(array $node, array $allowed, string $path): void
    {
        foreach (array_diff(array_keys($node), $allowed) as $key) {
            $this->error(JsonPointer::append($path, (string) $key), "Unknown key '{$key}'.");
        }
    }

    private function isObject(mixed $value): bool
    {
        return is_array($value) && ($value === [] || !array_is_list($value));
    }

    private function error(string $path, string $message): void
    {
        $this->errors[] = ['path' => $path, 'message' => $message];
    }

    private function depth(array $blocks): int
    {
        $depth = 0;
        foreach ($blocks as $block) {
            $children = is_array($block) && is_array($block['children'] ?? null) ? $block['children'] : [];
            $depth = max($depth, 1 + $this->depth($children));
            if ($depth > $this->limits->maxDepth()) {
                return $depth;
            }
        }
        return $depth;
    }

    private function count(array $blocks): int
    {
        return FlatTree::count(array_map(fn ($block) => is_array($block) ? [
            'widgets' => is_array($block['widgets'] ?? null) ? $block['widgets'] : [],
            'children' => is_array($block['children'] ?? null) ? $block['children'] : [],
        ] : [], $blocks));
    }
}
