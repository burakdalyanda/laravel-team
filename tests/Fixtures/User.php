<?php

declare(strict_types=1);

namespace BurakDalyanda\TeamGuard\Tests\Fixtures;

use BurakDalyanda\TeamGuard\Traits\HasTeams;
use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    use HasTeams;

    public $timestamps = false;

    protected $guarded = [];
}
