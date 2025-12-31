<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Driver
    |--------------------------------------------------------------------------
    |
    | Choose which driver to use: 'sql' or 'mongodb'
    |
    | - 'sql': Uses MySQL/PostgreSQL/SQLite with Eloquent
    | - 'mongodb': Uses MongoDB with embedded documents for maximum performance
    |
    */
    'database_driver' => env('LPB_DATABASE_DRIVER', 'sql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connection Names
    |--------------------------------------------------------------------------
    |
    | Specify the connection names defined in your config/database.php
    |
    */
    'connections' => [
        'sql' => env('LPB_SQL_CONNECTION', env('DB_CONNECTION', 'mysql')),
        'mongodb' => env('LPB_MONGODB_CONNECTION', 'mongodb'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables/Collections Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for database tables/collections (default: lpb_)
    |
    */
    'table_prefix' => env('LPB_TABLE_PREFIX', 'lpb_'),

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure Laravel Cache (not database-level caching)
    |
    */
    'cache' => [
        'enabled' => env('LPB_CACHE_ENABLED', true),
        'ttl' => env('LPB_CACHE_TTL', 3600),
        'prefix' => env('LPB_CACHE_PREFIX', 'lpb_'),
        'tags' => ['lpb', 'pages'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets Settings
    |--------------------------------------------------------------------------
    |
    | Configure the widgets system paths and namespaces
    |
    */
    'widgets' => [
        'path' => env('LPB_WIDGETS_PATH', app_path('Widgets')),
        'namespace' => env('LPB_WIDGETS_NAMESPACE', 'App\\Widgets'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Configure performance optimization options
    |
    */
    'performance' => [
        'eager_load_widgets' => true,
        'eager_load_metatags' => true,
        'chunk_size' => 100,
    ],

];
