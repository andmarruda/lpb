<?php
namespace Andmarruda\Lpb\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A template was updated or deleted; pages using it may look different.
 */
final class TemplateChanged
{
    use Dispatchable;

    public function __construct(public readonly string $templateId)
    {
    }
}
