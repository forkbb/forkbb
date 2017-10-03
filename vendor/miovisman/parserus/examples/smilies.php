<?php

include '../Parserus.php';

$parser = new Parserus();

echo $parser->setSmilies([
    ':)' => 'http://example.com/smilies/smile.png',
    ';)' => 'http://example.com/smilies/wink.png',
])->addBBCode([
    'tag' => 'img',
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
        return '<img src="' . $body . '" alt="' . $attrs['Def'] . '">';
    },
])->setSmTpl('<img src="{url}" alt="{alt}">')
  ->parse(":)\n;)")
  ->detectSmilies()
  ->getHTML();

#output: <img src="http://example.com/smilies/smile.png" alt=":)"><br><img src="http://example.com/smilies/wink.png" alt=";)">
