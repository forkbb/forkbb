<?php

include '../Parserus.php';

$parser = new Parserus();

echo $parser->setBBCodes([
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
         return '<table' . $attr . '>' . $body . '</table>';
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
         return '<th' . $attr . '>' . $body . '</th>';
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
         return '<td' . $attr . '>' . $body . '</td>';
     },
    ],
])->parse('
[table align=right border=1 bordercolor=#ccc      cellpadding=5 cellspacing=0 style="border-collapse:collapse; width:500px"]
		[tr]
			[th style="width:50%"]Position[/th]
			[th style=width:50%]Astronaut[/th]
		[/tr]
		[tr]
			[td]Commander[/td]
			[td]Neil A. Armstrong[/td]
		[/tr]
		[tr]
			[td]Command Module Pilot[/td]
			[td]Michael Collins[/td]
		[/tr]
		[tr]
			[td]Lunar Module Pilot[/td]
			[td]Edwin "Buzz" E. Aldrin, Jr.[/td]
		[/tr]
[/table]
')->getCode();

#output:
#[table align="right" border="1" bordercolor="#ccc" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:500px"]
#		[tr]
#			[th style=width:50%]Position[/th]
#			[th style=width:50%]Astronaut[/th]
#		[/tr]
#		[tr]
#			[td]Commander[/td]
#			[td]Neil A. Armstrong[/td]
#		[/tr]
#		[tr]
#			[td]Command Module Pilot[/td]
#			[td]Michael Collins[/td]
#		[/tr]
#		[tr]
#			[td]Lunar Module Pilot[/td]
#			[td]Edwin "Buzz" E. Aldrin, Jr.[/td]
#		[/tr]
#[/table]
#
