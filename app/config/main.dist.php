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
    'TIME_REMEMBER' => 31536000,
    'COOKIE' => [
        'prefix' => '_COOKIE_PREFIX_',
        'domain' => '',
        'path'   => '/',
        'secure' => false,
    ],
    'HMAC' => [
        'algo' => 'sha1',
        'salt' => '_SALT_FOR_HMAC_',
    ],
    'SALT1'       => '', // For FluxBB by Visman 1.5.10.74 and above
    'JQUERY_LINK' => '//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js',
    'shared' => [
        'Request' => [
            'class' => \ForkBB\Core\Request::class,
            'Secury' => '@Secury',
        ],
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
        'Secury' => [
            'class' => \ForkBB\Core\Secury::class,
            'hmac' => '%HMAC%',
        ],
        'Cookie' => [
            'class' => \ForkBB\Core\Cookie::class,
            'Secury' => '@Secury',
            'options' => '%COOKIE%'
        ],
        'UserCookie' => [
            'class' => \ForkBB\Core\Cookie\UserCookie::class,
            'Secury' => '@Secury',
            'Cookie' => '@Cookie',
            'timeMin' => '@config[o_timeout_visit]',
            'timeMax' => '%TIME_REMEMBER%',
        ],
        'firstAction' => \ForkBB\Core\Blank::class,
    ],
    'multiple'  => [],
];
