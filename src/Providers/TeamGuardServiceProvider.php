<?php

namespace BurakDalyanda\TeamGuard\Providers;

use Illuminate\Support\ServiceProvider;

class TeamGuardServiceProvider extends ServiceProvider {
    public function boot(): void
    {
        $this->offerPublishing();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/team-guard.php', 'team-guard');
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
            __DIR__.'/../../config/team-guard.php' => config_path('team-guard.php'),
        ], 'config');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
