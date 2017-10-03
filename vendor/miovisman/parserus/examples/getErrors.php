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

$parser->parse("[i][b] [/b][/i]")->stripEmptyTags(" \n", true);

$err = [
    1 => '[%1$s] is in the black list',
    2 => '[%1$s] is absent in the white list',
    3 => '[%1$s] can\'t be opened in the [%2$s]',
    4 => '[/%1$s] was found without a matching [%1$s]',
    5 => '[/%1$s] is found for single [%1$s]',
    6 => 'There are no attributes in [%1$s]',
    7 => 'Primary attribute is forbidden in [%1$s=...]',
    8 => 'Secondary attributes are forbidden in [%1$s ...]',
    9 => 'The attribute \'%2$s\' doesn\'t correspond to a template in the [%1$s]',
    10 => '[%1$s ...] contains unknown secondary attribute \'%2$s\'',
    11 => 'The body of [%1$s] doesn\'t correspond to a template',
    12 => '[%1$s] was opened within itself, this is not allowed',
    13 => 'In the [%1$s] is absent mandatory attribute \'%2$s\'',
    14 => 'All tags are empty'
];

var_dump($parser->getErrors($err));

#output: array (size=1)
#  0 => string 'All tags are empty' (length=18)
