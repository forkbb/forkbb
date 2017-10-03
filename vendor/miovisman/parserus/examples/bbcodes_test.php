<?php

include '../Parserus.php';

# example on the basis of (partially) bb-codes from FluxBB/PunBB parsers
# пример на основании (частично) bb-кодов из парсеров FluxBB/PunBB

$bbcodes = [
    ['tag' => 'ROOT',
     'type' => 'block',
     'handler' => function($body) {
         $body = '<p>' . $body . '</p>';

         // Replace any breaks next to paragraphs so our replace below catches them
         $body = preg_replace('%(</?p>)(?:\s*?<br />){1,2}%', '$1', $body);
         $body = preg_replace('%(?:<br />\s*?){1,2}(</?p>)%', '$1', $body);

         // Remove any empty paragraph tags (inserted via quotes/lists/code/etc) which should be stripped
         $body = str_replace('<p></p>', '', $body);

         $body = preg_replace('%<br />\s*?<br />%', '</p><p>', $body);

         $body = str_replace('<p><br />', '<br /><p>', $body);
         $body = str_replace('<br /></p>', '</p><br />', $body);
         $body = str_replace('<p></p>', '<br /><br />', $body);

         return $body;
     },
    ],
    ['tag' => 'code',
     'type' => 'block',
     'recursive' => true,
     'text only' => true,
     'pre' => true,
     'attrs' => [
         'Def' => true,
         'no attr' => true,
     ],
     'handler' => function($body, $attrs) {
         $body = trim($body, "\n\r");
         $class = substr_count($body, "\n") > 28 ? ' class="vscroll"' : '';
         return '</p><div class="codebox"><pre' . $class . '><code>' . $body . '</code></pre></div><p>';
     },
    ],
    ['tag' => 'b',
     'handler' => function($body) {
         return '<strong>' . $body . '</strong>';
     },
    ],
    ['tag' => 'i',
     'handler' => function($body) {
         return '<em>' . $body . '</em>';
     },
    ],
    ['tag' => 'em',
     'handler' => function($body) {
         return '<em>' . $body . '</em>';
     },
    ],
    ['tag' => 'u',
     'handler' => function($body) {
         return '<span class="bbu">' . $body . '</span>';
     },
    ],
    ['tag' => 's',
     'handler' => function($body) {
         return '<span class="bbs">' . $body . '</span>';
     },
    ],
    ['tag' => 'del',
     'handler' => function($body) {
         return '<del>' . $body . '</del>';
     },
    ],
    ['tag' => 'ins',
     'handler' => function($body) {
         return '<ins>' . $body . '</ins>';
     },
    ],
    ['tag' => 'h',
     'type' => 'h',
     'handler' => function($body) {
         return '</p><h5>' . $body . '</h5><p>';
     },
    ],
    ['tag' => 'hr',
     'type' => 'block',
     'single' => true,
     'handler' => function() {
         return  '</p><hr /><p>';
     },
    ],
    ['tag' => 'color',
     'self nesting' => true,
     'attrs' => [
         'Def' => [
             'format' => '%^(?:\#(?:[\dA-Fa-f]{3}){1,2}|(?:aqua|black|blue|fuchsia|gray|green|lime|maroon|navy|olive|orange|purple|red|silver|teal|yellow|white))$%',
         ],
     ],
     'handler' => function($body, $attrs) {
         return '<span style="color:' . $attrs['Def'] . ';">' . $body . '</span>';
     },
    ],
    ['tag' => 'colour',
     'self nesting' => true,
     'attrs' => [
         'Def' => [
             'format' => '%^(?:\#(?:[\dA-Fa-f]{3}){1,2}|(?:aqua|black|blue|fuchsia|gray|green|lime|maroon|navy|olive|orange|purple|red|silver|teal|yellow|white))$%',
         ],
     ],
     'handler' => function($body, $attrs) {
         return '<span style="color:' . $attrs['Def'] . ';">' . $body . '</span>';
     },
    ],
    ['tag' => 'background',
     'self nesting' => true,
     'attrs' => [
         'Def' => [
             'format' => '%^(?:\#(?:[\dA-Fa-f]{3}){1,2}|(?:aqua|black|blue|fuchsia|gray|green|lime|maroon|navy|olive|orange|purple|red|silver|teal|yellow|white))$%',
         ],
     ],
     'handler' => function($body, $attrs) {
         return '<span style="background-color:' . $attrs['Def'] . ';">' . $body . '</span>';
     },
    ],
    ['tag' => 'size',
     'self nesting' => true,
     'attrs' => [
         'Def' => [
             'format' => '%^[1-9]\d*(?:em|ex|pt|px|\%)?$%',
         ],
     ],
     'handler' => function($body, $attrs) {
         if (is_numeric($attrs['Def'])) {
             $attrs['Def'] .= 'px';
         }
         return '<span style="font-size:' . $attrs['Def'] . ';">' . $body . '</span>';
     },
    ],
    ['tag' => 'right',
     'type' => 'block',
     'handler' => function($body) {
         return '</p><p style="text-align: right;">' . $body . '</p><p>';
     },
    ],
    ['tag' => 'center',
     'type' => 'block',
     'handler' => function($body) {
         return '</p><p style="text-align: center;">' . $body . '</p><p>';
     },
    ],
    ['tag' => 'justify',
     'type' => 'block',
     'handler' => function($body) {
         return '</p><p style="text-align: justify;">' . $body . '</p><p>';
     },
    ],
    ['tag' => 'mono',
     'handler' => function($body) {
         return '<code>' . $body . '</code>';
     },
    ],
    ['tag' => 'font',
     'self nesting' => true,
     'attrs' => [
         'Def' => [
             'format' => '%^[a-z\d, -]+$%i',
         ],
     ],
     'handler' => function($body, $attrs) {
         return '<span style="font-family:' . $attrs['Def'] . ';">' . $body . '</span>';
     },
    ],
    ['tag' => 'email',
     'type' => 'email',
     'attrs' => [
         'Def' => [
             'format' => '%^[^\x00-\x1f\s]+?@[^\x00-\x1f\s]+$%',
         ],
         'no attr' => [
             'body format' => '%^[^\x00-\x1f\s]+?@[^\x00-\x1f\s]+$%D',
             'text only' => true,
         ],
     ],
     'handler' => function($body, $attrs) {
         if (empty($attrs['Def'])) {
             return '<a href="mailto:' . $body . '">' . $body . '</a>';
         } else {
             return '<a href="mailto:' . $attrs['Def'] . '">' . $body . '</a>';
         }
     },
    ],
    ['tag' => '*',
     'type' => 'block',
     'self nesting' => true,
     'parents' => ['list'],
     'auto' => true,
     'handler' => function($body) {
         return '<li><p>' . $body . '</p></li>';
     },
    ],
    ['tag' => 'list',
     'type' => 'list',
     'self nesting' => true,
     'tags only' => true,
     'attrs' => [
         'Def' => true,
         'no attr' => true,
     ],
     'handler' => function($body, $attrs) {
         if (!isset($attrs['Def'])) {
             $attrs['Def'] = '*';
         }

         switch ($attrs['Def'][0]) {
             case 'a':
                 return '</p><ol class="alpha">' . $body . '</ol><p>';
             case '1':
                 return '</p><ol class="decimal">' . $body . '</ol><p>';
             default:
                 return '</p><ul>' . $body . '</ul><p>';
         }
     },
    ],
    ['tag' => 'after',
     'type' => 'block',
     'single' => true,
     'attrs' => [
         'Def' => [
            'format' => '%^\d+$%',
         ],
     ],
     'handler' => function($body, $attrs, $parser) {
         $lang = $parser->attr('lang');
         $arr = array();
         $sec = $attrs['Def'] % 60;
         $min = ($attrs['Def'] / 60) % 60;
         $hours = ($attrs['Def'] / 3600) % 24;
         $days = (int) ($attrs['Def'] / 86400);
         if ($days > 0) {
             $arr[] = $days . $lang['After time d'];
         }
         if ($hours > 0) {
             $arr[] = $hours . $lang['After time H'];
         }
         if ($min > 0) {
             $arr[] = (($min < 10) ? '0' . $min : $min) . $lang['After time i'];
         }
         if ($sec > 0) {
             $arr[] = (($sec < 10) ? '0' . $sec : $sec) . $lang['After time s'];
         }

         $attr = $lang['After time'] . ' ' . implode(' ', $arr);

         return '<span style="color: #808080"><em>' . $attr . ':</em></span><br />';
     },
    ],
    ['tag' => 'quote',
     'type' => 'block',
     'self nesting' => true,
     'attrs' => [
         'Def' => true,
         'no attr' => true,
     ],
     'handler' => function($body, $attrs, $parser) {
         if (isset($attrs['Def'])) {
             $lang = $parser->attr('lang');
             $st = '</p><div class="quotebox"><cite>' . $attrs['Def'] .  ' ' . $lang['wrote'] . '</cite><blockquote><div><p>';
         } else {
             $st = '</p><div class="quotebox"><blockquote><div><p>';
         }

         return $st . $body . '</p></div></blockquote></div><p>';
     },
    ],
    ['tag' => 'spoiler',
     'type' => 'block',
     'self nesting' => true,
     'attrs' => [
         'Def' => true,
         'no attr' => true,
     ],
     'handler' => function($body, $attrs, $parser) {
         if (isset($attrs['Def'])) {
             $st = '</p><div class="quotebox" style="padding: 0px;"><div onclick="var e,d,c=this.parentNode,a=c.getElementsByTagName(\'div\')[1],b=this.getElementsByTagName(\'span\')[0];if(a.style.display!=\'\'){while(c.parentNode&&(!d||!e||d==e)){e=d;d=(window.getComputedStyle?getComputedStyle(c, null):c.currentStyle)[\'backgroundColor\'];if(d==\'transparent\'||d==\'rgba(0, 0, 0, 0)\')d=e;c=c.parentNode;}a.style.display=\'\';a.style.backgroundColor=d;b.innerHTML=\'&#9650;\';}else{a.style.display=\'none\';b.innerHTML=\'&#9660;\';}" style="font-weight: bold; cursor: pointer; font-size: 0.9em;"><span style="padding: 0 5px;">&#9660;</span>' . $attrs['Def'] . '</div><div style="padding: 6px; margin: 0; display: none;"><p>';
         } else {
             $lang = $parser->attr('lang');
             $st = '</p><div class="quotebox" style="padding: 0px;"><div onclick="var e,d,c=this.parentNode,a=c.getElementsByTagName(\'div\')[1],b=this.getElementsByTagName(\'span\')[0];if(a.style.display!=\'\'){while(c.parentNode&&(!d||!e||d==e)){e=d;d=(window.getComputedStyle?getComputedStyle(c, null):c.currentStyle)[\'backgroundColor\'];if(d==\'transparent\'||d==\'rgba(0, 0, 0, 0)\')d=e;c=c.parentNode;}a.style.display=\'\';a.style.backgroundColor=d;b.innerHTML=\'&#9650;\';}else{a.style.display=\'none\';b.innerHTML=\'&#9660;\';}" style="font-weight: bold; cursor: pointer; font-size: 0.9em;"><span style="padding: 0 5px;">&#9660;</span>' . $lang['Hidden text'] . '</div><div style="padding: 6px; margin: 0; display: none;"><p>';
         }

         return $st . $body . '</p></div></div><p>';
     },
    ],
    ['tag' => 'img',
     'type' => 'img',
     'parents' => ['inline', 'block', 'url'],
     'text only' => true,
     'attrs' => [
         'Def' => [
             'body format' => '%^(?:(?:ht|f)tps?://[^\x00-\x1f\s<"]+|data:image/[a-z]+;base64,(?:[a-zA-Z\d/\+\=]+))$%D'
         ],
         'no attr' => [
             'body format' => '%^(?:(?:ht|f)tps?://[^\x00-\x1f\s<"]+|data:image/[a-z]+;base64,(?:[a-zA-Z\d/\+\=]+))$%D'
         ],
     ],
     'handler' => function($body, $attrs, $parser) {
         if (! isset($attrs['Def'])) {
             $attrs['Def'] = (substr($body, 0, 11) === 'data:image/') ? 'base64' : basename($body);
         }

         // тег в подписи
         if ($parser->attr('isSign')) {
            if ($parser->attr('showImgSign')) {
                return '<img src="' . $body . '" alt="' . $attrs['Def'] . '" class="sigimage" />';
            }
         } else {
         // тег в теле сообщения
            if ($parser->attr('showImg')) {
                return '<span class="postimg"><img src="' . $body . '" alt="' . $attrs['Def'] . '" /></span>';
            }
         }

         $lang = $parser->attr('lang');

         return '<a href="' . $body . '" rel="nofollow">&lt;' . $lang['Image link']. ' - ' . $attrs['Def'] . '&gt;</a>';
     },
    ],
    ['tag' => 'url',
     'type' => 'url',
     'parents' => ['inline', 'block'],
     'attrs' => [
         'Def' => [
             'format' => '%^[^\x00-\x1f]+$%',
         ],
         'no attr' => [
             'body format' => '%^[^\x00-\x1f]+$%D',
         ],
     ],
     'handler' => function($body, $attrs, $parser) {
         if (isset($attrs['Def'])) {
             $url = $attrs['Def'];
         } else {
             $url = $body;
             // возможно внутри была картинка, которая отображается как ссылка
             if (preg_match('%^<a href=".++(?<=</a>)$%D', $url)) {
                 return $url;
             }
             // возможно внутри картинка
             if (preg_match('%<img src="([^"]+)"%', $url, $match)) {
                 $url = $match[1];
             }
         }

         $fUrl = str_replace(array(' ', '\'', '`', '"'), array('%20', '', '', ''), $url);

         if (strpos($url, 'www.') === 0) {
             $fUrl = 'http://'.$fUrl;
         } else if (strpos($url, 'ftp.') === 0) {
             $fUrl = 'ftp://'.$fUrl;
         } else if (strpos($url, '/') === 0) {
             $fUrl = $parser->attr('baseUrl') . $fUrl;
         } else if (!preg_match('%^([a-z0-9]{3,6})://%', $url)) {
             $fUrl = 'http://'.$fUrl;
         }

         if ($url === $body) {
             $url = htmlspecialchars_decode($url, ENT_QUOTES);
             $url = mb_strlen($url, 'UTF-8') > 55 ? mb_substr($url, 0, 39, 'UTF-8') . ' … ' . mb_substr($url, -10, null, 'UTF-8') : $url;
             $body = $parser->e($url);
         }

         return '<a href="' . $fUrl . '" rel="nofollow">' . $body . '</a>';
     },
    ],
    ['tag' => 'table',
     'type' => 'table',
     'tags only' => true,
     'self nesting' => true,
     'attrs' => [
         'no attr' => true,
         'style' => true,
         'align' => true,
         'background' => true,
         'bgcolor' => true,
         'border' => true,
         'bordercolor' => true,
         'cellpadding' => true,
         'cellspacing' => true,
         'frame' => true,
         'rules' => true,
     ],
     'handler' => function($body, $attrs) {
         $attr = '';
         foreach ($attrs as $key => $val) {
             $attr .= ' ' . $key . '="' . $val . '"';
         }
         return '</p><table' . $attr . '>' . $body . '</table><p>';
     },
    ],
    ['tag' => 'caption',
     'type' => 'block',
     'parents' => ['table'],
     'self nesting' => true,
     'attrs' => [
         'no attr' => true,
         'style' => true,
     ],
     'handler' => function($body, $attrs) {
         $attr = '';
         foreach ($attrs as $key => $val) {
             $attr .= ' ' . $key . '="' . $val . '"';
         }
         return '<caption' . $attr . '><p>' . $body . '</p></caption>';
     },
    ],
    ['tag' => 'thead',
     'type' => 't',
     'parents' => ['table'],
     'tags only' => true,
     'self nesting' => true,
     'attrs' => [
         'no attr' => true,
         'style' => true,
     ],
     'handler' => function($body, $attrs) {
         $attr = '';
         foreach ($attrs as $key => $val) {
             $attr .= ' ' . $key . '="' . $val . '"';
         }
         return '<thead' . $attr . '>' . $body . '</thead>';
     },
    ],
    ['tag' => 'tbody',
     'type' => 't',
     'parents' => ['table'],
     'tags only' => true,
     'self nesting' => true,
     'attrs' => [
         'no attr' => true,
         'style' => true,
     ],
     'handler' => function($body, $attrs) {
         $attr = '';
         foreach ($attrs as $key => $val) {
             $attr .= ' ' . $key . '="' . $val . '"';
         }
         return '<tbody' . $attr . '>' . $body . '</tbody>';
     },
    ],
    ['tag' => 'tfoot',
     'type' => 't',
     'parents' => ['table'],
     'tags only' => true,
     'self nesting' => true,
     'attrs' => [
         'no attr' => true,
         'style' => true,
     ],
     'handler' => function($body, $attrs) {
         $attr = '';
         foreach ($attrs as $key => $val) {
             $attr .= ' ' . $key . '="' . $val . '"';
         }
         return '<tfoot' . $attr . '>' . $body . '</tfoot>';
     },
    ],
    ['tag' => 'tr',
     'type' => 'tr',
     'parents' => ['table', 't'],
     'tags only' => true,
     'self nesting' => true,
     'attrs' => [
         'no attr' => true,
         'style' => true,
     ],
     'handler' => function($body, $attrs) {
         $attr = '';
         foreach ($attrs as $key => $val) {
             $attr .= ' ' . $key . '="' . $val . '"';
         }
         return '<tr' . $attr . '>' . $body . '</tr>';
     },
    ],
    ['tag' => 'th',
     'type' => 'block',
     'parents' => ['tr'],
     'self nesting' => true,
     'attrs' => [
         'no attr' => true,
         'style' => true,
         'colspan' => true,
         'rowspan' => true,
     ],
     'handler' => function($body, $attrs) {
         $attr = '';
         foreach ($attrs as $key => $val) {
             $attr .= ' ' . $key . '="' . $val . '"';
         }
         return '<th' . $attr . '><p>' . $body . '</p></th>';
     },
    ],
    ['tag' => 'td',
     'type' => 'block',
     'parents' => ['tr'],
     'self nesting' => true,
     'attrs' => [
         'no attr' => true,
         'style' => true,
         'colspan' => true,
         'rowspan' => true,
     ],
     'handler' => function($body, $attrs) {
         $attr = '';
         foreach ($attrs as $key => $val) {
             $attr .= ' ' . $key . '="' . $val . '"';
         }
         return '<td' . $attr . '><p>' . $body . '</p></td>';
     },
    ],
];

