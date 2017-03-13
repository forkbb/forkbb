<?php

return [
    'BASE_URL'    => 'http://forkbb.local',
    'DEBUG' => 1,
    'GROUP_UNVERIFIED' => 0,
    'GROUP_ADMIN'      => 1,
    'GROUP_MOD'        => 2,
    'GROUP_GUEST'      => 3,
    'GROUP_MEMBER'     => 4,
    'EOL' => PHP_EOL,


    'HMAC' => [
        'algo' => 'sha1',
        'salt' => '_SALT_FOR_HMAC_',
    ],

    'config' => [
        'o_default_lang' => 'English',
        'o_default_style' => 'ForkBB',
        'o_redirect_delay' => 0,
        'o_date_format' => 'Y-m-d',
        'o_time_format' => 'H:i:s',
        'o_maintenance' => 0,
    ],

    'shared' => [
        'Lang' => \ForkBB\Core\Lang::class,
        'Router' => [
            'class' => \ForkBB\Core\Router::class,
            'base_url' => '%BASE_URL%',
        ],
        'View' => [
            'class' => \ForkBB\Core\View::class,
            'cache_dir' => '%DIR_CACHE%',
            'views_dir' => '%DIR_VIEWS%',
        ],
        'Func' => \ForkBB\Core\Func::class,
        'Validator' => \ForkBB\Models\Validator::class,
        'Mail' => [
            'class' => \ForkBB\Core\Mail::class,
            'host' => '',
            'user' => '',
            'pass' => '',
            'ssl' => '',
            'eol' => '%EOL%',
        ],
        'DB' => [
            'class' => \ForkBB\Core\DB::class,
            'dsn'      => '%DB_DSN%',
            'username' => '%DB_USERNAME%',
            'password' => '%DB_PASSWORD%',
            'options'  => '%DB_OPTIONS%',
            'prefix'   => '%DB_PREFIX%',
        ],




        'Request' => [
            'class' => \ForkBB\Core\Request::class,
            'Secury' => '@Secury',
        ],
        'Secury' => [
            'class' => \ForkBB\Core\Secury::class,
            'hmac' => '%HMAC%',
        ],
    ],
    'multiple'  => [
        'PrimaryController' => \ForkBB\Controllers\Install::class,
        'Primary' => '@PrimaryController:routing',

        'Install' => \ForkBB\Models\Pages\Install::class,
        'Redirect' => \ForkBB\Models\Pages\Redirect::class,
        'Debug' => \ForkBB\Models\Pages\Debug::class,
    ],
];
