<?php
namespace Andmarruda\Lpb\Console;

use Andmarruda\Lpb\Widgets\TypeScriptGenerator;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Writes TypeScript types of every registered widget's config, as
 * renderers receive it (media references resolved).
 */
class TypesCommand extends Command
{
    protected $signature = 'lpb:types
        {--output= : File to write (defaults to resources/js/lpb/widgets.d.ts)}';

    protected $description = 'Generate TypeScript types for the registered widget configs';

    public function handle(TypeScriptGenerator $generator, Filesystem $files): int
    {
        $output = $this->option('output') ?: resource_path('js/lpb/widgets.d.ts');

        $files->ensureDirectoryExists(dirname($output));
        $files->put($output, $generator->generate());

        $this->info("Widget types written to {$output}.");
        return self::SUCCESS;
    }
}
