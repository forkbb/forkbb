<?php

namespace ForkBB\Models\Validators;

use ForkBB\Core\Validator;
use ForkBB\Core\Validators;
use ForkBB\Models\User\Model as User;
use function \ForkBB\__;

class Email extends Validators
{
    /**
     * Проверяет email
     * WARNING!!!
     * Если 4-ым параметром передан гость, то проверка уникальности email не проводится
     *
     * @param Validator $v
     * @param string $email
     * @param string $attrs
     * @param mixed $originalUser
     *
     * @return string
     */
    public function email(Validator $v, $email, $attrs, $originalUser): ?string
    {
        // поле отсутствует
        if (null === $email) {
            return null;
        // проверка длины email
        } elseif (\mb_strlen($email, 'UTF-8') > $this->c->MAX_EMAIL_LENGTH) {
            $v->addError('Long email');

            return $email;
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
        $user  = $this->c->users->create();
        $user->__email = $email; // + вычисление email_normal

        // провеерка бана email
        if (
            $ok
            && isset($attrs['noban'])
            && $this->c->bans->isBanned($user) > 0
        ) {
            $v->addError('Banned email');
            $ok = false;
        }
        // проверка наличия 1 пользователя с этим email
        if (
            $ok
            && isset($attrs['exists'])
        ) {
            $user = $this->c->users->loadByEmail($email); // ???? перехват исключения?

            if (! $user instanceof User) {
                $v->addError('Invalid email');
                $ok = false;
            }
        }
        // проверка уникальности email
        if (
            $ok
            && isset($attrs['unique'])
            && (
                ! $originalUser instanceof User
                || ! $originalUser->isGuest
            )
        ) {
            if ($user->isGuest) {
                $user = $this->c->users->loadByEmail($email); // ???? перехват исключения?
            }

            if (
                $user instanceof User
                && $originalUser instanceof User
                && $user->id !== $originalUser->id
            ) {
                $ok = false;
            }

            if (false === $ok) {
                $v->addError('Dupe email');
            }
        }
        // проверка на флуд интервал
        if (
            $ok
            && isset($attrs['flood'])
        ) {
            if (
                $originalUser instanceof User
                && ! $originalUser->isGuest
            ) {
                $flood = \time() - $originalUser->last_email_sent;
            } elseif (
                $user instanceof User
                && ! $user->isGuest
            ) {
                $flood = \time() - $user->last_email_sent;
            } else {
                $flood = $this->c->FLOOD_INTERVAL;
            }
            if ($flood < $this->c->FLOOD_INTERVAL) {
                $v->addError(__('Account email flood', (int) (($this->c->FLOOD_INTERVAL - $flood) / 60)), 'e');
                $ok = false;
            }
        }
        // возврат данных пользователя через 4-ый параметр
        if (
            $ok
            && $originalUser instanceof User
            && $originalUser->id < 1
            && $user instanceof User
            && ! $user->isGuest
        ) {
            $originalUser->setAttrs($user->getAttrs());
        }

        return $email;
    }
}
