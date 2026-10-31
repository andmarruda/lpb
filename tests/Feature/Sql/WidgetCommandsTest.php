<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;
use Andmarruda\Lpb\Facades\Lpb;
use Andmarruda\Lpb\Tests\Feature\Concerns\RegistersTestWidgets;
use Andmarruda\Lpb\Widgets\WidgetRegistry;

/**
 * Widget tooling commands: lpb:widgets:schema, lpb:types and lpb:make-widget
 */
class WidgetCommandsTest extends TestCase
{
    use RegistersTestWidgets;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerTestWidgets();
        $this->directory = sys_get_temp_dir() . '/lpb-widgets-' . uniqid();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directory);

        parent::tearDown();
    }

    /**
     * Test the facade exposes the same registry singleton
     */
    public function test_facade_returns_registry(): void
    {
        $this->assertSame(app(WidgetRegistry::class), Lpb::widgets());
        $this->assertTrue(Lpb::widgets()->has('hero'));
    }

    /**
     * Test configured definitions are registered on first use
     */
    public function test_configured_definitions(): void
    {
        $this->app->forgetInstance(WidgetRegistry::class);
        config(['laravel-page-builder.widgets.definitions' => [\Andmarruda\Lpb\Tests\Fixtures\Widgets\Footer::class]]);

        $this->assertSame(['footer'], array_keys(app(WidgetRegistry::class)->all()));
    }

    /**
     * Test the built-in library is registered when enabled, and a definition can replace one
     */
    public function test_widget_library(): void
    {
        $this->app->forgetInstance(WidgetRegistry::class);
        config([
            'laravel-page-builder.widgets.library' => true,
            'laravel-page-builder.widgets.definitions' => [\Andmarruda\Lpb\Tests\Fixtures\Widgets\Text::class],
        ]);
        $registry = app(WidgetRegistry::class);

        $this->assertCount(count(\Andmarruda\Lpb\Widgets\Library\Library::DEFINITIONS), $registry->all());
        $this->assertSame('Price table', $registry->get('price-table')->label);
        $this->assertInstanceOf(\Andmarruda\Lpb\Tests\Fixtures\Widgets\Text::class, $registry->get('text'));
    }

    /**
     * Test lpb:widgets:schema writes every definition with its JSON Schema
     */
    public function test_widgets_schema_command(): void
    {
        $file = "{$this->directory}/widgets.json";

        $this->artisan('lpb:widgets:schema', ['--output' => $file])->assertSuccessful();

        $widgets = json_decode(file_get_contents($file), true)['widgets'];
        $this->assertSame(['card', 'footer', 'hero', 'text'], array_column($widgets, 'name'));
        $this->assertSame('richText', $widgets[3]['schema']['properties']['body']['x-lpb-type']);
    }

    /**
     * Test lpb:types writes the TypeScript config types
     */
    public function test_types_command(): void
    {
        $file = "{$this->directory}/widgets.d.ts";

        $this->artisan('lpb:types', ['--output' => $file])->assertSuccessful();

        $types = file_get_contents($file);
        $this->assertStringContainsString('export interface CardConfig {', $types);
        $this->assertStringContainsString('export interface LpbWidgetConfigs {', $types);
    }

    /**
     * Test lpb:make-widget creates the definition and one stub per framework
     */
    public function test_make_widget_command(): void
    {
        config([
            'laravel-page-builder.widgets.path' => "{$this->directory}/php",
            'laravel-page-builder.widgets.frontend_path' => "{$this->directory}/js",
        ]);

        $this->artisan('lpb:make-widget', ['name' => 'PricingTable', '--frameworks' => 'react,vue,svelte,angular'])->assertSuccessful();

        $php = file_get_contents("{$this->directory}/php/PricingTable.php");
        $this->assertStringContainsString("public string \$name = 'pricing-table';", $php);
        $this->assertStringContainsString('class PricingTable extends WidgetDefinition', $php);
        foreach (['react/PricingTable.tsx', 'vue/PricingTable.vue', 'svelte/PricingTable.svelte', 'angular/pricing-table.component.ts'] as $stub) {
            $this->assertFileExists("{$this->directory}/js/{$stub}");
        }

        $this->artisan('lpb:make-widget', ['name' => 'PricingTable'])->assertFailed();
        $this->artisan('lpb:make-widget', ['name' => 'Other', '--frameworks' => 'qwik'])->assertFailed();
    }
}
