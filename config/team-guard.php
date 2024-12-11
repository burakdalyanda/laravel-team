<?php

return [
    'models' => [

        /*
         * When using the "HasTeams" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your teams. Of course, it
         * is often just the "Team" model but you may use whatever you like.
         *
         * The model you want to use as a Team model needs to implement the
         * `BurakDalyanda\TeamGuard\Contracts\Team` contract.
         */
        'team' => BurakDalyanda\TeamGuard\Models\Team::class,

        /*
         * When using the "HasTeams" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your users. Of course, it
         * is often just the "User" model but you may use whatever you like.
         *
         * The model you want to use as a User model needs to implement the
         * `BurakDalyanda\TeamGuard\Contracts\User` contract.
         */
        'user' => App\Models\User::class,
    ],

    'table_names' => [

        /*
         * When using the "HasTeams" trait from this package, we need to know which
         * table should be used to retrieve your teams. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */
        'teams' => 'teams',

        /*
         * When using the "HasTeams" trait from this package, we need to know which
         * table should be used to retrieve your users. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */
        'model_has_teams' => 'model_has_teams',
    ],

    'column_names' => [

        /*
         * When using the "HasTeams" trait from this package, we need to know which
         * column should be used to retrieve your team's primary key. We have chosen
         * a basic default value but you may easily change it to any column you like.
         */
        'team_pivot_key' => 'team_id',

        /*
         * When using the "HasTeams" trait from this package, we need to know which
         * column should be used to retrieve your model's primary key. We have chosen
         * a basic default value but you may easily change it to any column you like.
         */
        'model_morph_key' => 'model_id',

        /*
         * When using the "HasTeams" trait from this package, we need to know which
         * column should be used to retrieve your team's foreign key. We have chosen
         * a basic default value but you may easily change it to any column you like.
         */
        'team_foreign_key' => 'team_id',
    ],

    /*
     * When using the "HasTeams" trait from this package, we need to know which
     * table should be used to retrieve your teams. We have chosen a basic
     * default value but you may easily change it to any table you like.
     */
    'spatie_permission_package_feature' => false,

    'cache' => [

        /*
         * By default all teams are cached for 24 hours to speed up performance.
         * When teams are updated the cache is flushed automatically.
         */
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),

        /*
         * The cache key used to store all teams.
         */
        'key' => 'teamguard.team.cache',

        /*
         * You may optionally indicate a specific cache driver to use for teams
         * caching using any of the `store` drivers listed in the cache.php config
         * file. Using 'default' here means to use the `default` set in cache.php.
         */
        'driver' => 'default',
    ],
];
