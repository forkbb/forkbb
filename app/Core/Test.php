<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\Container;
use ForkBB\Core\Validator;

class Test
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    public function beforeValidation(Validator $v): Validator
    {
        $v->addValidators([
            'check_field_validation' => [$this, 'vTestCheck'],
        ])->addRules([
            'verificationField' => 'check_field_validation',
        ])->addAliases([
        ]);

        return $v;
    }

    public function vTestCheck(Validator $v, /* mixed */ $value) /* : mixed */
    {
        if (null !== $value) {
            $v->addError('The :alias contains an invalid value');

            return $value;
        }

        $index = 0;

        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            $index += 1;
        } elseif (\preg_match('%\bmsie\b%i', $_SERVER['HTTP_USER_AGENT'])) {
            $v->addError('Old browser', 'w');

            return $value;
        }
        if (empty($_SERVER['HTTP_ACCEPT'])) {
            $index += 5;
        } elseif (false === \strpos($_SERVER['HTTP_ACCEPT'], 'text/html')) {
            $index += 1;
        }
        if (empty($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            $index += 1;
        }
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $index += 1;
        }
        if (
            ! empty($_SERVER['HTTP_PRAGMA'])
            && ! \preg_match('%^no-cache$%iD', $_SERVER['HTTP_PRAGMA'])
        ) {
            $index += 1;
        }
/*
        if (empty($_SERVER['HTTP_CACHE_CONTROL'])) {
            if (false !== \strpos($_SERVER['SERVER_PROTOCOL'], '1.1')) {
                $index += 1;
            }
        } else {
            if (false !== \strpos($_SERVER['SERVER_PROTOCOL'], '1.0')) {
                $index += 3;
            }
        }
*/
        if (empty($_SERVER['HTTP_CONNECTION'])) {
            $index += 1;
        } elseif (! \preg_match('%^(?:keep-alive|close)$%iD', $_SERVER['HTTP_CONNECTION'])) {
            $index += 3;
        }
        if (
            ! empty($_SERVER['HTTP_REFERER'])
            && $this->c->Router->validate($_SERVER['HTTP_REFERER'], 'Index') !== $_SERVER['HTTP_REFERER']
        ) {
            $index += 3;
        }
        if ($index > 3)  {
            $v->addError('Bad browser', 'e');
        }

        return $value;
    }
}
