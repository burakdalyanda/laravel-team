<?php

namespace BurakDalyanda\TeamGuard;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class TeamGuardServiceProvider extends ServiceProvider {
    public function boot(): void
    {
        $this->offerPublishing();

        $this->registerCommands();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/team-guard.php', 'team-guard');
    }

    protected function offerPublishing(): void {
        if (! $this->app->runningInConsole()) {
            return;
        }

        if (! function_exists('config_path')) {
            // function not available and 'publish' not relevant in Lumen
            return;
        }

        $this->publishes([
            __DIR__ . '/../../config/team-guard.php' => config_path('team-guard.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_teams_tables.php.stub' => $this->getMigrationFileName('create_teams_tables.php'),
        ], 'teams-migrations');
    }

    protected function registerCommands(): void
    {
        $this->commands([
            Commands\CacheReset::class
        ]);
    }

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     */
    protected function getMigrationFileName(string $migrationFileName): string
    {
        $timestamp = date('Y_m_d_His');

        $filesystem = $this->app->make(Filesystem::class);

        return Collection::make([$this->app->databasePath().DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR])
            ->flatMap(fn ($path) => $filesystem->glob($path.'*_'.$migrationFileName))
            ->push($this->app->databasePath()."/migrations/{$timestamp}_{$migrationFileName}")
            ->first();
    }
}
