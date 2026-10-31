<?php
namespace Andmarruda\Lpb\Console;

use Andmarruda\Lpb\Delivery\DesignVersion;
use Illuminate\Console\Command;

/**
 * Bumps the design version, e.g. after a deployment that changes widgets
 * or styles: every page gets a new content cache key.
 */
class DesignBumpCommand extends Command
{
    protected $signature = 'lpb:design:bump';

    protected $description = 'Bump the design version so every page payload is rebuilt';

    public function handle(DesignVersion $design): int
    {
        $this->info("Design version is now {$design->bump()}.");
        return self::SUCCESS;
    }
}
