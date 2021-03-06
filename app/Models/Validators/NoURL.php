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

class NoURL extends RulesValidator
{
    /**
     * Проверяет значение на отсутствие ссылки, если пользователю запрещено использовать ссылки или включен флаг принудительной проверки
     *
     * @param Validator $v
     * @param mixed $value
     * @param string $flag
     *
     * @return mixed
     */
    public function noURL(Validator $v, $value, $flag)
    {
        if (
            (
                ! empty($flag)
                || '1' != $this->c->user->g_post_links
            )
            && \preg_match('%https?://|www\.%i', $value)
        ) {
            $v->addError('The :alias contains a link');
        }

        return $value;
    }
}
