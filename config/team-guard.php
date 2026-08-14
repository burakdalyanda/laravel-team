<?php

declare(strict_types=1);

use BurakDalyanda\TeamGuard\Models\Team;

return [
    'models' => [
        'team' => Team::class,
    ],

    'table_names' => [
        'teams' => 'teams',
        'model_has_teams' => 'model_has_teams',
    ],

    'column_names' => [
        'team_pivot_key' => 'team_id',
        'model_morph_key' => 'model_id',
    ],

    'morph_name' => 'model',

    /*
     * The key type used by models that receive teams. Supported values are
     * "int", "uuid", and "ulid". Publish the migration after changing it.
     */
    'model_key_type' => 'int',
];
