<?php
namespace Andmarruda\Lpb\ValueObjects;

/**
 * Availability of a template for new page assignments.
 */
enum TemplateStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
