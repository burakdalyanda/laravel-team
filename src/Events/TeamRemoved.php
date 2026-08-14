<?php

declare(strict_types=1);

namespace BurakDalyanda\TeamGuard\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;

final readonly class TeamRemoved implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Model $model,
        public Model $team,
    ) {}
}
