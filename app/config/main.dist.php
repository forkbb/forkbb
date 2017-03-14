<?php

return [
    'BASE_URL'    => '_BASE_URL_',
    // Define line breaks in mail headers; possible values can be PHP_EOL, "\r\n", "\n" or "\r"
    'EOL'         => PHP_EOL,
    'DB_DSN'      => '_DB_DSN_',
    'DB_USERNAME' => '_DB_USERNAME_',
    'DB_PASSWORD' => '_DB_PASSWORD_',
    'DB_OPTIONS'  => [],
    'DB_PREFIX'   => '_DB_PREFIX_',
    'TIME_REMEMBER' => 31536000,
    'COOKIE' => [
        'prefix' => '_COOKIE_PREFIX_',
        'domain' => '',
        'path'   => '/',
        'secure' => false,
    ],
    'HMAC' => [
        'algo' => 'sha256',
        'salt' => '_SALT_FOR_HMAC_',
    ],
    // For FluxBB by Visman 1.5.10.74 and above
    'SALT1' => '',
    'JQUERY_LINK' => '//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js',
    'DEBUG' => 1,
    'MAINTENANCE_OFF' => false,
    'GROUP_UNVERIFIED' => 0,
    'GROUP_ADMIN' => 1,
    'GROUP_MOD' => 2,
    'GROUP_GUEST' => 3,
    'GROUP_MEMBER' => 4,

    'shared' => [
        'Request' => [
            'class' => \ForkBB\Core\Request::class,
            'Secury' => '@Secury',
        ],
        'DB' => [
            'class' => \ForkBB\Core\DB::class,
            'dsn'      => '%DB_DSN%',
            'username' => '%DB_USERNAME%',
            'password' => '%DB_PASSWORD%',
            'options'  => '%DB_OPTIONS%',
            'prefix'   => '%DB_PREFIX%',
        ],
        'Secury' => [
            'class' => \ForkBB\Core\Secury::class,
            'hmac' => '%HMAC%',
        ],
        'FileCache' => [
            'class' => \ForkBB\Core\Cache\FileCache::class,
            'cache_dir' => '%DIR_CACHE%',
        ],
        'Cache' => [
            'class' => \ForkBB\Core\Cache::class,
            'provider' => '@FileCache',
        ],
        'View' => [
            'class' => \ForkBB\Core\View::class,
            'cache_dir' => '%DIR_CACHE%',
            'views_dir' => '%DIR_VIEWS%',
        ],
        'Router' => [
            'class' => \ForkBB\Core\Router::class,
            'base_url' => '%BASE_URL%',
        ],
        'Lang' => \ForkBB\Core\Lang::class,
        'Mail' => [
            'class' => \ForkBB\Core\Mail::class,
            'host' => '%config.o_smtp_host%',
            'user' => '%config.o_smtp_user%',
            'pass' => '%config.o_smtp_pass%',
            'ssl' => '%config.o_smtp_ssl%',
            'eol' => '%EOL%',
        ],
        'Func' => \ForkBB\Core\Func::class,

        'CacheLoader' => [
            'class' => \ForkBB\Models\Actions\CacheLoader::class,
            'Cache' => '@Cache',
        ],
        'CacheGenerator' => \ForkBB\Models\Actions\CacheGenerator::class,
        'CacheStopwords' => [
            'class' => \ForkBB\Models\Actions\CacheStopwords::class,
            'Cache' => '@Cache',
        ],

        'user' => '@LoadCurrentUser:load',
        'config' => [
            'factory method' => '@CacheLoader:load',
            'key' => 'config',
        ],
        'bans' => [
            'factory method' => '@CacheLoader:load',
            'key' => 'bans',
        ],
        'censoring' => [
            'factory method' => '@CacheLoader:load',
            'key' => 'censoring',
        ],
        'users_info' => [
            'factory method' => '@CacheLoader:load',
            'key' => 'users_info',
        ],
        'admins' => [
            'factory method' => '@CacheLoader:load',
            'key' => 'admins',
        ],
        'smilies' => [
            'factory method' => '@CacheLoader:load',
            'key' => 'smilies',
        ],
        'stopwords' => '@CacheStopwords:load',
        'forums' => '@CacheLoader:loadForums',

        'UserCookie' => [
            'class' => \ForkBB\Models\UserCookie::class,
            'Secury' => '@Secury',
            'options' => '%COOKIE%',
            'min' => '%config.o_timeout_visit%',
            'max' => '%TIME_REMEMBER%',
        ],
        'Csrf' => [
            'class' => \ForkBB\Models\Csrf::class,
            'Secury' => '@Secury',
            'user' => '@user',
        ],
        'Online' => \ForkBB\Models\Online::class,
        'UserMapper' => \ForkBB\Models\UserMapper::class,
        'Validator' => \ForkBB\Models\Validator::class,

        'Message' => \ForkBB\Models\Pages\Message::class,
    ],
    'multiple'  => [
        'PrimaryController' => \ForkBB\Controllers\Primary::class,
        'Primary' => '@PrimaryController:check',

        'RoutingController' => \ForkBB\Controllers\Routing::class,
        'Routing' => '@RoutingController:routing',

        'CheckBans' => \ForkBB\Models\Actions\CheckBans::class,
        'LoadCurrentUser' => [
            'class' => \ForkBB\Models\Actions\LoadUserFromCookie::class,
            'mapper' => '@UserMapper',
            'cookie' => '@UserCookie',
            'config' => '@config',
        ],

        'config update' => [
            'factory method' => '@CacheLoader:load',
            'key' => 'config',
            'update' => true,
        ],
        'get config' => '@CacheGenerator:config',

        'bans update' => [
            'factory method' => '@CacheLoader:load',
            'key' => 'bans',
            'update' => true,
        ],
        'get bans' => '@CacheGenerator:bans',

        'censoring update' => [
            'factory method' => '@CacheLoader:load',
            'key' => 'censoring',
            'update' => true,
        ],
        'get censoring' => '@CacheGenerator:censoring',

        'users_info update' => [
            'factory method' => '@CacheLoader:load',
            'key' => 'users_info',
            'update' => true,
        ],
        'get users_info' => '@CacheGenerator:usersInfo',

        'admins update' => [
            'factory method' => '@CacheLoader:load',
            'key' => 'admins',
            'update' => true,
        ],
        'get admins' => '@CacheGenerator:admins',

        'smilies update' => [
            'factory method' => '@CacheLoader:load',
            'key' => 'smilies',
            'update' => true,
        ],
        'get smilies' => '@CacheGenerator:smilies',

        'get forums' => [
            'factory method' => '@CacheGenerator:forums',
            'user' => '@user',
        ],

        'Index' => \ForkBB\Models\Pages\Index::class,
        'Rules' => \ForkBB\Models\Pages\Rules::class,
        'Auth' => \ForkBB\Models\Pages\Auth::class,
        'Register' => \ForkBB\Models\Pages\Register::class,
        'Redirect' => \ForkBB\Models\Pages\Redirect::class,
        'Maintenance' => \ForkBB\Models\Pages\Maintenance::class,
        'Ban' => \ForkBB\Models\Pages\Ban::class,
        'Debug' => \ForkBB\Models\Pages\Debug::class,
        'AdminIndex' => \ForkBB\Models\Pages\Admin\Index::class,
        'AdminStatistics' => \ForkBB\Models\Pages\Admin\Statistics::class,
    ],
];
