<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

/**
 * Массив подмены символов
 * Используется в методе app\Core\Func\friendly() в случае:
 * 1. если конфиг FRIENDLY_URL['translit'] содержит строку правил - тогда подмена символов производится до Transliterator::transliterate()
 * 2. если конфиг FRIENDLY_URL['translit'] равен true - тогда подмена символов выполняется без вызова Transliterator::transliterate()
 */
return [
    'ь' => '',
    'Ь' => '',
    'ъ' => '',
    'Ъ' => '',
    '\'' => '',
    '’' => '',
];
