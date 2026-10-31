<?php
namespace Andmarruda\Lpb\Providers;

use Illuminate\Support\ServiceProvider;
use Andmarruda\Lpb\Console\DesignBumpCommand;
use Andmarruda\Lpb\Console\InstallMongoCommand;
use Andmarruda\Lpb\Console\MakeWidgetCommand;
use Andmarruda\Lpb\Console\TypesCommand;
use Andmarruda\Lpb\Console\UpgradeContentCommand;
use Andmarruda\Lpb\Console\WidgetsSchemaCommand;
use Andmarruda\Lpb\Lpb;
use Andmarruda\Lpb\Widgets\WidgetRegistry;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Andmarruda\Lpb\Contracts\PublishingServiceInterface;
use Andmarruda\Lpb\Publishing\PublishingService;
use Andmarruda\Lpb\Contracts\BlockRepositoryInterface;
use Andmarruda\Lpb\Contracts\MenuRepositoryInterface;
use Andmarruda\Lpb\Contracts\MediaRepositoryInterface;
use Andmarruda\Lpb\Contracts\RevisionRepositoryInterface;
use Andmarruda\Lpb\Contracts\SettingsRepositoryInterface;
use Andmarruda\Lpb\Delivery\BumpDesignOnTemplateChange;
use Andmarruda\Lpb\Delivery\UpdatePointers;
use Andmarruda\Lpb\Events\TemplateChanged;
use Andmarruda\Lpb\Webhooks\NotifyWebhooks;
use Andmarruda\Lpb\Http\Shell;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        SettingsRepositoryInterface::class    => [Sql\SettingsRepository::class, Mongo\SettingsRepository::class],
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
        $this->app->bind(PublishingServiceInterface::class, PublishingService::class);
        $this->app->singleton(Lpb::class);
    }

    public function boot(): void
    {
        // Delivery pointers, design version and webhooks follow the publishing
        // events; listeners are resolved when an event fires, not at boot
        foreach ([UpdatePointers::class, NotifyWebhooks::class] as $listener) {
            foreach ($listener::listens() as $event => $method) {
                Event::listen($event, [$listener, $method]);
            }
        }
        Event::listen(TemplateChanged::class, BumpDesignOnTemplateChange::class);

        // Shell views: <x-lpb::head :page="$lpb" />, @lpbPayload and lpb::shell
        $views = __DIR__ . '/../../resources/views';
        $this->loadViewsFrom($views, 'lpb');
        Blade::anonymousComponentPath("{$views}/components", 'lpb');
        Blade::directive('lpbPayload', fn (string $expression) => '<?php echo \\' . Shell::class . '::payloadScript(' . ($expression !== '' ? $expression : '$lpb') . '); ?>');

        if (config('laravel-page-builder.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/lpb.php');
        }

        // The host application grants editing and publishing; until it does, both are denied
        foreach (['lpb.edit', 'lpb.publish'] as $ability) {
            if (!Gate::has($ability)) {
                Gate::define($ability, fn ($user = null) => false);
            }
        }

        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../../config/laravel-page-builder.php' => config_path('laravel-page-builder.php'),
            ], 'lpb-config');

            // Publish the shell views
            $this->publishes([
                __DIR__ . '/../../resources/views' => resource_path('views/vendor/lpb'),
            ], 'lpb-views');

            // Publish SQL migrations
            $this->publishes([
                __DIR__ . '/../../database/migrations/sql' => database_path('migrations'),
            ], 'lpb-migrations-sql');

            // MongoDB collections, validators and indexes are created by a command instead of migrations
            $this->commands([
                DesignBumpCommand::class,
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
