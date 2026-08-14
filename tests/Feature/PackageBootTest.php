<?php

declare(strict_types=1);

namespace BurakDalyanda\TeamGuard\Tests\Feature;

use BurakDalyanda\TeamGuard\Models\Team;
use BurakDalyanda\TeamGuard\Tests\TestCase;

final class PackageBootTest extends TestCase
{
    public function test_the_package_merges_its_default_configuration(): void
    {
        self::assertSame(Team::class, config('team-guard.models.team'));
        self::assertSame('model_has_teams', config('team-guard.table_names.model_has_teams'));
        self::assertSame('int', config('team-guard.model_key_type'));
    }
}
