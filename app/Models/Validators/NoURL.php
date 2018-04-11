<?php

namespace ForkBB\Models\Validators;

use ForkBB\Core\Validator;
use ForkBB\Core\Validators;

class NoURL extends Validators
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
        if ((! empty($flag) || '1' != $this->c->user->g_post_links)
            && \preg_match('%https?://|www\.%i', $value)
        ) {
            $v->addError('The :alias contains a link');
        }
        return $value;
    }
}
