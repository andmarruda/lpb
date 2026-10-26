<?php

namespace Andmarruda\Lpb\Repositories\Concerns;

use Andmarruda\Lpb\Exceptions\InvalidValueException;

/**
 * Validation of block and widget instance input shared by both drivers.
 */
trait ValidatesBlocks
{
    use ValidatesOrder;

    /**
     * Extract and validate the writable block attributes (type, order, parent_id).
     *
     * @param array $data
     * @param bool $creating Whether type is required
     * @return array
     */
    protected function blockAttributes(array $data, bool $creating): array
    {
        $attributes = array_intersect_key($data, array_flip(['type', 'order', 'parent_id']));

        if ($creating || array_key_exists('type', $attributes)) {
            $type = $attributes['type'] ?? null;
            if (!is_string($type) || trim($type) === '') {
                throw new InvalidValueException('Block type must be a non-empty string.');
            }
        }

        if (array_key_exists('order', $attributes)) {
            $attributes['order'] = $this->validOrder($attributes['order']);
        }

        return $attributes;
    }

    /**
     * @param string $widget
     * @return void
     */
    protected function assertWidget(string $widget): void
    {
        if (trim($widget) === '') {
            throw new InvalidValueException('Widget must be a non-empty class name or alias.');
        }
    }
}
