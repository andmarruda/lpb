<?php

namespace Andmarruda\Lpb\Tests\Feature\Concerns;

/**
 * Comparisons for arrays read back from JSON or document storage.
 *
 * MySQL JSON columns and MongoDB's embedded id renaming change the order of
 * object keys, never the order of list items, so keys are sorted
 * recursively before a strict comparison.
 */
trait ComparesStoredArrays
{
    protected function assertSameIgnoringKeyOrder(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertSame($this->sortKeysRecursively($expected), $this->sortKeysRecursively($actual), $message);
    }

    private function sortKeysRecursively(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->sortKeysRecursively($item), $value);
    }
}
