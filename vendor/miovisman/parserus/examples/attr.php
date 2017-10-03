<?php

include '../Parserus.php';

$parser = new Parserus();

echo $parser->addBBCode([
    'tag' => 'after',
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

        return '<span style="color: #808080"><em>' . $attr . ':</em></span><br>';
     },
])->setAttr('lang', [
    'After time'   => 'Added later',
    'After time s' => ' s',
    'After time i' => ' min',
    'After time H' => ' h',
    'After time d' => ' d',
])->parse('[after=10123]')
  ->getHTML();


#output: <span style="color: #808080"><em>Added later 2 h 48 min 43 s:</em></span><br>