$lang = [
    'Hidden text' => 'Hidden text',
    'wrote' => 'wrote:', // For [quote]'s
    'After time' => 'Added later',
    'After time s' => ' s',
    'After time i' => ' min',
    'After time H' => ' h',
    'After time d' => ' d',
    'Image link' => 'image', // This is displayed (i.e. <image>) instead of images when "Show images" is disabled in the profile
];

$text = '[table align="center" border="1" bordercolor="#ccc" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:500px"]
	[caption][b]Table[/b][/caption]
	[thead]
		[tr]
			[th style=width:50%]Column 1[/th]
			[th style=width:50%]Column 2[/th]
		[/tr]
	[/thead]
	[tbody]
		[tr]
			[td]1.1[/td]
			[td]
                [list]
                    [*]1.2
                    [*]1.3
                    [*]1.4
                [/list]
            [/td]
		[/tr]
	[/tbody]
[/table]
[size=36]Hello World![/size]
[img]data:image/gif;base64,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[/img]
[code]
$parser->setBBCodes($bbcodes)
    ->setAttr(\'baseUrl\', \'http://localhost\')
    ->setAttr(\'lang\', $lang)
    ->setAttr(\'showImg\', true)
    ->setAttr(\'showImgSign\', true)
    ->setAttr(\'isSign\', false)
    ->parse($text);
[/code]

[center][background=#00CCCC][url=https://github.com/MioVisman/Parserus]Parserus[/url] BBCode parser[/background][/center]

<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
';

$parser = new Parserus(ENT_XHTML);
$parser->setBBCodes($bbcodes)
    ->setAttr('baseUrl', 'http://localhost')
    ->setAttr('lang', $lang)
    ->setAttr('showImg', true)
    ->setAttr('showImgSign', true)
    ->setAttr('isSign', false)
    ->parse($text);

echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ru" lang="ru" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body>' .
$parser->getHtml() .
'</body>
</html>';
