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
    ['tag' => 'h',
     'type' => 'h',
     'handler' => function($body, $attrs, $parser) {
#...
     },
    ],
])->parse('www.example.com/link1[h]Hello www.example.com/link2 World![/h]www.example.com/link3')
  ->detectUrls()
  ->getCode();

#output: [url]www.example.com/link1[/url][h]Hello www.example.com/link2 World![/h][url]www.example.com/link3[/url]

echo "\n\n";

echo $parser->setBlackList(['url'])
  ->setWhiteList()
  ->parse('www.example.com/link1[h]Hello www.example.com/link2 World![/h]www.example.com/link3')
  ->detectUrls()
  ->getCode();

#output: www.example.com/link1[h]Hello www.example.com/link2 World![/h]www.example.com/link3

var_dump($parser->getErrors());

#output: array (size=1)
#output:   0 => string 'Тег [url] находится в черном списке' (length=60)

echo "\n\n";

echo $parser->setBlackList()
  ->setWhiteList(['h'])
  ->parse('www.example.com/link1[h]Hello www.example.com/link2 World![/h]www.example.com/link3')
  ->detectUrls()
  ->getCode();

#output: www.example.com/link1[h]Hello www.example.com/link2 World![/h]www.example.com/link3

var_dump($parser->getErrors());

#output: array (size=1)
#output:   0 => string 'Тег [url] отсутствует в белом списке' (length=62)
