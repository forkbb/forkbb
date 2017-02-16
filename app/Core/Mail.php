<?php

namespace ForkBB\Core;

class Mail
{
    /**
     * Валидация email
     * @param mixed $email
     * @return bool
     */
    public function valid($email)
    {
        return is_string($email)
            && strlen($email) < 255
            && preg_match('%^.+@.+$%D', $email);
    }
}
