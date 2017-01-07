<?php

define('PUN_DEBUG', 1);
define('PUN_SHOW_QUERIES', 1);

return [
    'BASE_URL'    => '_BASE_URL_',
    'DB_TYPE'     => '_DB_TYPE_',
    'DB_HOST'     => '_DB_HOST_',
    'DB_USERNAME' => '_DB_USERNAME_',
    'DB_PASSWORD' => '_DB_PASSWORD_',
    'DB_NAME'     => '_DB_NAME_',
    'DB_PREFIX'   => '_DB_PREFIX_',
    'P_CONNECT'   => false,
    'COOKIE_PREFIX' => '_COOKIE_PREFIX_',
    'COOKIE_DOMAIN' => '',
    'COOKIE_PATH'   => '/',
    'COOKIE_SECURE' => false,
    'COOKIE_SALT'   => '_COOKIE_SALT_',
    'ALGO_FOR_HMAC' => 'sha1',
    'SALT1' => '',
    'JQUERY_LINK' => '//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js',
    'shared' => [
        'Request' => \ForkBB\Core\Request::class,
        'DBLoader' => [
            'class' => \ForkBB\Core\DBLoader::class,
            'db_host'     => '%DB_HOST%',
            'db_username' => '%DB_USERNAME%',
            'db_password' => '%DB_PASSWORD%',
            'db_name'     => '%DB_NAME%',
            'db_prefix'   => '%DB_PREFIX%',
            'p_connect'   => '%P_CONNECT%',
        ],
        'DB' => [
            'factory method' => '@DBLoader:load',
            'type' => '%DB_TYPE%',
        ],
        'firstAction' => \ForkBB\Core\Blank::class,
    ],
    'multiple'  => [],
];
