<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

use function \ForkBB\__;

return [
    [
        'tag' => 'ROOT',
        'type' => 'block',
        'handler' => <<<'HANDLER'
$body = '<p>' . $body . '</p>';
$body = \str_replace('<p><br>', '<p>', $body);
$body = \str_replace('<p></p>', '', $body);
$body = \preg_replace('%<br>(?:\s*?<br>){3,}%', '<br><br><br>', $body);

return $body;
HANDLER,
    ],
    [
        'tag' => 'code',
        'type' => 'block',
        'recursive' => true,
        'text_only' => true,
        'pre' => true,
        'attrs' => [
            'Def' => true,
            'No_attr' => true,
        ],
        'handler' => <<<'HANDLER'
return '</p><pre class="f-bb-code">' . \trim($body, "\n\r") . '</pre><p>';
HANDLER,
    ],
    [   'tag' => 'b',
        'parents' => ['inline', 'block', 'url'],
        'handler' => <<<'HANDLER'
return "<b>{$body}</b>";
HANDLER,
    ],
    [
        'tag' => 'i',
        'parents' => ['inline', 'block', 'url'],
        'handler' => <<<'HANDLER'
return "<i>{$body}</i>";
HANDLER,
    ],
    [
        'tag' => 'em',
        'parents' => ['inline', 'block', 'url'],
        'handler' => <<<'HANDLER'
return "<em>{$body}</em>";
HANDLER,
    ],
    [
        'tag' => 'u',
        'parents' => ['inline', 'block', 'url'],
        'handler' => <<<'HANDLER'
return "<u>{$body}</u>";
HANDLER,
    ],
    [
        'tag' => 's',
        'parents' => ['inline', 'block', 'url'],
        'handler' => <<<'HANDLER'
return "<s>{$body}</s>";
HANDLER,
    ],
    [
        'tag' => 'del',
        'parents' => ['inline', 'block', 'url'],
        'handler' => <<<'HANDLER'
return "<del>{$body}</del>";
HANDLER,
    ],
    [
        'tag' => 'ins',
        'parents' => ['inline', 'block', 'url'],
        'handler' => <<<'HANDLER'
return "<ins>{$body}</ins>";
HANDLER,
    ],
    [
        'tag' => 'sub',
        'parents' => ['inline', 'block', 'url'],
        'handler' => <<<'HANDLER'
return "<sub>{$body}</sub>";
HANDLER,
    ],
    [
        'tag' => 'sup',
        'parents' => ['inline', 'block', 'url'],
        'handler' => <<<'HANDLER'
return "<sup>{$body}</sup>";
HANDLER,
    ],
    [
        'tag' => 'h',
        'type' => 'h',
        'handler' => <<<'HANDLER'
return "</p><p class=\"f-bb-header\">{$body}</p><p>";
HANDLER,
    ],
    [
        'tag' => 'hr',
        'type' => 'block',
        'single' => true,
        'handler' => <<<'HANDLER'
return  '</p><hr><p>';
HANDLER,
    ],
    [
        'tag' => 'color',
        'parents' => ['inline', 'block', 'url'],
        'self_nesting' => 5,
        'attrs' => [
            'Def' => [
                'format' => '%^(?:\#(?:[\dA-Fa-f]{3}){1,2}|(?:aqua|black|blue|fuchsia|gray|green|lime|maroon|navy|olive|orange|purple|red|silver|teal|yellow|white))$%',
            ],
        ],
        'handler' => <<<'HANDLER'
$parser->inlineStyle(true);

$color = $attrs['Def'];

if ('#' === $color[0]) {
    $color = \strtoupper($color);
} else {
    $repl = [
        'black'   => '#000000',
        'gray'    => '#808080',
        'silver'  => '#C0C0C0',
        'white'   => '#FFFFFF',
        'fuchsia' => '#FF00FF',
        'purple'  => '#800080',
        'red'     => '#FF0000',
        'maroon'  => '#800000',
        'yellow'  => '#FFFF00',
        'olive'   => '#808000',
        'lime'    => '#00FF00',
        'green'   => '#008000',
        'aqua'    => '#00FFFF',
        'teal'    => '#008080',
        'blue'    => '#0000FF',
        'navy'    => '#000080',
    ];

    if (isset($repl[$color])) {
        $color = $repl[$color];
    }
}

return "<span class=\"f-bb-color\" style=\"color:{$color};\">{$body}</span>";
HANDLER,
    ],
    [
        'tag' => 'colour',
        'parents' => ['inline', 'block', 'url'],
        'self_nesting' => 5,
        'attrs' => [
            'Def' => [
                'format' => '%^(?:\#(?:[\dA-Fa-f]{3}){1,2}|(?:aqua|black|blue|fuchsia|gray|green|lime|maroon|navy|olive|orange|purple|red|silver|teal|yellow|white))$%',
            ],
        ],
        'handler' => <<<'HANDLER'
$parser->inlineStyle(true);

$color = $attrs['Def'];

if ('#' === $color[0]) {
    $color = \strtoupper($color);
} else {
    $repl = [
        'black'   => '#000000',
        'gray'    => '#808080',
        'silver'  => '#C0C0C0',
        'white'   => '#FFFFFF',
        'fuchsia' => '#FF00FF',
        'purple'  => '#800080',
        'red'     => '#FF0000',
        'maroon'  => '#800000',
        'yellow'  => '#FFFF00',
        'olive'   => '#808000',
        'lime'    => '#00FF00',
        'green'   => '#008000',
        'aqua'    => '#00FFFF',
        'teal'    => '#008080',
        'blue'    => '#0000FF',
        'navy'    => '#000080',
    ];

    if (isset($repl[$color])) {
        $color = $repl[$color];
    }
}

return "<span class=\"f-bb-color\" style=\"color:{$color};\">{$body}</span>";
HANDLER,
    ],
    [
        'tag' => 'background',
        'parents' => ['inline', 'block', 'url'],
        'self_nesting' => 5,
        'attrs' => [
            'Def' => [
                'format' => '%^(?:\#(?:[\dA-Fa-f]{3}){1,2}|(?:aqua|black|blue|fuchsia|gray|green|lime|maroon|navy|olive|orange|purple|red|silver|teal|yellow|white))$%',
            ],
        ],
        'handler' => <<<'HANDLER'
$parser->inlineStyle(true);

$color = $attrs['Def'];

if ('#' === $color[0]) {
    $color = \strtoupper($color);
} else {
    $repl = [
        'black'   => '#000000',
        'gray'    => '#808080',
        'silver'  => '#C0C0C0',
        'white'   => '#FFFFFF',
        'fuchsia' => '#FF00FF',
        'purple'  => '#800080',
        'red'     => '#FF0000',
        'maroon'  => '#800000',
        'yellow'  => '#FFFF00',
        'olive'   => '#808000',
        'lime'    => '#00FF00',
        'green'   => '#008000',
        'aqua'    => '#00FFFF',
        'teal'    => '#008080',
        'blue'    => '#0000FF',
        'navy'    => '#000080',
    ];

    if (isset($repl[$color])) {
        $color = $repl[$color];
    }
}

return "<span class=\"f-bb-bgcolor\" style=\"background-color:{$color};\">{$body}</span>";
HANDLER,
    ],
    [
        'tag' => 'size',
        'parents' => ['inline', 'block', 'url'],
        'self_nesting' => 5,
        'attrs' => [
            'Def' => [
                'format' => '%^[1-7]$%',
            ],
        ],
        'handler' => <<<'HANDLER'
return "<span class=\"f-bb-size\" data-bb=\"{$attrs['Def']}\">{$body}</span>";
HANDLER,
    ],
    [
        'tag' => 'left',
        'type' => 'block',
        'handler' => <<<'HANDLER'
return "</p><p class=\"f-bb-left\">{$body}</p><p>";
HANDLER,
    ],
    [
        'tag' => 'right',
        'type' => 'block',
        'handler' => <<<'HANDLER'
return "</p><p class=\"f-bb-right\">{$body}</p><p>";
HANDLER,
    ],
    [
        'tag' => 'center',
        'type' => 'block',
        'handler' => <<<'HANDLER'
return "</p><p class=\"f-bb-center\">{$body}</p><p>";
HANDLER,
    ],
    [
        'tag' => 'justify',
        'type' => 'block',
        'handler' => <<<'HANDLER'
return "</p><p class=\"f-bb-justify\">{$body}</p><p>";
HANDLER,
    ],
    [
        'tag' => 'mono',
        'parents' => ['inline', 'block', 'url'],
        'handler' => <<<'HANDLER'
return "<span class=\"f-bb-mono\">{$body}</span>";
HANDLER,
    ],
    [
        'tag' => 'font',
        'parents' => ['inline', 'block', 'url'],
        'self_nesting' => 5,
        'attrs' => [
            'Def' => [
                'format' => '%^[a-z\d, -]+$%i',
            ],
        ],
        'handler' => <<<'HANDLER'
$parser->inlineStyle(true);

return "<span class=\"f-bb-font\" style=\"font-family:{$attrs['Def']};\">{$body}</span>";
HANDLER,
    ],
    [
        'tag' => 'email',
        'type' => 'email',
        'attrs' => [
            'Def' => [
                'format' => '%^[^\x00-\x1f\s]+?@[^\x00-\x1f\s]+$%',
            ],
            'No_attr' => [
                'body_format' => '%^[^\x00-\x1f\s]+?@[^\x00-\x1f\s]+$%D',
                'text_only' => true,
            ],
        ],
        'handler' => <<<'HANDLER'
$def = $parser->de($attrs['Def'] ?? $body);
$def = url("mailto:{$def}");

if ('' == $def) {
    return '<span class="f-bb-badlink">BAD EMAIL</span>';
} else {
    $def = $parser->e($def);

    return "<a href=\"{$def}\">{$body}</a>";
}
HANDLER,
    ],
    [
        'tag' => '*',
        'type' => 'block',
        'self_nesting' => 5,
        'parents' => ['list'],
        'auto' => true,
        'handler' => <<<'HANDLER'
return "<li><p>{$body}</p></li>";
HANDLER,
    ],
    [
        'tag' => 'list',
        'type' => 'list',
        'self_nesting' => 5,
        'tags_only' => true,
        'attrs' => [
            'Def' => true,
            'No_attr' => true,
        ],
        'handler' => <<<'HANDLER'
if (! isset($attrs['Def'])) {
    $attrs['Def'] = '*';
}

switch ($attrs['Def'][0]) {
    case 'a':
        return "</p><ol class=\"f-bb-l-lat\">{$body}</ol><p>";
    case '1':
        return "</p><ol class=\"f-bb-l-dec\">{$body}</ol><p>";
    default:
        return "</p><ul class=\"f-bb-l-disc\">{$body}</ul><p>";
}
HANDLER,
    ],
    [
        'tag' => 'after',
        'type' => 'block',
        'single' => true,
        'attrs' => [
            'Def' => [
                'format' => '%^\d+$%',
            ],
        ],
        'handler' => <<<'HANDLER'
$arr = [];
$sec = $attrs['Def'] % 60;
$min = (int) ($attrs['Def'] / 60) % 60;
$hours = (int) ($attrs['Def'] / 3600) % 24;
$days = (int) ($attrs['Def'] / 86400);
if ($days > 0) {
    $arr[] = $days . __('After time d');
}
if ($hours > 0) {
    $arr[] = $hours . __('After time H');
}
if ($min > 0) {
    $arr[] = ($min < 10 ? '0' . $min : $min) . __('After time i');
}
if ($sec > 0) {
    $arr[] = ($sec < 10 ? '0' . $sec : $sec) . __('After time s');
}

$attr = __('After time') . ' ' . \implode(' ', $arr);

return '</p><p class="f-bb-after"><em>' . $attr . ':</em></p><p>';
HANDLER,
    ],
    [
        'tag' => 'quote',
        'type' => 'block',
        'self_nesting' => 5,
        'attrs' => [
            'Def' => true,
            'No_attr' => true,
        ],
        'handler' => <<<'HANDLER'
$header = isset($attrs['Def']) ? '<cite class="f-bb-q-header">' . __(['%s wrote', $attrs['Def']]) . '</cite>' : '';

return "</p><blockquote class=\"f-bb-quote\">{$header}<div class=\"f-bb-q-body\"><p>{$body}</p></div></blockquote><p>";
HANDLER,
    ],
    [
        'tag' => 'spoiler',
        'type' => 'block',
        'self_nesting' => 5,
        'attrs' => [
            'Def' => true,
            'No_attr' => true,
        ],
        'handler' => <<<'HANDLER'
if (! isset($attrs['Def'])) {
    $attrs['Def'] = __('Hidden text');
}

return "</p><details class=\"f-bb-spoiler\"><summary>{$attrs['Def']}</summary><div class=\"f-bb-s-body\"><p>{$body}</p></div></details><p>";
HANDLER,
    ],
    [
        'tag' => 'img',
        'type' => 'img',
        'parents' => ['inline', 'block', 'url'],
        'text_only' => true,
        'attrs' => [
            'Def' => [
                'body_format' => '%^(?:(?:ht|f)tps?://[^\x00-\x1f\s<"]+|data:image/[a-z]+;base64,(?:[a-zA-Z\d/\+\=]+))$%D'
            ],
            'No_attr' => [
                'body_format' => '%^(?:(?:ht|f)tps?://[^\x00-\x1f\s<"]+|data:image/[a-z]+;base64,(?:[a-zA-Z\d/\+\=]+))$%D'
            ],
        ],
        'handler' => <<<'HANDLER'
if (! isset($attrs['Def'])) {
    $attrs['Def'] = (\substr($body, 0, 11) === 'data:image/') ? 'base64' : \basename($body);
}

// тег в подписи
if ($parser->attr('isSign')) {
    if ($parser->attr('showImgSign')) {
        return '<img src="' . $body . '" alt="' . $attrs['Def'] . '" loading="lazy" class="sigimage">';
    }
} else {
    // тег в теле сообщения
    if ($parser->attr('showImg')) {
        return '<span class="postimg"><img src="' . $body . '" alt="' . $attrs['Def'] . '" loading="lazy"></span>';
    }
}

return '<a href="' . $body . '" rel="ugc">&lt;' . __('Image link') . ' - ' . $attrs['Def'] . '&gt;</a>';
HANDLER,
    ],
    [
        'tag' => 'url',
        'type' => 'url',
        'parents' => ['inline', 'block'],
        'attrs' => [
            'Def' => [
                'format' => '%^[^\x00-\x1f]+$%',
            ],
            'No_attr' => [
                'body_format' => '%^[^\x00-\x1f]+$%D',
            ],
        ],
        'handler' => <<<'HANDLER'
if (isset($attrs['Def'])) {
    $url = $attrs['Def'];
} else {
    $url = $body;
    // возможно внутри была картинка, которая отображается как ссылка
    if (\preg_match('%^<a href=".++(?<=</a>)$%D', $url)) {
        return $url;
    }
    // возможно внутри картинка
    if (\preg_match('%<img src="([^"]+)"%', $url, $match)) {
        $url = $match[1];
    }
}

$fUrl = $parser->de($url);

if (0 === \strpos($url, 'ftp.')) {
    $fUrl = 'ftp://' . $fUrl;
}

$fUrl = url($fUrl);

if ('' == $fUrl) {
    return '<span class="f-bb-badlink">BAD URL</span>';
} else {
    $fUrl = $parser->e($fUrl);

    return "<a href=\"{$fUrl}\" rel=\"ugc\">{$body}</a>";
}
HANDLER,
    ],
    [
        'tag' => 'table',
        'type' => 'table',
        'tags_only' => true,
        'self_nesting' => 5,
        'attrs' => [
            'No_attr' => true,
            'style' => true,
        ],
        'handler' => <<<'HANDLER'
$attr = '';

foreach ($attrs as $key => $val) {
    $attr .= " {$key}=\"{$val}\"";
}

return "</p><table class=\"f-bb-table\"{$attr}>{$body}</table><p>";
HANDLER,
    ],
    [
        'tag' => 'caption',
        'type' => 'block',
        'parents' => ['table'],
        'self_nesting' => 5,
        'attrs' => [
            'No_attr' => true,
            'style' => true,
        ],
        'handler' => <<<'HANDLER'
$attr = '';

foreach ($attrs as $key => $val) {
    $attr .= " {$key}=\"{$val}\"";
}

return "<caption class=\"f-bb-caption\"{$attr}><p>{$body}</p></caption>";
HANDLER,
    ],
    [
        'tag' => 'thead',
        'type' => 't',
        'parents' => ['table'],
        'tags_only' => true,
        'self_nesting' => 5,
        'attrs' => [
            'No_attr' => true,
            'style' => true,
        ],
        'handler' => <<<'HANDLER'
$attr = '';

foreach ($attrs as $key => $val) {
    $attr .= " {$key}=\"{$val}\"";
}

return "<thead class=\"f-bb-thead\"{$attr}>{$body}</thead>";
HANDLER,
    ],
    [
        'tag' => 'tbody',
        'type' => 't',
        'parents' => ['table'],
        'tags_only' => true,
        'self_nesting' => 5,
        'attrs' => [
            'No_attr' => true,
            'style' => true,
        ],
        'handler' => <<<'HANDLER'
$attr = '';

foreach ($attrs as $key => $val) {
    $attr .= " {$key}=\"{$val}\"";
}

return "<tbody class=\"f-bb-tbody\"{$attr}>{$body}</tbody>";
HANDLER,
    ],
    [
        'tag' => 'tfoot',
        'type' => 't',
        'parents' => ['table'],
        'tags_only' => true,
        'self_nesting' => 5,
        'attrs' => [
            'No_attr' => true,
            'style' => true,
        ],
        'handler' => <<<'HANDLER'
$attr = '';

foreach ($attrs as $key => $val) {
    $attr .= " {$key}=\"{$val}\"";
}

return "<tfoot class=\"f-bb-tfoot\"{$attr}>{$body}</tfoot>";
HANDLER,
    ],
    [
        'tag' => 'tr',
        'type' => 'tr',
        'parents' => ['table', 't'],
        'tags_only' => true,
        'self_nesting' => 5,
        'attrs' => [
            'No_attr' => true,
            'style' => true,
        ],
        'handler' => <<<'HANDLER'
$attr = '';

foreach ($attrs as $key => $val) {
    $attr .= " {$key}=\"{$val}\"";
}

return "<tr class=\"f-bb-tr\"{$attr}>{$body}</tr>";
HANDLER,
    ],
    [
        'tag' => 'th',
        'type' => 'block',
        'parents' => ['tr'],
        'self_nesting' => 5,
        'attrs' => [
            'No_attr' => true,
            'style' => true,
            'colspan' => true,
            'rowspan' => true,
        ],
        'handler' => <<<'HANDLER'
$attr = '';

foreach ($attrs as $key => $val) {
    $attr .= " {$key}=\"{$val}\"";
}

return "<th class=\"f-bb-th\"{$attr}><p>{$body}</p></th>";
HANDLER,
    ],
    [
        'tag' => 'td',
        'type' => 'block',
        'parents' => ['tr'],
        'self_nesting' => 5,
        'attrs' => [
            'No_attr' => true,
            'style' => true,
            'colspan' => true,
            'rowspan' => true,
        ],
        'handler' => <<<'HANDLER'
$attr = '';

foreach ($attrs as $key => $val) {
    $attr .= " {$key}=\"{$val}\"";
}

return "<td class=\"f-bb-td\"{$attr}><p>{$body}</p></td>";
HANDLER,
    ],
    [
        'tag' => 'from',
        'type' => 'block',
        'text_only' => true,
        'handler' => <<<'HANDLER'
$body = __(['Post from topic %s', $parser->de($body)]);

return "</p><p class=\"f-bb-from\">{$body}</p><p>";
HANDLER,
    ],

];
