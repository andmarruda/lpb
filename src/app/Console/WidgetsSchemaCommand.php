<?php
namespace Andmarruda\Lpb\Console;

use Andmarruda\Lpb\Widgets\WidgetRegistry;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Exports every registered widget definition (name, version, label,
 * category and JSON Schema) as JSON, for editors and CI.
 */
class WidgetsSchemaCommand extends Command
{
    protected $signature = 'lpb:widgets:schema
        {--output= : File to write instead of printing}';

    protected $description = 'Export the registered widget definitions and their JSON Schemas';

    public function handle(WidgetRegistry $widgets, Filesystem $files): int
    {
        $json = json_encode(['widgets' => $widgets->describe()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

        $output = $this->option('output');
        if (!$output) {
            $this->output->write($json);
            return self::SUCCESS;
        }

        $files->ensureDirectoryExists(dirname($output));
        $files->put($output, $json);
        $this->info("Widget schemas written to {$output}.");
        return self::SUCCESS;
    }
}
