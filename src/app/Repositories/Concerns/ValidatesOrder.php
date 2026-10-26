<?php

namespace Andmarruda\Lpb\Repositories\Concerns;

use Andmarruda\Lpb\Exceptions\InvalidValueException;

/**
 * Validation of order positions, stored as unsigned small integers.
 */
trait ValidatesOrder
{
    /**
     * @param mixed $order
     * @return int
     */
    protected function validOrder(mixed $order): int
    {
        if (!is_int($order) || $order < 0 || $order > 65535) {
            throw new InvalidValueException('Order must be an integer between 0 and 65535.');
        }
        return $order;
    }
}
