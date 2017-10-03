<?php

include '../Parserus.php';

$parser = new Parserus();

echo $parser->addBBCode([
    'tag' => 'b',
    'handler' => function($body) {
        return '<b>' . $body . '</b>';
    }
])->addBBcode([
    'tag' => 'i',
    'handler' => function($body) {
        return '<i>' . $body . '</i>';
    },
])->parse("[i]Hello\n[b]World[/b]![/i]")
->getHTML();

#output: <i>Hello<br><b>World</b>!</i>
