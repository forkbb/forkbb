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
    'github' => [
        'title' => 'GitHub',
        'urls'  => [
            'github\.com/([\w\-]+)' => 'https://github.com/$1',
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
