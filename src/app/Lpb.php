<?php
namespace Andmarruda\Lpb;

use Andmarruda\Lpb\Widgets\WidgetRegistry;

/**
 * Entry point of the package, also available as the Lpb facade.
 */
class Lpb
{
    public function __construct(private WidgetRegistry $widgets)
    {
    }

    /**
     * The widget registry: Lpb::widgets()->register(Hero::class).
     *
     * @return WidgetRegistry
     */
    public function widgets(): WidgetRegistry
    {
        return $this->widgets;
    }
}
