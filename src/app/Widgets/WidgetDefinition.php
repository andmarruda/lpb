<?php
namespace Andmarruda\Lpb\Widgets;

/**
 * A widget defined in code: its name, config schema and schema version.
 *
 *     final class Hero extends WidgetDefinition
 *     {
 *         public string $name = 'hero';
 *         public int $version = 2;
 *
 *         public function schema(Schema $s): Schema
 *         {
 *             return $s->string('title')->required()->maxLength(120);
 *         }
 *
 *         public function upgrade(int $from, array $config): array
 *         {
 *             return $config; // migrate config stored at version $from to $from + 1
 *         }
 *     }
 *
 * Every frontend renders the widget with its own component registered under
 * the same name.
 */
abstract class WidgetDefinition
{
    /** Unique name, e.g. "hero" (lowercase, digits, hyphens and dots) */
    public string $name;

    /** Version of the config schema; bump it and add an upgrade() step when the config shape changes */
    public int $version = 1;

    /** Name shown in the editor */
    public ?string $label = null;

    /** Group in the editor's widget list */
    public ?string $category = null;

    /**
     * Describe the config with the builder, or return Schema::raw([...]).
     *
     * @param Schema $s
     * @return Schema
     */
    abstract public function schema(Schema $s): Schema;

    /**
     * A valid config showing what the widget does; used by editors (new
     * instances), documentation and renderer conformance fixtures.
     *
     * @return array
     */
    public function example(): array
    {
        return [];
    }

    /**
     * Upgrade a config stored at version $from by one version, to $from + 1.
     *
     * @param int $from
     * @param array $config
     * @return array
     */
    public function upgrade(int $from, array $config): array
    {
        return $config;
    }
}
