<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Validators;

use ForkBB\Core\RulesValidator;
use ForkBB\Core\Validator;

class Html extends RulesValidator
{
    /**
     * Обрабатывает html код в соответствии с заданными правилами
     *
     * @param Validator $v
     * @param string $value
     *
     * @return mixed
     */
    public function html(Validator $v, string $value)
    {
        $errors = [];
        $result = $this->c->HTMLCleaner->setConfig()->parse($value, $errors);

        if (empty($errors)) {
            return $result;
        } else {
            foreach ($errors as $args) {
                $v->addError($args);
            }

            return $value;
        }
    }
}
