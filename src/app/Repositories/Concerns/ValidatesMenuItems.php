<?php

namespace Andmarruda\Lpb\Repositories\Concerns;

use Andmarruda\Lpb\Exceptions\InvalidValueException;

/**
 * Validation of menu item input shared by both drivers.
 */
trait ValidatesMenuItems
{
    use ValidatesOrder;

    /**
     * Extract and validate the writable item attributes
     * (label, url, target, order, parent_id).
     *
     * @param array $data
     * @param bool $creating Whether label and url are required
     * @return array
     */
    protected function menuItemAttributes(array $data, bool $creating): array
    {
        $attributes = array_intersect_key($data, array_flip(['label', 'url', 'target', 'order', 'parent_id']));

        foreach (['label', 'url'] as $field) {
            if ($creating || array_key_exists($field, $attributes)) {
                $value = $attributes[$field] ?? null;
                if (!is_string($value) || trim($value) === '') {
                    throw new InvalidValueException("Menu item {$field} must be a non-empty string.");
                }
            }
        }

        if (array_key_exists('target', $attributes) && !in_array($attributes['target'], ['_self', '_blank', '_parent', '_top'], true)) {
            throw new InvalidValueException('Menu item target must be one of _self, _blank, _parent or _top.');
        }

        if (array_key_exists('order', $attributes)) {
            $attributes['order'] = $this->validOrder($attributes['order']);
        }

        return $attributes;
    }
}
