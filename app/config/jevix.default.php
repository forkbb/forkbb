<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

return [
    // конфигурация
    'default' => [
        // перевод строки
        'cfgSetNL' => "\n",
        // XHTML
        'cfgSetXHTMLMode' => false,
        // Автозамена перевода строки на тег <br>
        'cfgSetAutoBrMode' => true,
        // Автоматически распознавать ссылки
        'cfgSetAutoLinkMode' => true,
        // Разрешенные теги
        'cfgAllowTags' => [
            'a1' => [
                'a', 'abbr', 'address', 'article', 'aside', 'b', 'bdi', 'bdo', 'blockquote', 'br', 'code',
                'dd', 'del', 'details', 'dfn', 'div', 'dl', 'dt', 'em', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5',
                'h6', 'header', 'hr', 'i', 'img', 'ins', 'kbd', 'li', 'mark', 'noscript', 'ol', 'p', 'pre', 'q',
                's', 'samp', 'section', 'small', 'span', 'strong', 'sub', 'summary', 'sup', 'u', 'ul', 'var',
            ],
        ],
        // Короткие теги
        'cfgSetTagShort' => [
            'a1' => ['br', 'hr', 'img'],
        ],
        // Преформатированные теги
        'cfgSetTagPreformatted' => [
            'a1' => ['pre'],
        ],
        // Теги, которые полностью будут удалены
        'cfgSetTagCutWithContent' => [
            'a1' => ['canvas', 'frameset', 'iframe', 'object', 'script', 'style'],
        ],
        // Разрешенные параметры тегов
        'cfgAllowTagParams' => [
            ['a', ['class', 'title', 'href']],
            ['abbr', ['class']],
            ['address', ['class']],
            ['article', ['class']],
            ['aside', ['class']],
            ['b', ['class']],
            ['bdi', ['class']],
            ['bdo', ['class']],
            ['blockquote', ['class']],
            ['code', ['class']],
            ['dd', ['class']],
            ['del', ['class']],
            ['details', ['class']],
            ['dfn', ['class', 'title']],
            ['div', ['class']],
            ['dl', ['class']],
            ['dt', ['class']],
            ['em', ['class']],
            ['footer', ['class']],
            ['h1', ['class']],
            ['h2', ['class']],
            ['h3', ['class']],
            ['h4', ['class']],
            ['h5', ['class']],
            ['h6', ['class']],
            ['header', ['class']],
            ['hr', ['class']],
            ['i', ['class']],
            ['img', ['class', 'src', 'alt', 'title', 'width', 'height']],
            ['ins', ['class']],
            ['kbd', ['class']],
            ['li', ['class']],
            ['ol', ['class', 'type', 'reversed', 'start' => '#int']],
            ['p', ['class']],
            ['pre', ['class']],
            ['q', ['class']],
            ['samp', ['class']],
            ['section', ['class']],
            ['small', ['class']],
            ['span', ['class']],
            ['strong', ['class']],
            ['sub', ['class']],
            ['sup', ['class']],
            ['ul', ['class']],
            ['var', ['class']],
        ],
        // Обязательные параметры
        'cfgSetTagParamsRequired' => [
            ['a', ['href']],
            ['img', ['src']],
        ],
        // Теги-родители/потомки
        // [
        //   контейнер,
        //   потомки,
        //   контейнер содержит только теги (без текста),
        //   потомки могут существовать только в этом родителе
        // ]
        'cfgSetTagChilds' => [
            ['details', ['summary'], false, true],
            ['dl', ['dd', 'dt'], true, true],
            ['ol', 'li', true, false],
            ['ul', 'li', true, false],
        ],
        // Автоматически добавляемые атрибуты
        // [тег, атрибут, значение, перезапись существующего атрибута]
        'cfgSetTagParamDefault' => [
            ['a', 'rel', 'ugc', true],
            ['img', 'alt', 'image', false],
            ['img', 'loading', 'lazy', true],
        ],
        // Автозамена
        'cfgSetAutoReplace' => [
            [['+/-', '(c)', '(r)'], ['±', '©', '®']],
        ],
        // Теги в которых отключено типографирование
        'cfgSetTagNoTypography' => [
            'a1' => ['code', 'pre'],
        ],
        // Теги без авторасстановки <br>
        'cfgSetTagNoAutoBr' => [
            'a1' => ['dl', 'ol', 'ul'],
        ],
        // Теги после которых не устанавливается <br>
        'cfgSetTagBlockType' => [
            'a1' => [
                'address', 'article', 'aside', 'blockquote', 'dd', 'div', 'dl', 'dt',
                'footer', 'h1', 'h2','h3','h4','h5','h6', 'header', 'hr', 'li',
                'noscript', 'ol', 'p', 'pre', 'section', 'ul',
            ],
        ],
        // Автозамена через regexp
        // 'cfgSetAutoPregReplace' => [],
        // Замена содержимого тега через функцию
        // 'cfgSetTagCallback' => [],
        // Замена тега через функцию
        // 'cfgSetTagCallbackFull' => [],
        // Разрешенные комбинации значений атрибутов для тега
        // 'cfgSetTagParamCombination' => [],
        // Список разрешенных протоколов
        // 'cfgSetAllowedProtocols' => [],
        // Теги, кторые не удаляются, если содержимое пустое
        // 'cfgSetTagIsEmpty' => [],

    ],
];
