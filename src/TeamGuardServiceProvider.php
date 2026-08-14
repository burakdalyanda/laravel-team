<?php

declare(strict_types=1);

namespace BurakDalyanda\TeamGuard;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

final class TeamGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/team-guard.php', 'team-guard');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/team-guard.php' => config_path('team-guard.php'),
        ], 'team-guard-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_teams_tables.php.stub' => $this->migrationPath(),
        ], 'team-guard-migrations');
    }

    private function migrationPath(): string
    {
        $migration = 'create_teams_tables.php';
        $directory = $this->app->databasePath('migrations');
        $files = $this->app->make(Filesystem::class);

        return (string) Collection::make($files->glob($directory.'/*_'.$migration))
            ->push($directory.'/'.date('Y_m_d_His').'_'.$migration)
            ->first();
    }
}
