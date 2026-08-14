<?php

declare(strict_types=1);

namespace BurakDalyanda\TeamGuard\Traits;

use BurakDalyanda\TeamGuard\Events\TeamAssigned;
use BurakDalyanda\TeamGuard\Events\TeamRemoved;
use BurakDalyanda\TeamGuard\Events\TeamsSynced;
use BurakDalyanda\TeamGuard\Models\Team;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use LogicException;

/**
 * @mixin Model
 */
trait HasTeams
{
    public static function bootHasTeams(): void
    {
        static::deleting(static function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->teams()->detach();
        });
    }

    public function teams(): MorphToMany
    {
        return $this->morphToMany(
            $this->getTeamsClass(),
            (string) config('team-guard.morph_name', 'model'),
            (string) config('team-guard.table_names.model_has_teams', 'model_has_teams'),
            (string) config('team-guard.column_names.model_morph_key', 'model_id'),
            (string) config('team-guard.column_names.team_pivot_key', 'team_id'),
        );
    }

    /**
     * @return class-string<Model>
     */
    public function getTeamsClass(): string
    {
        $class = config('team-guard.models.team', Team::class);

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            throw new LogicException('team-guard.models.team must be an Eloquent model class.');
        }

        return $class;
    }

    public function assignTeam(Model|int|string|array ...$teams): static
    {
        $this->ensureTeamableIsPersisted();
        $resolved = $this->resolveTeams($teams);
        $changes = $this->mutateTeams(
            fn (): array => $this->teams()->syncWithoutDetaching($resolved->modelKeys()),
        );

        foreach ($changes['attached'] as $teamId) {
            $team = $resolved->first(fn (Model $team): bool => (string) $team->getKey() === (string) $teamId);

            if ($team !== null) {
                Event::dispatch(new TeamAssigned($this, $team));
            }
        }

        $this->unsetRelation('teams');

        return $this;
    }

    public function assignToTeam(Model|int|string|array ...$teams): static
    {
        return $this->assignTeam(...$teams);
    }

    public function removeTeam(Model|int|string $team): int
    {
        $this->ensureTeamableIsPersisted();
        $resolved = $this->resolveTeam($team);
        $removed = $this->mutateTeams(
            fn (): int => $this->teams()->detach($resolved->getKey()),
        );

        if ($removed > 0) {
            Event::dispatch(new TeamRemoved($this, $resolved));
        }

        $this->unsetRelation('teams');

        return $removed;
    }

    /**
     * @param  array<int, Model|int|string>  $teams
     * @return array{attached: array<int, int|string>, detached: array<int, int|string>, updated: array<int, int|string>}
     */
    public function syncTeams(array $teams): array
    {
        $this->ensureTeamableIsPersisted();
        $resolved = $this->resolveTeams($teams);
        [$existing, $changes] = $this->mutateTeams(function () use ($resolved): array {
            $existing = $this->teams()->get()->keyBy(fn (Model $team): string => (string) $team->getKey());
            $changes = $this->teams()->sync($resolved->modelKeys());

            return [$existing, $changes];
        });

        foreach ($changes['attached'] as $teamId) {
            $team = $resolved->first(fn (Model $team): bool => (string) $team->getKey() === (string) $teamId);

            if ($team !== null) {
                Event::dispatch(new TeamAssigned($this, $team));
            }
        }

        foreach ($changes['detached'] as $teamId) {
            $team = $existing->get((string) $teamId);

            if ($team !== null) {
                Event::dispatch(new TeamRemoved($this, $team));
            }
        }

        if ($changes['attached'] !== [] || $changes['detached'] !== [] || $changes['updated'] !== []) {
            Event::dispatch(new TeamsSynced($this, $changes));
        }

        $this->unsetRelation('teams');

        return $changes;
    }

    /**
     * @param  array<int, Model|int|string>  $teams
     * @return array{attached: array<int, int|string>, detached: array<int, int|string>, updated: array<int, int|string>}
     */
    public function syncTeam(array $teams): array
    {
        return $this->syncTeams($teams);
    }

    /**
     * @param  array<int, Model|int|string>  $teams
     * @return array{attached: array<int, int|string>, detached: array<int, int|string>, updated: array<int, int|string>}
     */
    public function syncTeamsWithoutDetaching(array $teams): array
    {
        $this->ensureTeamableIsPersisted();
        $resolved = $this->resolveTeams($teams);
        $changes = $this->mutateTeams(
            fn (): array => $this->teams()->syncWithoutDetaching($resolved->modelKeys()),
        );

        foreach ($changes['attached'] as $teamId) {
            $team = $resolved->first(fn (Model $team): bool => (string) $team->getKey() === (string) $teamId);

            if ($team !== null) {
                Event::dispatch(new TeamAssigned($this, $team));
            }
        }

        $this->unsetRelation('teams');

        return $changes;
    }

    /**
     * @param  array<int, Model|int|string>  $teams
     * @return array{attached: array<int, int|string>, detached: array<int, int|string>, updated: array<int, int|string>}
     */
    public function syncTeamWithoutDetach(array $teams): array
    {
        return $this->syncTeamsWithoutDetaching($teams);
    }

    public function hasTeam(Model|int|string $team): bool
    {
        return $this->teams()->whereKey($this->resolveTeam($team)->getKey())->exists();
    }

    public function hasAnyTeam(Model|int|string|array ...$teams): bool
    {
        $ids = $this->resolveTeams($teams)->modelKeys();

        return $ids !== [] && $this->teams()->whereKey($ids)->exists();
    }

    public function hasAllTeams(Model|int|string|array ...$teams): bool
    {
        $ids = array_values(array_unique($this->resolveTeams($teams)->modelKeys(), SORT_REGULAR));

        return $ids !== [] && $this->teams()->whereKey($ids)->count() === count($ids);
    }

    /**
     * @return Collection<int, int|string>
     */
    public function getTeamIds(): Collection
    {
        return $this->teams()->pluck($this->teams()->getRelated()->getQualifiedKeyName());
    }

    public function firstTeam(): ?Model
    {
        return $this->teams()->first();
    }

    /**
     * @deprecated Use firstTeam().
     */
    public function getModelTeam(): ?Model
    {
        return $this->firstTeam();
    }

    public function scopeWhereTeam(Builder $query, Model|int|string|array $teams): Builder
    {
        $items = is_array($teams) ? $teams : [$teams];

        return $this->scopeWhereAnyTeam($query, ...$items);
    }

    public function scopeWhereAnyTeam(Builder $query, Model|int|string|array ...$teams): Builder
    {
        $ids = $this->resolveTeams($teams)->modelKeys();

        return $query->whereHas('teams', fn (Builder $teamQuery): Builder => $teamQuery->whereKey($ids));
    }

    public function scopeWhereAllTeams(Builder $query, Model|int|string|array ...$teams): Builder
    {
        $teamIds = $this->resolveTeams($teams)->modelKeys();

        if ($teamIds === []) {
            return $query->whereRaw('0 = 1');
        }

        foreach ($teamIds as $teamId) {
            $query->whereHas('teams', fn (Builder $teamQuery): Builder => $teamQuery->whereKey($teamId));
        }

        return $query;
    }

    public function scopeWithoutTeams(Builder $query): Builder
    {
        return $query->whereDoesntHave('teams');
    }

    /**
     * @param  array<int, Model|int|string|array>  $teams
     * @return EloquentCollection<int, Model>
     */
    private function resolveTeams(array $teams): EloquentCollection
    {
        $resolved = collect($teams)
            ->flatten()
            ->map(fn (mixed $team): Model => $this->resolveTeam($team))
            ->unique(fn (Model $team): string => (string) $team->getKey())
            ->values();

        return new EloquentCollection($resolved->all());
    }

    private function resolveTeam(mixed $team): Model
    {
        $class = $this->getTeamsClass();

        if ($team instanceof $class) {
            if (! $team->exists) {
                throw new InvalidArgumentException('A team must be persisted before it can be assigned.');
            }

            return $team;
        }

        if (is_int($team) || (is_string($team) && ctype_digit($team))) {
            return $class::query()->findOrFail($team);
        }

        if (is_string($team) && $team !== '') {
            return $class::query()->where('name', $team)->firstOrFail();
        }

        throw new InvalidArgumentException('A team must be a persisted team model, numeric ID, or non-empty name.');
    }

    private function ensureTeamableIsPersisted(): void
    {
        if (! $this->exists) {
            throw new LogicException('The model must be persisted before teams can be assigned.');
        }
    }

    /**
     * Serialize membership mutations for the same teamable model and make
     * multi-query sync operations atomic.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    private function mutateTeams(Closure $callback): mixed
    {
        return $this->getConnection()->transaction(function () use ($callback): mixed {
            $this->newQueryWithoutScopes()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return $callback();
        });
    }
}
