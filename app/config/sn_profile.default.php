<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

// переименуйте этот файл в sn_profile.php
// в файле main.php замените 'sn_profile.default.php' на 'sn_profile.php'
// после этого вносите свои изменения

return [
    // Список профилей социальных сетей для соответствующих полей профиля пользователя
    'flickr' => [
        'title' => 'Flickr',
        'urls' => [
            'flickr\.com/(?:people|photos)/([a-zA-Z0-9@-]+)/' => 'https://www.flickr.com/people/$1/',
        ],
    ],
    'github' => [
        'title' => 'GitHub',
        'urls'  => [
            'github\.com/([a-zA-Z0-9_\-]+)' => 'https://github.com/$1',
        ],
    ],
    'mastodon' => [
        'title' => 'Mastodon',
        'urls'  => [
            'mastodon\.social/@([a-z0-9_.]+)' => 'https://mastodon.social/@$1',
        ],
    ],
    'reddit' => [
        'title' => 'Reddit',
        'urls'  => [
            'reddit\.com/u(?:ser)?/([a-zA-Z0-9_\-]+)' => 'https://www.reddit.com/user/$1/'
        ],
    ],
    'stackoverflow' => [
        'title' => 'StackOverflow',
        'urls'  => [
            'stackoverflow\.com/users/([0-9]+)/([^,\\/+:\s]+)' => 'https://stackoverflow.com/users/$1/$2',
            'ru\.stackoverflow\.com/users/([0-9]+)/([^,\\/+:\s]+)' => 'https://ru.stackoverflow.com/users/$1/$2',
        ],
    ],
    'telegram' => [
        'title' => 'Telegram',
        'urls'  => [
            't\.me/([a-z0-9_]+)' => 'https://t.me/$1',
        ],
    ],
    'tiktok' => [
        'title' => 'TikTok',
        'urls'  => [
            'tiktok\.com/@([a-z0-9_.]+)' => 'https://www.tiktok.com/@$1',
        ],
    ],

    // элемент unknown можно раскомментировать для того, чтобы разрешить неизвестные ссылки
    /*
    'unknown' => [
        'title' => 'Unknown',
        'urls'  => [
            '.++' => '$0',
        ],
    ],
    */
];
