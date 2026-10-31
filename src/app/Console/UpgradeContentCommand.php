<?php
namespace Andmarruda\Lpb\Console;

use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Rewrites stored widget configs older than their widget definition to the
 * current version. Revisions are never rewritten; they are upgraded on read.
 */
class UpgradeContentCommand extends Command
{
    protected $signature = 'lpb:upgrade-content';

    protected $description = 'Upgrade stored widget configs to their current widget versions';

    public function handle(PageRepositoryInterface $pages, PageTreeRepositoryInterface $trees): int
    {
        $total = 0;
        foreach ($pages->all() as $page) {
            $count = $trees->upgradeStoredContent((string) $page['id']);
            if ($count > 0) {
                $this->line("{$page['slug']}: {$count} widget instances upgraded");
            }
            $total += $count;
        }

        $this->info("{$total} widget instances upgraded.");
        return self::SUCCESS;
    }
}
