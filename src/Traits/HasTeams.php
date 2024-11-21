<?php

namespace BurakDalyanda\TeamGuard\Traits;

use BurakDalyanda\TeamGuard\Models\Teams;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Trait HasTeams
 *
 * @package BurakDalyanda\TeamGuard\Traits
 * @package Illuminate\Database\Eloquent\Model
 *
 * @method static Builder withTeam(array $allPermissions = [], string $localColumnName = 'created_by', bool $includeSuperAdmin = false)
 * @method static Builder whereTeam(array $teamIds = [], string $localColumnName = 'created_by', array $allPermissions = [])
 *
 * @developer Burak DALYANDA
 */
trait HasTeams {
    /**
     * Boot the HasTeams trait for a model.
     *
     * @return void
     */
    public static function bootTeams(): void
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }
            $model->teams()->detach();
        });

        static::updated(function ($model) {
            $model->clearTeamsCache();
        });
    }

    public function teams(): MorphToMany
    {
        return $this->morphToMany(
            Teams::class,
            'model',
            'model_has_teams',
            'model_id',
            'team_id'
        );
    }
    /**
     * Assign the given team to the model.
     *
     * @param array|int $teams
     * @return $this
     */
    public function assignTeam(...$teams): static
    {
        $teams = collect($teams)
            ->flatten()
            ->map(function ($team) {
                if (empty($team)) {
                    return false;
                }

                if(is_numeric($team)){
                    return $team;
                }

                return false;
            })
            ->filter()
            ->all();

        $this->teams()->attach($teams, ['model_type' => self::class]);

        $this->clearTeamsCache();

        return $this;
    }

    /**
     * Remove the given team from the model.
     *
     * @param $team
     * @return int
     */
    public function removeStructure($team): int
    {
        $result = $this->teams()->detach($team);

        $this->clearTeamsCache();

        return $result;
    }

    /**
     * Sync the given teams to the model.
     *
     * @param array $teams
     * @return array
     */
    public function syncTeam(array $teams): array
    {
        $teams = collect($teams)
            ->flatten()
            ->map(function ($team) {
                if (empty($team)) {
                    return false;
                }

                if(is_numeric($team)){
                    return $team;
                }

                return false;
            })
            ->filter()
            ->all();

        $result = $this->teams()->sync(array_fill_keys(
            $teams,
            ['model_type' => self::class]
        ));

        $this->clearTeamsCache();

        return $result;
    }

    /**
     * Sync the given teams to the model without detach process
     *
     * @param array $teams
     * @return array
     */
    public function syncTeamWithoutDetach(array $teams): array
    {
        $teams = collect($teams)
            ->flatten()
            ->map(function ($team) {
                if (empty($team)) {
                    return false;
                }

                if(is_numeric($team)){
                    return $team;
                }

                return false;
            })
            ->filter()
            ->all();

        $result = $this->teams()->syncWithoutDetaching(array_fill_keys(
            $teams,
            ['model_type' => self::class]
        ));

        $this->clearTeamsCache();

        return $result;
    }

    /**
     * Determine if the model has the given team.
     *
     * @param int|string|Teams $team
     * @return bool
     */
    public function hasTeam(int|string|Teams $team): bool
    {
        $team = $this->getStoredTeam($team);

        return $this->getCachedTeams()->contains('id', $team->id);
    }
}
