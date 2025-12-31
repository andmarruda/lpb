<?php
namespace Andmarruda\Lpb\Contracts;
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
            $this->publishes([
                __DIR__ . '/../../config/laravel-page-builder.php' => config_path('laravel-page-builder.php'),
            ], 'config');
        }
    }

    protected function resolveModelClass(): string
    {
        return config('laravel-page-builder.default_model', \App\Models\Page::class);
    }
}
