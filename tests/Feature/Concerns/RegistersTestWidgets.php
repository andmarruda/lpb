<?php

namespace Andmarruda\Lpb\Tests\Feature\Concerns;

use Andmarruda\Lpb\Tests\Fixtures\Widgets\Card;
use Andmarruda\Lpb\Tests\Fixtures\Widgets\Footer;
use Andmarruda\Lpb\Tests\Fixtures\Widgets\Hero;
use Andmarruda\Lpb\Tests\Fixtures\Widgets\Text;
use Andmarruda\Lpb\Widgets\WidgetRegistry;

/**
 * Registers the fixture widgets (hero, text, footer, card); call from setUp().
 */
trait RegistersTestWidgets
{
    protected function registerTestWidgets(): void
    {
        app(WidgetRegistry::class)
            ->register(Hero::class)
            ->register(Text::class)
            ->register(Footer::class)
            ->register(Card::class);
    }
}
