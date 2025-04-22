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
    protected array $config = [];
    protected bool $multi;

    public function __construct(string $path, protected Container $c)
    {
        if (! \is_file($path)) {
            throw new RuntimeException('File not found');
        }

        if (! \is_readable($path)) {
            throw new RuntimeException('File can not be read');
        }

        $this->config = include $path;
    }

    public function beforeValidation(Validator $v, bool $multi = false): Validator
    {
        $this->multi = $multi;

        $v->addValidators([
            'check_field_validation' => [$this, 'vTestCheck'],
        ])->addRules([
            'verificationField' => 'check_field_validation',
        ])->addAliases([
        ]);

        return $v;
    }

    public function vTestCheck(Validator $v, mixed $value): mixed
    {
        if (! empty($v->getErrors())) {
            return $value;

        } elseif (null !== $value) {
            $v->addError('The :alias contains an invalid value');

            $this->log('Invalid value for field');

            return $value;
        }

        $index = 0;

        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            $index += 1;

        } elseif (\preg_match('%\b(msie|trident|opera|presto)\b%i', $_SERVER['HTTP_USER_AGENT'])) {
            $v->addError('Old browser', FORK_MESS_WARN);

            $this->log('Old browser');

            return $value;
        }

        if (
            empty($_SERVER['HTTP_ACCEPT'])
            || false === \strpos($_SERVER['HTTP_ACCEPT'], 'text/html')
            || empty($_SERVER['HTTP_ACCEPT_ENCODING'])
            || empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])
            || empty($_SERVER['HTTP_ORIGIN'])
        ) {
            $index += 5;
        }

        if (
            $this->multi
            && ! empty($_SERVER["CONTENT_TYPE"])
            && ! \str_starts_with($_SERVER["CONTENT_TYPE"], 'multipart/')
        ) {
            $index += 4;
        }

        if (! empty($_SERVER['HTTP_REFERER'])) {
            $ref = $this->c->Router->validate($_SERVER['HTTP_REFERER'], 'Index');
            $ref = \strstr($ref, '#', true) ?: $ref;

            if ($ref !== $_SERVER['HTTP_REFERER']) {
                $inc = 3;

                if (
                    ! empty($this->config['referers'])
                    && \is_array($this->config['referers'])
                ) {
                    foreach ($this->config['referers'] as $ref) {
                        if (false === \strpos($ref, '*')) {
                            if ($ref === $_SERVER['HTTP_REFERER']) {
                                $inc = 0;

                                break;
                            }

                        } else {
                            $ref = \preg_quote($ref, '%');
                            $ref = \str_replace('\\*', '.*?', $ref);

                            if (\preg_match("%^{$ref}$%D", $_SERVER['HTTP_REFERER'])) {
                                $inc = 0;

                                break;
                            }
                        }
                    }
                }

                $index += $inc;
            }
        }

        if ($index > 3)  {
            $v->addError('Bad browser', FORK_MESS_ERR);

            $this->log('Bad browser');
        }

        return $value;
    }

    protected function log(string $message): void
    {
        $this->c->Log->debug("TEST: {$message}", [
            'user'    => $this->c->user->fLog(),
            'headers' => true,
        ]);
    }
}
