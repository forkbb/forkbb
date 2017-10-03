<?php

include '../Parserus.php';

$parser = new Parserus();

$parser->addBBCode([
    'tag' => 'b',
    'handler' => function($body) {
        return '<b>' . $body . '</b>';
    }
])->addBBcode([
    'tag' => 'i',
    'handler' => function($body) {
        return '<i>' . $body . '</i>';
    },
]);

# №1

var_dump($parser->parse("[i][b] [/b][/i]")->stripEmptyTags());

#output: boolean false

echo $parser->getCode();

#output: [i][b] [/b][/i]

echo "\n\n";

# №2

var_dump($parser->parse("[i][b] [/b][/i]")->stripEmptyTags(" \n", true));

#output: boolean true

echo $parser->getCode();

#output: [i][b] [/b][/i]

var_dump($parser->getErrors());

#output: array (size=1)
#  0 => string 'Все теги пустые' (length=28)

echo "\n\n";

# №3

var_dump($parser->parse("[i][b] [/b][/i]")->stripEmptyTags(" \n"));

#output: boolean true

echo $parser->getCode();

#output:

var_dump($parser->getErrors());

#output: array (size=1)
#  empty
