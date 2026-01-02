<?php
namespace Andmarruda\Lpb\Providers;

use Illuminate\Support\ServiceProvider;
use Andmarruda\Lpb\Repositories\EloquentRepository;
use Andmarruda\Lpb\Repositories\MongoRepository;
use Andmarruda\Lpb\Contracts\RepositoryInterface;

class DatabaseAbstractionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/laravel-page-builder.php',
            'laravel-page-builder'
        );

        $this->app->bind(RepositoryInterface::class, function ($app) {
            $driver = config('laravel-page-builder.database_driver', 'eloquent');

            $modelClass = $this->resolveModelClass($driver);
            $model = $app->make($modelClass);

            return match ($driver) {
                'mongo' => new MongoRepository($model),
                'eloquent' => new EloquentRepository($model),
                default => new EloquentRepository($model),
            };
        });
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

            // Publish MongoDB migrations
            $this->publishes([
                __DIR__ . '/../../database/migrations/mongodb' => database_path('migrations'),
            ], 'lpb-migrations-mongodb');
        }
    }

    protected function resolveModelClass(): string
    {
        return config('laravel-page-builder.default_model', \App\Models\Page::class);
    }
}
