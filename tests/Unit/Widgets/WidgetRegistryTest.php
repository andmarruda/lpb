<?php

namespace Andmarruda\Lpb\Tests\Unit\Widgets;

use PHPUnit\Framework\TestCase;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\Exceptions\TreeValidationException;
use Andmarruda\Lpb\Tests\Fixtures\Widgets\Card;
use Andmarruda\Lpb\Tests\Fixtures\Widgets\Hero;
use Andmarruda\Lpb\Validation\ConfigValidator;
use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\TypeScriptGenerator;
use Andmarruda\Lpb\Widgets\WidgetDefinition;
use Andmarruda\Lpb\Widgets\WidgetRegistry;
use Illuminate\Container\Container;

/**
 * Unit tests for the widget registry, upgrades and TypeScript generation
 */
class WidgetRegistryTest extends TestCase
{
    private function registry(): WidgetRegistry
    {
        return (new WidgetRegistry(new Container(), new ConfigValidator()))->register(Hero::class)->register(new Card());
    }

    /**
     * Test registered widgets are listed by name and described for editors
     */
    public function test_register_and_describe(): void
    {
        $registry = $this->registry();

        $this->assertSame(['card', 'hero'], array_keys($registry->all()));
        $this->assertTrue($registry->has('hero'));
        $this->assertFalse($registry->has('missing'));

        $described = $registry->describe();
        $this->assertSame(['card', 'hero'], array_column($described, 'name'));
        $this->assertSame('Hero banner', $described[1]['label']);
        $this->assertSame('Card', $described[0]['label']);
        $this->assertSame(3, $described[0]['version']);
        $this->assertSame(Schema::DIALECT, $described[1]['schema']['$schema']);
    }

    /**
     * Test invalid definitions are rejected
     */
    public function test_rejects_invalid_definitions(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->registry()->register(new class extends WidgetDefinition {
            public string $name = 'Bad Name';

            public function schema(Schema $s): Schema
            {
                return $s;
            }
        });
    }

    /**
     * Test validate returns the config at the current version or throws with paths
     */
    public function test_validate(): void
    {
        $registry = $this->registry();

        $this->assertSame([['title' => 'Hi'], 1], $registry->validate('hero', ['title' => 'Hi']));

        try {
            $registry->validate('hero', ['title' => str_repeat('x', 121)]);
            $this->fail('An invalid config was accepted');
        } catch (TreeValidationException $e) {
            $this->assertSame('/config/title', $e->errors()[0]['path']);
        }

        try {
            $registry->validate('missing', []);
            $this->fail('An unknown widget was accepted');
        } catch (TreeValidationException $e) {
            $this->assertSame('/widget', $e->errors()[0]['path']);
        }
    }

    /**
     * Test old configs are upgraded step by step before validation
     */
    public function test_upgrades_step_by_step(): void
    {
        $registry = $this->registry();

        $this->assertSame([['title' => 'Old', 'size' => 'md'], 3], $registry->upgrade('card', ['heading' => 'Old'], 1));
        $this->assertSame([['title' => 'T', 'size' => 'md'], 3], $registry->upgrade('card', ['title' => 'T'], 2));
        $this->assertSame([['title' => 'T', 'size' => 'lg'], 3], $registry->upgrade('card', ['title' => 'T', 'size' => 'lg'], 3));
        $this->assertSame([['x' => 1], 4], $registry->upgrade('missing', ['x' => 1], 4));

        $this->assertSame([['title' => 'Old', 'size' => 'md'], 3], $registry->validate('card', ['heading' => 'Old'], 1));

        $this->expectException(TreeValidationException::class);
        $registry->validate('card', ['title' => 'T', 'size' => 'md'], 4);
    }

    /**
     * Test generated TypeScript types describe resolved configs
     */
    public function test_typescript_types(): void
    {
        $types = (new TypeScriptGenerator($this->registry()))->generate();

        $this->assertStringContainsString('export interface HeroConfig {', $types);
        $this->assertStringContainsString('  /** Title */', $types);
        $this->assertStringContainsString('  title?: string;', $types);
        $this->assertStringContainsString('  image?: LpbMedia | null;', $types);
        $this->assertStringContainsString("  size: \"sm\" | \"md\" | \"lg\";", $types);
        $this->assertStringContainsString("  \"hero\": HeroConfig;", $types);
        $this->assertMatchesRegularExpression('/buttons\?: \{\n\s+label: string;\n\s+url: string;\n\s+\}\[\];/', $types);
    }
}
