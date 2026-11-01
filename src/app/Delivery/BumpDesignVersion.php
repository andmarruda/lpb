<?php
namespace Andmarruda\Lpb\Delivery;

/**
 * Changes outside the snapshots (a template, a replaced or deleted media
 * file) make every content key new (ADR 09).
 */
class BumpDesignVersion
{
    public function __construct(private DesignVersion $design)
    {
    }

    public function handle(object $event): void
    {
        $this->design->bump();
    }
}
