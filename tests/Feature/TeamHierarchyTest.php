<?php

declare(strict_types=1);

namespace BurakDalyanda\TeamGuard\Tests\Feature;

use BurakDalyanda\TeamGuard\Enums\TeamStatus;
use BurakDalyanda\TeamGuard\Exceptions\InvalidTeamHierarchy;
use BurakDalyanda\TeamGuard\Models\Team;
use BurakDalyanda\TeamGuard\Tests\TestCase;

final class TeamHierarchyTest extends TestCase
{
    public function test_it_exposes_parent_children_ancestors_and_descendants(): void
    {
        $company = Team::query()->create(['name' => 'Company', 'sort_order' => 20]);
        $engineering = Team::query()->create(['name' => 'Engineering', 'parent_id' => $company->getKey()]);
        $platform = Team::query()->create(['name' => 'Platform', 'parent_id' => $engineering->getKey()]);

        self::assertTrue($engineering->parent()->firstOrFail()->is($company));
        self::assertTrue($company->children()->firstOrFail()->is($engineering));
        self::assertEqualsCanonicalizing(
            [$engineering->getKey(), $company->getKey()],
            $platform->ancestors()->modelKeys(),
        );
        self::assertEqualsCanonicalizing(
            [$engineering->getKey(), $platform->getKey()],
            $company->descendants()->modelKeys(),
        );
        self::assertTrue($company->isAncestorOf($platform));
        self::assertTrue($platform->isDescendantOf($company));
    }

    public function test_it_prevents_a_team_from_becoming_its_own_parent(): void
    {
        $team = Team::query()->create(['name' => 'Engineering']);
        $team->parent_id = $team->getKey();

        $this->expectException(InvalidTeamHierarchy::class);
        $team->save();
    }

    public function test_it_prevents_moving_a_team_below_its_descendant(): void
    {
        $company = Team::query()->create(['name' => 'Company']);
        $engineering = Team::query()->create(['name' => 'Engineering', 'parent_id' => $company->getKey()]);
        $platform = Team::query()->create(['name' => 'Platform', 'parent_id' => $engineering->getKey()]);
        $company->parent_id = $platform->getKey();

        $this->expectException(InvalidTeamHierarchy::class);
        $company->save();
    }

    public function test_deleting_a_parent_keeps_children_and_promotes_them_to_roots(): void
    {
        $company = Team::query()->create(['name' => 'Company']);
        $engineering = Team::query()->create(['name' => 'Engineering', 'parent_id' => $company->getKey()]);

        $company->delete();

        self::assertNull($engineering->refresh()->parent_id);
        self::assertTrue(Team::query()->roots()->whereKey($engineering->getKey())->exists());
    }

    public function test_status_and_order_scopes_express_common_hierarchy_queries(): void
    {
        Team::query()->create(['name' => 'Zulu', 'sort_order' => 20, 'status' => TeamStatus::Active]);
        Team::query()->create(['name' => 'Alpha', 'sort_order' => 10, 'status' => TeamStatus::Active]);
        Team::query()->create(['name' => 'Hidden', 'sort_order' => 0, 'status' => TeamStatus::Inactive]);

        self::assertSame(
            ['Alpha', 'Zulu'],
            Team::query()->active()->ordered()->pluck('name')->all(),
        );
    }
}
