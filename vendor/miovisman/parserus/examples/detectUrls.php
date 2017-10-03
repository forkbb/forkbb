<?php

include '../Parserus.php';

$parser = new Parserus();

echo $parser->setBBCodes([
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
#...
     },
    ],
])->parse('Hello www.example.com World!')
  ->detectUrls()
  ->getCode();

#output: Hello [url]www.example.com[/url] World!
