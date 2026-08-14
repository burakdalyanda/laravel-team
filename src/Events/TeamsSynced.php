<?php

declare(strict_types=1);

namespace BurakDalyanda\TeamGuard\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;

final readonly class TeamsSynced implements ShouldDispatchAfterCommit
{
    /**
     * @param  array{attached: array<int, int|string>, detached: array<int, int|string>, updated: array<int, int|string>}  $changes
     */
    public function __construct(
        public Model $model,
        public array $changes,
    ) {}
}
