<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

// переименуйте этот файл в test.php
// в файле main.php замените '%DIR_CONFIG%/test.default.php' на '%DIR_CONFIG%/test.php'
// после этого вносите свои изменения

return [
    // Список url (не относящихся к страницам движка) источников отправки форм
    // символ звездочки (*) заменяет любое количество символов
    'referers' => [
        // 'http://localhost/',  // <-- точное соотвествие странице http://localhost/ (не https)
        // 'http://localhost/*', // <-- любые страницы с сайта http://localhost/ (не https)
    ],
];
