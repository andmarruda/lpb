<?php
namespace Andmarruda\Lpb\Widgets;

use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\Exceptions\TreeValidationException;
use Andmarruda\Lpb\Validation\ConfigValidator;
use Andmarruda\Lpb\Validation\JsonPointer;
use Illuminate\Contracts\Container\Container;

/**
 * Widget definitions registered in code, by name.
 *
 * Definitions listed in laravel-page-builder.widgets.definitions are
 * registered on first use; others with register().
 */
class WidgetRegistry
{
    private const NAME_PATTERN = '/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/';

    /** @var array<string, WidgetDefinition> */
    private array $definitions = [];

    /** @var array<string, array> */
    private array $schemas = [];

    private bool $configLoaded = false;

    public function __construct(private Container $container, private ConfigValidator $validator)
    {
    }

    /**
     * @param class-string<WidgetDefinition>|WidgetDefinition $definition
     * @return $this
     */
    public function register(string|WidgetDefinition $definition): static
    {
        // Configured definitions first, so an explicit registration replaces one
        $this->loadConfigured();

        $definition = is_string($definition) ? $this->container->make($definition) : $definition;
        if (!$definition instanceof WidgetDefinition) {
            throw new InvalidValueException('Widgets must extend ' . WidgetDefinition::class . '.');
        }
        if (!isset($definition->name) || !preg_match(self::NAME_PATTERN, $definition->name)) {
            throw new InvalidValueException('Widget name ' . json_encode($definition->name ?? null) . ' must be lowercase letters and digits separated by hyphens or dots.');
        }
        if ($definition->version < 1) {
            throw new InvalidValueException("Widget '{$definition->name}' version must be 1 or higher.");
        }

        $this->definitions[$definition->name] = $definition;
        unset($this->schemas[$definition->name]);
        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    public function get(string $name): ?WidgetDefinition
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * @return array<string, WidgetDefinition> Definitions by name, sorted by name
     */
    public function all(): array
    {
        $this->loadConfigured();
        ksort($this->definitions);
        return $this->definitions;
    }

    /**
     * The JSON Schema of a widget's config.
     *
     * @param string $name
     * @return array
     */
    public function schema(string $name): array
    {
        $definition = $this->get($name) ?? throw new InvalidValueException("Widget '{$name}' is not registered.");
        return $this->schemas[$name] ??= $definition->schema(Schema::make())->toArray();
    }

    /**
     * Definitions as exchanged with editors and tooling.
     *
     * @return array<int, array{name: string, version: int, label: string, category: string|null, schema: array}>
     */
    public function describe(): array
    {
        return array_values(array_map(fn (WidgetDefinition $definition) => [
            'name' => $definition->name,
            'version' => $definition->version,
            'label' => $definition->label ?? ucfirst(str_replace(['-', '.'], ' ', $definition->name)),
            'category' => $definition->category,
            'schema' => $this->schema($definition->name),
            'example' => $definition->example(),
        ], $this->all()));
    }

    /**
     * Upgrade a stored config to the definition's version.
     *
     * Unknown widgets and configs already at (or above) the current version
     * are returned unchanged.
     *
     * @param string $name
     * @param array $config
     * @param int $from Stored schema_version
     * @return array{0: array, 1: int} The config and its schema_version
     */
    public function upgrade(string $name, array $config, int $from): array
    {
        $definition = $this->get($name);
        if (!$definition || $from >= $definition->version) {
            return [$config, $from];
        }

        for ($version = max(1, $from); $version < $definition->version; $version++) {
            $config = $definition->upgrade($version, $config);
        }
        return [$config, $definition->version];
    }

    /**
     * Errors of a widget instance about to be written.
     *
     * A config older than the definition is upgraded first; the result is
     * validated against the current schema.
     *
     * @param string $name
     * @param mixed $config
     * @param int|null $schemaVersion Null for the current version
     * @param string $path JSON pointer of the widget instance
     * @return array{0: array, 1: int, 2: array} The upgraded config, its schema_version and the errors
     */
    public function check(string $name, mixed $config, ?int $schemaVersion, string $path = ''): array
    {
        $definition = $this->get($name);
        if (!$definition) {
            return [[], 0, [['path' => JsonPointer::append($path, 'widget'), 'message' => "Widget '{$name}' is not registered."]]];
        }

        $configPath = JsonPointer::append($path, 'config');
        if (!is_array($config)) {
            return [[], 0, [['path' => $configPath, 'message' => 'Widget config must be an object.']]];
        }

        $schemaVersion ??= $definition->version;
        if ($schemaVersion < 1 || $schemaVersion > $definition->version) {
            return [[], 0, [['path' => JsonPointer::append($path, 'schema_version'), 'message' => "Widget '{$name}' schema_version must be between 1 and {$definition->version}."]]];
        }

        [$config, $version] = $this->upgrade($name, $config, $schemaVersion);
        return [$config, $version, $this->validator->validate($config, $this->schema($name), $configPath)];
    }

    /**
     * Validate a widget instance about to be written.
     *
     * @param string $name
     * @param array $config
     * @param int|null $schemaVersion Null for the current version
     * @return array{0: array, 1: int} The upgraded config and its schema_version
     * @throws TreeValidationException
     */
    public function validate(string $name, array $config, ?int $schemaVersion = null): array
    {
        [$config, $version, $errors] = $this->check($name, $config, $schemaVersion);
        if ($errors !== []) {
            throw new TreeValidationException($errors);
        }
        return [$config, $version];
    }

    private function loadConfigured(): void
    {
        if ($this->configLoaded) {
            return;
        }
        $this->configLoaded = true;

        $config = $this->container->bound('config') ? $this->container->make('config') : null;
        $configured = (array) ($config?->get('laravel-page-builder.widgets.definitions', []) ?? []);
        if ($config?->get('laravel-page-builder.widgets.library', true)) {
            $configured = [...Library\Library::DEFINITIONS, ...$configured];
        }
        foreach ($configured as $definition) {
            $this->register($definition);
        }
    }
}
