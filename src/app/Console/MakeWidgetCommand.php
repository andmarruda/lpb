<?php
namespace Andmarruda\Lpb\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Scaffolds a widget: the PHP definition and a render component stub per
 * frontend framework the site uses.
 */
class MakeWidgetCommand extends Command
{
    private const FRAMEWORKS = [
        'react' => '{Name}.tsx',
        'vue' => '{Name}.vue',
        'svelte' => '{Name}.svelte',
        'angular' => '{kebab}.component.ts',
    ];

    protected $signature = 'lpb:make-widget
        {name : Widget class name, e.g. Hero or PricingTable}
        {--frameworks=react : Comma-separated frameworks: react, vue, svelte, angular}
        {--force : Overwrite existing files}';

    protected $description = 'Create a widget definition and its render component stubs';

    public function handle(Filesystem $files): int
    {
        $class = Str::studly($this->argument('name'));
        $widgetName = Str::kebab($class);
        $frameworks = array_filter(array_map('trim', explode(',', (string) $this->option('frameworks'))));

        $unknown = array_diff($frameworks, array_keys(self::FRAMEWORKS));
        if ($unknown !== []) {
            $this->error('Unknown frameworks: ' . implode(', ', $unknown) . '. Use ' . implode(', ', array_keys(self::FRAMEWORKS)) . '.');
            return self::FAILURE;
        }

        $namespace = trim((string) config('laravel-page-builder.widgets.namespace', 'App\\Widgets'), '\\');
        $phpPath = rtrim((string) config('laravel-page-builder.widgets.path', app_path('Widgets')), '/') . "/{$class}.php";
        $written = $this->write($files, $phpPath, $this->definitionStub($namespace, $class, $widgetName));

        $frontendPath = rtrim((string) config('laravel-page-builder.widgets.frontend_path', resource_path('js/lpb/widgets')), '/');
        foreach ($frameworks as $framework) {
            $file = str_replace(['{Name}', '{kebab}'], [$class, $widgetName], self::FRAMEWORKS[$framework]);
            $written = $this->write($files, "{$frontendPath}/{$framework}/{$file}", $this->componentStub($framework, $class, $widgetName)) && $written;
        }

        $this->line('');
        $this->line("Register it in config/laravel-page-builder.php under widgets.definitions: \\{$namespace}\\{$class}::class");
        $this->line("Register the components under the name '{$widgetName}' in your frontend widget registry.");
        return $written ? self::SUCCESS : self::FAILURE;
    }

    private function write(Filesystem $files, string $path, string $contents): bool
    {
        if ($files->exists($path) && !$this->option('force')) {
            $this->error("{$path} already exists (use --force to overwrite).");
            return false;
        }

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $contents);
        $this->line("Created {$path}");
        return true;
    }

    private function definitionStub(string $namespace, string $class, string $name): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use Andmarruda\\Lpb\\Widgets\\Schema;
use Andmarruda\\Lpb\\Widgets\\WidgetDefinition;

class {$class} extends WidgetDefinition
{
    public string \$name = '{$name}';

    public int \$version = 1;

    public function schema(Schema \$s): Schema
    {
        return \$s
            ->string('title')->label('Title')->maxLength(120);
    }
}

PHP;
    }

    private function componentStub(string $framework, string $class, string $name): string
    {
        return match ($framework) {
            'react' => <<<TSX
import type { {$class}Config } from '../../widgets';

export default function {$class}({ config }: { config: {$class}Config }) {
  return <div className="lpb-{$name}">{config.title}</div>;
}

TSX,
            'vue' => <<<VUE
<script setup lang="ts">
import type { {$class}Config } from '../../widgets';

defineProps<{ config: {$class}Config }>();
</script>

<template>
  <div class="lpb-{$name}">{{ config.title }}</div>
</template>

VUE,
            'svelte' => <<<SVELTE
<script lang="ts">
  import type { {$class}Config } from '../../widgets';

  export let config: {$class}Config;
</script>

<div class="lpb-{$name}">{config.title}</div>

SVELTE,
            'angular' => <<<TS
import { Component, Input } from '@angular/core';
import type { {$class}Config } from '../../widgets';

@Component({
  selector: 'lpb-{$name}',
  standalone: true,
  template: `<div class="lpb-{$name}">{{ config.title }}</div>`,
})
export class {$class}Component {
  @Input({ required: true }) config!: {$class}Config;
}

TS,
        };
    }
}
