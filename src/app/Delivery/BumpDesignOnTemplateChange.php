<?php
namespace Andmarruda\Lpb\Delivery;

use Andmarruda\Lpb\Events\TemplateChanged;

/**
 * A template change makes every content key new (ADR 09).
 */
class BumpDesignOnTemplateChange
{
    public function __construct(private DesignVersion $design)
    {
    }

    public function handle(TemplateChanged $event): void
    {
        $this->design->bump();
    }
}
