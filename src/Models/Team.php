<?php

declare(strict_types=1);

namespace BurakDalyanda\TeamGuard\Models;

use BurakDalyanda\TeamGuard\Enums\TeamStatus;
use BurakDalyanda\TeamGuard\Exceptions\InvalidTeamHierarchy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $parent_id
 * @property TeamStatus $status
 * @property int $sort_order
 * @property-read Team|null $parent
 * @property-read Collection<int, Team> $children
 */
class Team extends Model
{
    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'status',
        'sort_order',
    ];

    public function getTable(): string
    {
        return (string) config('team-guard.table_names.teams', parent::getTable());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'sort_order' => 'integer',
            'status' => TeamStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $team): void {
            $team->assertValidParent();
        });
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo($this->teamModelClass(), 'parent_id');
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany($this->teamModelClass(), 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * @return Collection<int, static>
     */
    public function ancestors(): Collection
    {
        $ancestors = $this->newCollection();
        $seen = [];
        $current = $this->parent;

        while ($current !== null) {
            $key = (string) $current->getKey();

            if (isset($seen[$key])) {
                throw new InvalidTeamHierarchy('A cycle exists in the stored team hierarchy.');
            }

            $seen[$key] = true;
            $ancestors->push($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * @return Collection<int, static>
     */
    public function descendants(): Collection
    {
        $descendants = $this->newCollection();
        $pending = $this->children()->get();
        $seen = [(string) $this->getKey() => true];

        while ($pending->isNotEmpty()) {
            /** @var static $child */
            $child = $pending->shift();
            $key = (string) $child->getKey();

            if (isset($seen[$key])) {
                throw new InvalidTeamHierarchy('A cycle exists in the stored team hierarchy.');
            }

            $seen[$key] = true;
            $descendants->push($child);
            $pending->push(...$child->children()->get());
        }

        return $descendants;
    }

    public function isAncestorOf(self $team): bool
    {
        return $team->ancestors()->contains(fn (self $ancestor): bool => $ancestor->is($this));
    }

    public function isDescendantOf(self $team): bool
    {
        return $this->ancestors()->contains(fn (self $ancestor): bool => $ancestor->is($team));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TeamStatus::Active->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    private function assertValidParent(): void
    {
        if ($this->parent_id === null) {
            return;
        }

        if ($this->exists && (string) $this->parent_id === (string) $this->getKey()) {
            throw new InvalidTeamHierarchy('A team cannot be its own parent.');
        }

        $seen = [];
        $parent = $this->newQuery()->find($this->parent_id);

        while ($parent !== null) {
            if ($this->exists && $parent->is($this)) {
                throw new InvalidTeamHierarchy('A team cannot be moved below one of its descendants.');
            }

            $key = (string) $parent->getKey();

            if (isset($seen[$key])) {
                throw new InvalidTeamHierarchy('A cycle exists in the stored team hierarchy.');
            }

            $seen[$key] = true;
            $parent = $parent->parent()->first();
        }
    }

    /**
     * @return class-string<Model>
     */
    private function teamModelClass(): string
    {
        $class = config('team-guard.models.team', self::class);

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            throw new LogicException('team-guard.models.team must be an Eloquent model class.');
        }

        return $class;
    }
}
