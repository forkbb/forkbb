<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
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
                'a', 'abbr', 'address', 'article', 'aside', 'b', 'bdi', 'bdo', 'blockquote', 'br', 'code',
                'dd', 'del', 'details', 'dfn', 'div', 'dl', 'dt', 'em', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5',
                'h6', 'header', 'hr', 'i', 'img', 'ins', 'kbd', 'li', 'mark', 'noscript', 'ol', 'p', 'pre', 'q',
                's', 'samp', 'section', 'small', 'span', 'strong', 'sub', 'summary', 'sup', 'u', 'ul', 'var',
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
        // Обязательные атрибуты / Required attributes
        'cfgSetTagParamsRequired' => [
            ['a', ['href']],
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
        ],
        // Автоматически добавляемые атрибуты / Auto-added attributes
        // [тег, атрибут, значение, перезапись существующего атрибута]
        // [tag, attribute, value, overwrite existing attribute]
        'cfgSetTagParamDefault' => [
            ['a', 'rel', 'ugc', true],
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
                'noscript', 'ol', 'p', 'pre', 'section', 'ul',
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
