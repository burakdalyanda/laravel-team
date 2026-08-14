<?php

declare(strict_types=1);

namespace BurakDalyanda\TeamGuard\Tests;

use BurakDalyanda\TeamGuard\TeamGuardServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::enableForeignKeyConstraints();
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        $migration = require __DIR__.'/../database/migrations/create_teams_tables.php.stub';
        $migration->up();
    }

    /**
     * @param  mixed  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [TeamGuardServiceProvider::class];
    }

    /**
     * @param  mixed  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }
}
