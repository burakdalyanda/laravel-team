<?php

namespace BurakDalyanda\TeamGuard;

use BurakDalyanda\TeamGuard\Models\Team;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Eloquent\Collection;

class TeamsInitializer {
    protected Repository $cache;

    protected CacheManager $cacheManager;

    protected string $teamsClass;

    protected array|null|Collection $teams;

    public string $pivotTeams;

    public \DateInterval|int $cacheExpirationTime;

    public string $cacheKey;

    private array $cachedTeams;

    private array $alias = [];

    private array $except = [];

    public function __construct(CacheManager $cacheManager)
    {
        $this->teamsClass = config('team-guard.models.team');

        $this->cacheManager = $cacheManager;
        $this->initializeCache();
    }

    public function initializeCache(): void
    {
        $this->cacheExpirationTime = config('team-guard.cache.expiration_time');

        $this->cacheKey = config('team-guard.cache.key');

        $this->pivotTeams = config('team-guard.table_names.model_has_teams');

        $this->cache = $this->getCacheStoreFromConfig();
    }

    protected function getCacheStoreFromConfig(): Repository
    {
        $cacheDriver = config('team-guard.cache.driver');

        if ($cacheDriver === 'default') {
            return $this->cacheManager->store();
        }

        if (! \array_key_exists($cacheDriver, config('cache.stores'))) {
            $cacheDriver = 'array';
        }

        return $this->cacheManager->store($cacheDriver);
    }

    /**
     * Flush the cache.
     */
    public function forgetCachedTeams(): void
    {
        $this->teams = null;

        $this->cache->forget($this->cacheKey);
    }

    /**
     * Clear already-loaded teams collection.
     */
    public function clearTeamsCollection(): void
    {
        $this->teams = null;
    }

    private function loadTeams(): void
    {
        if ($this->teams){
            return;
        }

        $this->teams = $this->cache->remember(
            $this->cacheKey, $this->cacheExpirationTime, fn () => $this->getSerializedTeamsForCache(),
        );

        $this->hydrateTeamsCache();

        $this->teams = $this->getHydratedTeamsCollection();

        // TODO: Control this line
        $this->cachedTeams = $this->teams->toArray();
    }

    public function getTeamsClass(): string
    {
        return $this->teamsClass;
    }

    public function setTeamsClass(string $teamsClass)
    {
        $this->teamsClass = $teamsClass;
        config()->set('team-guard.models.team', $teamsClass);
        app()->bind(Team::class, $teamsClass);

        return $this;
    }

    public function getCacheRepository(): Repository
    {
        return $this->cache;
    }

    public function getCacheStore(): Store
    {
        return $this->cache->getStore();
    }

    /**
     * Changes array keys with alias
     */
    private function aliasedArray($model): array
    {
        return collect(is_array($model) ? $model : $model->getAttributes())->except($this->except)
            ->keyBy(fn ($value, $key) => $this->alias[$key] ?? $key)
            ->all();
    }

    /**
     * Array for cache alias
     */
    private function aliasModelFields($newKeys = []): void
    {
        $i = 0;
        $alphas = ! count($this->alias) ? range('a', 'h') : range('j', 'p');

        foreach (array_keys($newKeys->getAttributes()) as $value) {
            if (! isset($this->alias[$value])) {
                $this->alias[$value] = $alphas[$i++] ?? $value;
            }
        }

        $this->alias = array_diff_key($this->alias, array_flip($this->except));
    }

    private function getSerializedTeamsForCache(): array
    {
        $this->except = ['created_at', 'updated_at', 'deleted_at'];
    }
}
