<?php

declare(strict_types=1);

namespace BurakDalyanda\TeamGuard\Tests\Feature;

use BurakDalyanda\TeamGuard\Events\TeamAssigned;
use BurakDalyanda\TeamGuard\Events\TeamRemoved;
use BurakDalyanda\TeamGuard\Events\TeamsSynced;
use BurakDalyanda\TeamGuard\Models\Team;
use BurakDalyanda\TeamGuard\Tests\Fixtures\User;
use BurakDalyanda\TeamGuard\Tests\TestCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

final class TeamMembershipTest extends TestCase
{
    public function test_it_assigns_teams_by_model_id_and_name_without_duplicates(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        $engineering = Team::query()->create(['name' => 'Engineering']);
        $support = Team::query()->create(['name' => 'Support']);

        $user->assignTeam($engineering, $support->getKey(), 'Engineering', $engineering);

        self::assertEqualsCanonicalizing(
            [$engineering->getKey(), $support->getKey()],
            $user->getTeamIds()->all(),
        );
        self::assertCount(2, $user->teams()->get());
    }

    public function test_assignment_is_idempotent_and_dispatches_only_for_new_memberships(): void
    {
        Event::fake([TeamAssigned::class]);
        $user = User::query()->create(['name' => 'Ada']);
        $team = Team::query()->create(['name' => 'Engineering']);

        $user->assignToTeam($team);
        $user->assignToTeam($team);

        Event::assertDispatchedTimes(TeamAssigned::class, 1);
        Event::assertDispatched(
            TeamAssigned::class,
            fn (TeamAssigned $event): bool => $event->model->is($user) && $event->team->is($team),
        );
        self::assertCount(1, $user->teams()->get());
    }

    public function test_it_removes_a_team_and_emits_an_event_only_when_membership_existed(): void
    {
        Event::fake([TeamRemoved::class]);
        $user = User::query()->create(['name' => 'Ada']);
        $team = Team::query()->create(['name' => 'Engineering']);
        $user->assignTeam($team);

        self::assertSame(1, $user->removeTeam($team));
        self::assertSame(0, $user->removeTeam($team));

        Event::assertDispatchedTimes(TeamRemoved::class, 1);
        self::assertFalse($user->hasTeam($team));
    }

    public function test_sync_replaces_memberships_and_reports_observable_changes(): void
    {
        Event::fake([TeamAssigned::class, TeamRemoved::class, TeamsSynced::class]);
        $user = User::query()->create(['name' => 'Ada']);
        $engineering = Team::query()->create(['name' => 'Engineering']);
        $support = Team::query()->create(['name' => 'Support']);
        $sales = Team::query()->create(['name' => 'Sales']);
        $user->assignTeam($engineering, $support);
        Event::fake([TeamAssigned::class, TeamRemoved::class, TeamsSynced::class]);

        $changes = $user->syncTeams([$support, $sales]);

        self::assertEqualsCanonicalizing([$sales->getKey()], $changes['attached']);
        self::assertEqualsCanonicalizing([$engineering->getKey()], $changes['detached']);
        self::assertTrue($user->hasAllTeams($support, $sales));
        self::assertFalse($user->hasTeam($engineering));
        Event::assertDispatchedTimes(TeamAssigned::class, 1);
        Event::assertDispatchedTimes(TeamRemoved::class, 1);
        Event::assertDispatchedTimes(TeamsSynced::class, 1);
    }

    public function test_sync_without_detaching_preserves_existing_memberships(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        $engineering = Team::query()->create(['name' => 'Engineering']);
        $support = Team::query()->create(['name' => 'Support']);
        $user->assignTeam($engineering);

        $user->syncTeamWithoutDetach([$support]);

        self::assertTrue($user->hasAllTeams($engineering, $support));
    }

    public function test_membership_queries_filter_by_any_all_and_missing_teams(): void
    {
        $engineering = Team::query()->create(['name' => 'Engineering']);
        $support = Team::query()->create(['name' => 'Support']);
        $both = User::query()->create(['name' => 'Both']);
        $engineeringOnly = User::query()->create(['name' => 'Engineering only']);
        $unassigned = User::query()->create(['name' => 'Unassigned']);
        $both->assignTeam($engineering, $support);
        $engineeringOnly->assignTeam($engineering);

        self::assertEqualsCanonicalizing(
            [$both->getKey(), $engineeringOnly->getKey()],
            User::query()->whereAnyTeam($engineering)->pluck('id')->all(),
        );
        self::assertSame([$both->getKey()], User::query()->whereAllTeams($engineering, $support)->pluck('id')->all());
        self::assertSame([$unassigned->getKey()], User::query()->withoutTeams()->pluck('id')->all());
        self::assertSame([], User::query()->whereAllTeams([])->pluck('id')->all());
    }

    public function test_deleting_a_model_removes_its_polymorphic_memberships(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        $team = Team::query()->create(['name' => 'Engineering']);
        $user->assignTeam($team);

        $user->delete();

        $this->assertDatabaseCount('model_has_teams', 0);
        $this->assertDatabaseHas('teams', ['id' => $team->getKey()]);
    }

    public function test_deleting_a_team_cascades_its_memberships_without_deleting_models(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        $team = Team::query()->create(['name' => 'Engineering']);
        $user->assignTeam($team);

        $team->delete();

        $this->assertDatabaseCount('model_has_teams', 0);
        $this->assertDatabaseHas('users', ['id' => $user->getKey()]);
    }

    public function test_events_are_not_observed_when_an_outer_transaction_rolls_back(): void
    {
        Event::fake([TeamAssigned::class]);
        $user = User::query()->create(['name' => 'Ada']);
        $team = Team::query()->create(['name' => 'Engineering']);

        try {
            DB::transaction(function () use ($team, $user): never {
                $user->assignTeam($team);

                throw new RuntimeException('Rollback the outer transaction.');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('Rollback the outer transaction.', $exception->getMessage());
        }

        $this->assertDatabaseCount('model_has_teams', 0);
        Event::assertNotDispatched(TeamAssigned::class);
    }

    public function test_it_rejects_an_unpersisted_teamable_model(): void
    {
        $team = Team::query()->create(['name' => 'Engineering']);

        $this->expectException(LogicException::class);
        (new User(['name' => 'Unsaved']))->assignTeam($team);
    }

    public function test_it_rejects_an_empty_team_identifier(): void
    {
        Team::query()->create(['name' => 'Engineering']);

        $user = User::query()->create(['name' => 'Ada']);

        $this->expectException(InvalidArgumentException::class);
        $user->assignTeam('');
    }

    public function test_unknown_team_names_fail_instead_of_creating_implicit_teams(): void
    {
        $user = User::query()->create(['name' => 'Ada']);

        $this->expectException(ModelNotFoundException::class);
        $user->assignTeam('Unknown');
    }
}
