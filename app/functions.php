<?php

function __($data, ...$args)
{
    static $lang;

    if (empty($lang)) {
        $lang = $data;
        return;
    }

    $tr = $lang->get($data);

    if (is_array($tr)) {
        if (isset($args[0]) && is_numeric($args[0])) {
            $n = array_shift($args);
            eval('$n = (int) ' . $tr['plural']);
            $tr = $tr[$n];
        } else {
            $tr = $tr[0];
        }
    }

    if (empty($args)) {
        return $tr;
    } elseif (is_array($args[0])) {
        return strtr($tr, $args[0]);
    } else {
        return sprintf($tr, ...$args);
    }
}
