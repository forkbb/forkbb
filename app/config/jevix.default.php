<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

return [
    // Конфигурация / Configuration
    'default' => [
        // Символы перевода строки / Newline
        'cfgSetNL' => "\n",
        // XHTML
        'cfgSetXHTMLMode' => false,
        // Автозамена перевода строки на тег <br> / Autocorrect line feed to <br> tag
        'cfgSetAutoBrMode' => true,
        // Автоматически распознавать ссылки / Automatically detect links
        'cfgSetAutoLinkMode' => true,
        // Разрешенные теги / Allowed tags
        'cfgAllowTags' => [
            'a1' => [
                'a', 'abbr', 'address', 'article', 'aside', 'b', 'bdi', 'bdo', 'blockquote', 'br',
                'caption', 'cite', 'code', 'dd', 'del', 'details', 'dfn', 'div', 'dl', 'dt',
                'em', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'i', 'img',
                'ins', 'kbd', 'li', 'mark', 'menu', 'ol', 'p', 'pre', 'q', 's', 'samp', 'section',
                'small', 'span', 'strong', 'sub', 'summary', 'sup', 'table', 'td', 'th', 'time',
                'tr', 'u', 'ul', 'var',
            ],
        ],
        // Короткие теги / Short tags
        'cfgSetTagShort' => [
            'a1' => ['br', 'hr', 'img'],
        ],
        // Преформатированные теги / Preformatted tags
        'cfgSetTagPreformatted' => [
            'a1' => ['pre'],
        ],
        // Теги, которые полностью будут удалены / Tags that will be completely removed
        'cfgSetTagCutWithContent' => [
            'a1' => ['canvas', 'frameset', 'iframe', 'object', 'script', 'style'],
        ],
        // Разрешенные атрибуты тегов / Allowed tag attributes
        'cfgAllowTagParams' => [
            ['a', ['id', 'class', 'title', 'href', 'rel']],
            ['abbr', ['id', 'class']],
            ['address', ['id', 'class']],
            ['article', ['id', 'class']],
            ['aside', ['id', 'class']],
            ['b', ['id', 'class']],
            ['bdi', ['id', 'class']],
            ['bdo', ['id', 'class', 'dir']],
            ['blockquote', ['id', 'class']],
            ['caption', ['id', 'class', 'align', 'valign']],
            ['cite', ['id', 'class']],
            ['code', ['id', 'class']],
            ['dd', ['id', 'class']],
            ['del', ['id', 'class']],
            ['details', ['id', 'class']],
            ['dfn', ['id', 'class', 'title']],
            ['div', ['id', 'class']],
            ['dl', ['id', 'class']],
            ['dt', ['id', 'class']],
            ['em', ['id', 'class']],
            ['footer', ['id', 'class']],
            ['h1', ['id', 'class']],
            ['h2', ['id', 'class']],
            ['h3', ['id', 'class']],
            ['h4', ['id', 'class']],
            ['h5', ['id', 'class']],
            ['h6', ['id', 'class']],
            ['header', ['id', 'class']],
            ['hr', ['id', 'class']],
            ['i', ['id', 'class']],
            ['img', ['id', 'class', 'src', 'alt', 'title', 'width', 'height', 'loading', 'decoding', 'sizes', 'srcset']],
            ['ins', ['id', 'class']],
            ['kbd', ['id', 'class']],
            ['li', ['id', 'class', 'value' => '#int']],
            ['mark', ['id', 'class']],
            ['menu', ['id', 'class']],
            ['ol', ['id', 'class', 'type', 'reversed', 'start' => '#int']],
            ['p', ['id', 'class']],
            ['pre', ['id', 'class']],
            ['q', ['id', 'class']],
            ['s', ['id', 'class']],
            ['samp', ['id', 'class']],
            ['section', ['id', 'class']],
            ['small', ['id', 'class']],
            ['span', ['id', 'class']],
            ['strong', ['id', 'class']],
            ['sub', ['id', 'class']],
            ['summary', ['id', 'class']],
            ['sup', ['id', 'class']],
            ['table', ['id', 'class']],
            ['td', ['id', 'class', 'colspan', 'headers', 'rowspan']],
            ['th', ['id', 'class', 'colspan', 'headers', 'rowspan', 'abbr', 'scope']],
            ['time', ['id', 'class', 'datetime']],
            ['tr', ['id', 'class']],
            ['u', ['id', 'class']],
            ['ul', ['id', 'class']],
            ['var', ['id', 'class']],
        ],
        // Обязательные атрибуты / Required attributes
        'cfgSetTagParamsRequired' => [
            ['a', ['href']],
            ['bdo', ['dir']],
            ['img', ['src']],
        ],
        // Теги-родители/потомки / Tags-parents/childs
        // [
        //   родитель / parent,
        //   потомки / childs,
        //   родитель содержит только теги (без текста) / parent only contains tags (no text),
        //   потомки могут существовать только в этом родителе / childs can only exist in this parent
        // ]
        'cfgSetTagChilds' => [
            ['details', ['summary'], false, true],
            ['dl', ['dd', 'dt'], true, true],
            ['ol', 'li', true, false],
            ['ul', 'li', true, false],
            ['menu', 'li', true, false],
            ['table', ['caption', 'tr'], true, true],
            ['tr', ['td', 'th'], true, true],
        ],
        // Автоматически добавляемые атрибуты / Auto-added attributes
        // [тег, атрибут, значение, перезапись существующего атрибута]
        // [tag, attribute, value, overwrite existing attribute]
        'cfgSetTagParamDefault' => [
//            ['a', 'rel', 'ugc', false],
            ['img', 'alt', 'image', false],
            ['img', 'loading', 'lazy', true],
        ],
        // Автозамена / AutoCorrect
        'cfgSetAutoReplace' => [
            [['+/-', '(c)', '(r)'], ['±', '©', '®']],
        ],
        // Теги в которых отключено типографирование / Tags with disabled typography
        'cfgSetTagNoTypography' => [
            'a1' => ['code', 'pre'],
        ],
        // Теги без авторасстановки <br> / Tags without auto-placement <br>
        'cfgSetTagNoAutoBr' => [
            'a1' => ['dl', 'ol', 'ul'],
        ],
        // Теги после которых не устанавливается <br> / Tags after which the <br> is not installed
        'cfgSetTagBlockType' => [
            'a1' => [
                'address', 'article', 'aside', 'blockquote', 'dd', 'div', 'dl', 'dt',
                'footer', 'h1', 'h2','h3','h4','h5','h6', 'header', 'hr', 'li',
                'ol', 'p', 'pre', 'section', 'ul',
            ],
        ],
        // Автозамена через regexp / Autocorrect via regexp
        // 'cfgSetAutoPregReplace' => [],
        // Замена содержимого тега через функцию / Replacing tag content via function
        // 'cfgSetTagCallback' => [],
        // Замена тега через функцию/ Replacing tag via function
        // 'cfgSetTagCallbackFull' => [],
        // Разрешенные комбинации значений атрибутов для тега / Allowed attribute value combinations for tag
        // 'cfgSetTagParamCombination' => [],
        // Список разрешенных протоколов / List of allowed protocols
        // 'cfgSetAllowedProtocols' => [],
        // Теги, кторые не удаляются, если содержимое пустое / Tags that are not removed when empty content
        // 'cfgSetTagIsEmpty' => [],
    ],
];
