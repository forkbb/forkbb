<?php

namespace ForkBB\Models\Validators;

use ForkBB\Core\Validator;
use ForkBB\Core\Validators;
use ForkBB\Models\User\Model as User;

class Email extends Validators
{
    const FLOOD = 3600;

    /**
     * Проверяет email
     * WARNING!!!
     * Если передан гость 4-ым параметром, то проверка уникальности email не проводится
     *
     * @param Validator $v
     * @param string $email
     * @param string $attrs
     * @param mixed $originalUser
     *
     * @return string
     */
    public function email(Validator $v, $email, $attrs, $originalUser)
    {
        // поле отсутствует
        if (null === $email) {
            return null;
        // это не email
        } elseif (false === ($result = $this->c->Mail->valid($email, true))) {
            $v->addError('The :alias is not valid email');
            return $email;
        // есть другие ошибки
        } elseif (! empty($v->getErrors())) {
            return $result;
        }

        $email = $result;
        $attrs = \array_flip(\explode(',', $attrs));
        $ok    = true;
        $user  = true;

        // email забанен
        if ($ok && isset($attrs['banned']) && $this->c->bans->isBanned($this->c->users->create(['email' => $email])) > 0) {
            $v->addError('Banned email');
            $ok = false;
        }
        // отсутствует пользователь с таким email (или их больше одного O_o)
        if (isset($attrs['exists'])) {
            $user = $this->c->users->load($email, 'email');

            if (! $user instanceof User) {
                $v->addError('Invalid email');
                $ok = false;
            }
        }
        // email не уникален
        if ($ok && isset($attrs['unique']) && (! $originalUser instanceof User || ! $originalUser->isGuest)) {
            if (true === $user) {
                $user = $this->c->users->load($email, 'email');
            }

            $id = $originalUser instanceof User ? $originalUser->id : true;

            if (($user instanceof User && $id !== $user->id) || (! $user instanceof User && 0 !== $user)) {
                $v->addError('Dupe email');
                $ok = false;
            }
        }
        // проверка на флуд интервал
        if ($ok && isset($attrs['flood'])) {
            if ($originalUser instanceof User && ! $originalUser->isGuest) {
                $flood = \time() - $originalUser->last_email_sent;
            } elseif ($user instanceof User) {
                $flood = \time() - $user->last_email_sent;
            } else {
                $flood = self::FLOOD;
            }
            if ($flood < self::FLOOD) {
                $v->addError(\ForkBB\__('Email flood', (int) ((self::FLOOD - $flood) / 60)), 'e');
                $ok = false;
            }
        }
        // возврат данных пользователя через 4-ый параметр
        if ($ok && $originalUser instanceof User && $originalUser->id < 1 && $user instanceof User) {
            $originalUser->setAttrs($user->getAttrs());
        }

        return $email;
    }
}
