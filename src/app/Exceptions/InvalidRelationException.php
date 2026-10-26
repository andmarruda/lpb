<?php
namespace Andmarruda\Lpb\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a write references a missing or incompatible related record,
 * such as a block whose parent belongs to another page.
 */
class InvalidRelationException extends InvalidArgumentException
{
}
