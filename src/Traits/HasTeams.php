<?php

namespace BurakDalyanda\TeamGuard\Traits;

use BurakDalyanda\TeamGuard\Models\ModelHasTeam;
use BurakDalyanda\TeamGuard\Models\Teams;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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

    /**
     * Get all teams assigned to the model.
     *
     * @return BelongsToMany
     */
    public function teams(): BelongsToMany
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
    public function removeTeam($team): int
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

    /**
     * Get the IDs of the model's teams.
     *
     * @return Collection
     */
    public function getTeamIds(): Collection
    {
        $cachedTeams = $this->getCachedTeams();
        if(is_array($cachedTeams)){
            return collect($cachedTeams);
        }

        return $cachedTeams->pluck('id');
    }

    /**
     * Get the team instance.
     *
     * @return HasOneThrough
     */
    public function getModelTeam(): HasOneThrough
    {
        return $this->hasOneThrough(
            Teams::class,
            ModelHasTeam::class,
            'model_id',
            'id',
            'id',
            'team_id'
        )->where('model_type', self::class);
    }

    /**
     * Get the team instance by ID, name, or team instance.
     *
     * @param int|string|Teams $team
     * @return int|Teams
     */
    protected function getStoredTeam(int|string|Teams $team): int|Teams
    {
        if (is_numeric($team)) {
            return Teams::findOrFail($team);
        }

        if (is_string($team)) {
            return Teams::where('name', $team)->firstOrFail();
        }

        return $team;
    }

    /**
     * Get the cached teams assigned to the model.
     *
     * @return Collection|array
     */
    protected function getCachedTeams(): Collection|array
    {
        $cacheKey = $this->getCacheKey();

        return Cache::remember($cacheKey, now()->addMinutes(60), function () {
            return $this->teams()->get();
        });
    }

    /**
     * Clear the cache for the model's teams.
     */
    protected function clearTeamsCache(): void
    {
        Cache::forget($this->getCacheKey());
    }

    /**
     * Get the cache key for the model's teams.
     *
     * @return string
     */
    protected function getCacheKey(): string
    {
        return 'team-guard.' . $this::class . '.' . $this->getKey();
    }
}
