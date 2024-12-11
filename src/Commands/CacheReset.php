<?php

namespace BurakDalyanda\TeamGuard\Commands;

use Illuminate\Console\Command;

class CacheReset extends Command {
    protected $signature = 'team:cache-reset';

    protected $description = 'Reset the team cache';

    public function handle() {
        $teamRegistrar = app(TeamRegistrar::class);
        $cacheExists = $teamRegistrar->getCacheRepository()->has($teamRegistrar->cacheKey);

        if ($teamRegistrar->forgetCachedTeams()) {
            $this->info('Team cache flushed.');
        } elseif ($cacheExists) {
            $this->error('Unable to flush cache.');
        }
    }
}
