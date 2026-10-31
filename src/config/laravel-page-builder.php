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
    | Delivery cache (ADR 09): pointers (slug -> served revision) live for
    | 'ttl' seconds and are rewritten by publishing events; content entries
    | are immutable (keyed by revision and design version) and live for
    | 'content_ttl' seconds, or forever when null. 'store' is a cache store
    | from config/cache.php (null for the default store).
    |
    */
    'cache' => [
        'enabled' => env('LPB_CACHE_ENABLED', true),
        'store' => env('LPB_CACHE_STORE'),
        'ttl' => env('LPB_CACHE_TTL', 3600),
        'content_ttl' => env('LPB_CACHE_CONTENT_TTL'),
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

        // The built-in widget library (heading, image, button, tabs, price
        // table, ...; see Andmarruda\Lpb\Widgets\Library\Library)
        'library' => env('LPB_WIDGET_LIBRARY', true),

        // WidgetDefinition classes registered at boot; more can be added
        // with Lpb::widgets()->register(Hero::class). A definition with the
        // name of a library widget replaces it.
        'definitions' => [],

        // Where lpb:make-widget writes render component stubs, per framework
        'frontend_path' => env('LPB_WIDGETS_FRONTEND_PATH', resource_path('js/lpb/widgets')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Page Tree Limits
    |--------------------------------------------------------------------------
    |
    | Maximum block nesting depth and maximum number of nodes (blocks plus
    | widget instances) per page. Larger trees are rejected on write.
    |
    */
    'limits' => [
        'max_depth' => (int) env('LPB_MAX_DEPTH', 10),
        'max_nodes' => (int) env('LPB_MAX_NODES', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Breakpoints
    |--------------------------------------------------------------------------
    |
    | Style variants target a breakpoint by key; the value is the maximum
    | width in pixels (null for the base breakpoint).
    |
    */
    'breakpoints' => [
        'desktop' => null,
        'tablet' => 1024,
        'mobile' => 767,
    ],

    /*
    |--------------------------------------------------------------------------
    | Revisions
    |--------------------------------------------------------------------------
    |
    | Published revisions kept per page; the published one is never pruned.
    |
    */
    'revisions' => [
        'keep' => (int) env('LPB_REVISIONS_KEEP', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Routes
    |--------------------------------------------------------------------------
    |
    | Delivery (public) and editor (protected by middleware and the lpb.edit
    | and lpb.publish Gates) JSON APIs, and the optional page route of the
    | view flow (e.g. '/{slug}', disabled when null).
    |
    */
    'routes' => [
        'enabled' => env('LPB_ROUTES_ENABLED', true),
        'prefix' => env('LPB_ROUTES_PREFIX', 'lpb/api'),
        'middleware' => ['api'],
        'pages' => env('LPB_PAGES_ROUTE'),
        'pages_middleware' => ['web'],
    ],

    'editor' => [
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preview
    |--------------------------------------------------------------------------
    |
    | Lifetime in seconds of the signed preview URLs issued by the editor.
    |
    */
    'preview' => [
        'ttl' => (int) env('LPB_PREVIEW_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inertia
    |--------------------------------------------------------------------------
    |
    | Page component rendered by Lpb::inertia().
    |
    */
    'inertia' => [
        'component' => env('LPB_INERTIA_COMPONENT', 'Lpb/Page'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Shell
    |--------------------------------------------------------------------------
    |
    | Vite entry points loaded by lpb::shell, the shell used when a page has
    | no template or its view_path does not exist.
    |
    */
    'shell' => [
        'assets' => ['resources/js/app.js'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Endpoints notified on publish, unpublish, archive, rollback and design
    | bumps. Payloads are signed with HMAC-SHA256 in X-Lpb-Signature.
    |
    */
    'webhooks' => [
        'endpoints' => array_filter(explode(',', (string) env('LPB_WEBHOOK_ENDPOINTS', ''))),
        'secret' => env('LPB_WEBHOOK_SECRET'),
        'queue' => env('LPB_WEBHOOK_QUEUE'),
        'tries' => 5,
        'backoff' => [10, 60, 300, 900],
        'timeout' => 10,
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
        'eager_load_blocks' => true,
        'eager_load_metatags' => true,
        'chunk_size' => 100,
    ],

];
