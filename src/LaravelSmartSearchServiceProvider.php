<?php

namespace Traore225\LaravelSmartSearch;

use Illuminate\Support\ServiceProvider;
use Traore225\LaravelSmartSearch\Search\SearchEngine;

class LaravelSmartSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/smart-search.php', 'smart-search');

        $this->app->singleton(SearchEngine::class, function () {
            return new SearchEngine();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/smart-search.php' => config_path('smart-search.php'),
        ], 'smart-search-config');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Traore225\LaravelSmartSearch\Console\SmartSearchInstallCommand::class,
            ]);
        }
    }
}
