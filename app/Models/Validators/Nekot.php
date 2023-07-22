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

class Nekot extends RulesValidator
{
    public function nekot(Validator $v, string $value): string
    {
        if (
            '' == $value
            || \substr(\preg_replace('%\D+%', '', $v->token), 0, 6) !== $value
        ) {
            $v->addError('Javascript disabled or bot', FORK_MESS_ERR);
        }

        return $value;
    }
}
