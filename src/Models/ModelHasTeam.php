<?php

namespace BurakDalyanda\TeamGuard\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ModelHasTeam extends Pivot
{
    protected $table = 'model_has_teams';

    protected $fillable = [
        'team_id',
        'model_type',
        'model_id'
    ];

    public $timestamps = false;

    /**
     * @return BelongsTo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(config('teamguard.models.team'), 'team_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(self::class, 'model_id');
    }

    public function modelType(): BelongsTo
    {
        return $this->belongsTo(self::class, 'model_type');
    }
}
