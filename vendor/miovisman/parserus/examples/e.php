<?php

include '../Parserus.php';

$parser = new Parserus();

echo $parser->e("<'abcde'>");

#output: &lt;&apos;abcde&apos;&gt;
