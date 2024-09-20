<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

// переименуйте этот файл в domains.php
// в файле main.php замените '%DIR_CONFIG%/domains.default.php' на '%DIR_CONFIG%/domains.php'
// после этого вносите свои изменения

return [
    // Список доменов почтовых серверов для которых не требуется(???) посылать запросы при строгой проверке электронного адреса
    // Варианты значений:
    //   true  - запрос посылать не надо, почтовый сервер на домене существует
    //   false - запрос посылать не надо, почтовый сервер на домене отсутствует
    //   null  - надо посылать запрос(!!!)
    'yandex.ru'      => true,
    'yandex.com'     => true,
    'ya.ru'          => true,
    'rambler.ru'     => true,
    'mail.ru'        => true,
    'bk.ru'          => true,
    'inbox.ru'       => true,
    'list.ru'        => true,
    'gmail.com'      => true,
    'googlemail.com' => true,
    'outlook.com'    => true,
    'hotmail.com'    => true,
    'yahoo.com'      => true,
];
