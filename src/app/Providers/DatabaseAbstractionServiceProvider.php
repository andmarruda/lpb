<?php
namespace Andmarruda\Lpb\Providers;

use Illuminate\Support\ServiceProvider;
use Andmarruda\Lpb\Console\InstallMongoCommand;
use Andmarruda\Lpb\Console\MakeWidgetCommand;
use Andmarruda\Lpb\Console\TypesCommand;
use Andmarruda\Lpb\Console\UpgradeContentCommand;
use Andmarruda\Lpb\Console\WidgetsSchemaCommand;
use Andmarruda\Lpb\Lpb;
use Andmarruda\Lpb\Widgets\WidgetRegistry;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Andmarruda\Lpb\Contracts\BlockRepositoryInterface;
use Andmarruda\Lpb\Contracts\MenuRepositoryInterface;
use Andmarruda\Lpb\Contracts\MediaRepositoryInterface;
use Andmarruda\Lpb\Contracts\RevisionRepositoryInterface;
use Andmarruda\Lpb\Contracts\TemplateRepositoryInterface;
use Andmarruda\Lpb\Repositories\Sql;
use Andmarruda\Lpb\Repositories\Mongo;

class DatabaseAbstractionServiceProvider extends ServiceProvider
{
    /**
     * Repository contracts mapped to their [SQL, MongoDB] implementations.
     *
     * @var array<class-string, array{class-string, class-string}>
     */
    protected array $repositories = [
        PageRepositoryInterface::class        => [Sql\PageRepository::class, Mongo\PageRepository::class],
        PageTreeRepositoryInterface::class    => [Sql\PageTreeRepository::class, Mongo\PageTreeRepository::class],
        TemplateRepositoryInterface::class    => [Sql\TemplateRepository::class, Mongo\TemplateRepository::class],
        BlockRepositoryInterface::class       => [Sql\BlockRepository::class, Mongo\BlockRepository::class],
        MenuRepositoryInterface::class        => [Sql\MenuRepository::class, Mongo\MenuRepository::class],
        MediaRepositoryInterface::class       => [Sql\MediaRepository::class, Mongo\MediaRepository::class],
        RevisionRepositoryInterface::class    => [Sql\RevisionRepository::class, Mongo\RevisionRepository::class],
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/laravel-page-builder.php',
            'laravel-page-builder'
        );

        $useMongo = $this->usesMongo();

        foreach ($this->repositories as $contract => [$sqlImplementation, $mongoImplementation]) {
            $this->app->bind($contract, $useMongo ? $mongoImplementation : $sqlImplementation);
        }

        $this->app->singleton(WidgetRegistry::class);
        $this->app->singleton(Lpb::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../../config/laravel-page-builder.php' => config_path('laravel-page-builder.php'),
            ], 'lpb-config');

            // Publish SQL migrations
            $this->publishes([
                __DIR__ . '/../../database/migrations/sql' => database_path('migrations'),
            ], 'lpb-migrations-sql');

            // MongoDB collections, validators and indexes are created by a command instead of migrations
            $this->commands([
                InstallMongoCommand::class,
                MakeWidgetCommand::class,
                TypesCommand::class,
                UpgradeContentCommand::class,
                WidgetsSchemaCommand::class,
            ]);
        }
    }

    /**
     * Whether the configured driver is MongoDB ('mongodb', or the 'mongo' alias).
     *
     * @return bool
     */
    protected function usesMongo(): bool
    {
        return in_array(config('laravel-page-builder.database_driver', 'sql'), ['mongodb', 'mongo'], true);
    }
}
