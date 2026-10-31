<?php

namespace Andmarruda\Lpb\Tests\Unit\Widgets;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Andmarruda\Lpb\Validation\ConfigValidator;
use Andmarruda\Lpb\Widgets\Library\Library;
use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetRegistry;
use Illuminate\Container\Container;

/**
 * Unit tests for the built-in widget library
 */
class LibraryTest extends TestCase
{
    public static function definitions(): array
    {
        return array_combine(Library::DEFINITIONS, array_map(fn (string $class) => [$class], Library::DEFINITIONS));
    }

    /**
     * Test every library widget registers with a unique name, a label and a category
     */
    public function test_library_registers(): void
    {
        $registry = new WidgetRegistry(new Container(), new ConfigValidator());
        foreach (Library::DEFINITIONS as $definition) {
            $registry->register($definition);
        }

        $this->assertCount(count(Library::DEFINITIONS), $registry->all());
        foreach ($registry->describe() as $widget) {
            $this->assertNotNull($widget['category'], $widget['name']);
            $this->assertSame(Schema::DIALECT, $widget['schema']['$schema']);
        }
    }

    /**
     * Test every example is a valid config of its widget
     */
    #[DataProvider('definitions')]
    public function test_example_is_valid(string $class): void
    {
        $definition = new $class();

        $errors = (new ConfigValidator())->validate($definition->example(), $definition->schema(Schema::make())->toArray());

        $this->assertSame([], $errors, $definition->name);
        $this->assertNotSame([], $definition->example(), $definition->name);
    }

    /**
     * Test required fields are enforced, e.g. a heading needs its text
     */
    public function test_required_fields(): void
    {
        $registry = (new WidgetRegistry(new Container(), new ConfigValidator()))->register(\Andmarruda\Lpb\Widgets\Library\Heading::class);

        [, , $errors] = $registry->check('heading', ['tag' => 'h1'], null);
        $this->assertSame('/config', $errors[0]['path']);

        [, , $errors] = $registry->check('heading', ['text' => 'Hi', 'tag' => 'h7'], null);
        $this->assertSame('/config/tag', $errors[0]['path']);
    }
}
