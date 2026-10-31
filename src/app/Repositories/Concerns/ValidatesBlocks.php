<?php

namespace Andmarruda\Lpb\Repositories\Concerns;

use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\Support\Ulid;
use Andmarruda\Lpb\Tree\BlockType;
use Andmarruda\Lpb\Tree\TreeLimits;
use Andmarruda\Lpb\Validation\StyleValidator;
use Andmarruda\Lpb\Widgets\WidgetRegistry;

/**
 * Validation of block and widget instance input shared by both drivers.
 */
trait ValidatesBlocks
{
    use ValidatesOrder;

    /**
     * Extract and validate the writable block attributes (type, order, parent_id, styles).
     *
     * @param array $data
     * @param bool $creating Whether type is required
     * @return array
     */
    protected function blockAttributes(array $data, bool $creating): array
    {
        $attributes = array_intersect_key($data, array_flip(['type', 'order', 'parent_id', 'styles']));

        if ($creating || array_key_exists('type', $attributes)) {
            $type = $attributes['type'] ?? null;
            if (!is_string($type) || BlockType::tryFrom($type) === null) {
                throw new InvalidValueException('Block type must be one of: ' . implode(', ', BlockType::values()) . '.');
            }
        }

        if (array_key_exists('order', $attributes)) {
            $attributes['order'] = $this->validOrder($attributes['order']);
        }

        if (array_key_exists('parent_id', $attributes) && $attributes['parent_id'] !== null) {
            $attributes['parent_id'] = (string) $attributes['parent_id'];
        }

        if ($creating || array_key_exists('styles', $attributes)) {
            $attributes['styles'] = $this->validStyles($attributes['styles'] ?? []);
        }

        return $attributes;
    }

    /**
     * @param mixed $styles
     * @return array
     */
    protected function validStyles(mixed $styles): array
    {
        return StyleValidator::fromConfig()->assertValid($styles);
    }

    /**
     * Check the page's tree stays within the configured limits after a change.
     *
     * @param array $blocks Flat blocks of the page after the change
     * @return void
     */
    protected function assertWithinLimits(array $blocks): void
    {
        TreeLimits::fromConfig()->assertFlat($blocks);
    }

    /**
     * Validate a widget instance and return its writable attributes.
     *
     * @param string $widget Registered widget name
     * @param array $config
     * @param array|null $styles Null to leave styles out (config-only updates)
     * @return array widget, schema_version, config and, unless null was given, styles
     */
    protected function widgetInstanceAttributes(string $widget, array $config, ?array $styles = []): array
    {
        [$config, $version] = app(WidgetRegistry::class)->validate($widget, $config);

        $attributes = ['widget' => $widget, 'schema_version' => $version, 'config' => $config];
        if ($styles !== null) {
            $attributes['styles'] = $this->validStyles($styles);
        }
        return $attributes;
    }

    /**
     * Client-generated block id from the input, or a new one.
     *
     * @param array $data
     * @return string
     */
    protected function newBlockId(array $data): string
    {
        if (!array_key_exists('id', $data) || $data['id'] === null) {
            return Ulid::generate();
        }
        if (!Ulid::isValid($data['id'])) {
            throw new InvalidValueException("Block id '{$data['id']}' must be a ULID.");
        }
        return Ulid::normalize($data['id']);
    }

    /**
     * Flat blocks with one more widget instance in a block, for limit checks.
     *
     * @param array $blocks
     * @param string $blockId
     * @param array $instance
     * @return array
     */
    protected function withWidgetInstance(array $blocks, string $blockId, array $instance): array
    {
        foreach ($blocks as &$block) {
            if ((string) $block['id'] === $blockId) {
                $block['widget_instances'][] = $instance + ['id' => ''];
            }
        }
        unset($block);
        return $blocks;
    }
}
