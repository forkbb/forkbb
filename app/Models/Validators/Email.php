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
use ForkBB\Models\User\User;

class Email extends RulesValidator
{
    /**
     * Проверяет email
     */
    public function email(Validator $v, string $email, string $attrs, mixed $originalUser): ?string
    {
        // поле отсутствует
        if ($v->noValue($email)) {
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

        // провеерка бана email
        if (
            $ok
            && (
                isset($attrs['noban'])
                || isset($attrs['nosoloban'])
            )
        ) {
            $banType = $this->c->bans->isBanned(
                $this->c->users->create(['email' => $email])
            );

            if (
                $banType > 0
                && (
                    isset($attrs['noban'])
                    || (
                        1 === $banType
                        && isset($attrs['nosoloban'])
                    )
                )
            ) {
                $v->addError('Banned email');
                $ok = false;
            }
        }
        // проверка наличия 1 пользователя с этим email
        if (
            $ok
            && isset($attrs['exists'])
        ) {
            $user = $this->c->users->loadByEmail($email);

            if (! $user instanceof User) {
                $v->addError('Invalid email');
                $ok = false;
            }
        }
        // проверка уникальности email
        if (
            $ok
            && isset($attrs['unique'])
        ) {
            $user = $this->c->users->loadByEmail($email); // ???? exists и unique вместе же не должны встречаться!? O_o

            if (
                $user instanceof User
                && (
                    ! $originalUser instanceof User
                    || $originalUser->isGuest
                    || $user->id !== $originalUser->id
                )
            ) {
                $v->addError('Dupe email');

                $ok = false;

            // дополнительная проверка по связанным аккаунтам
            } else {
                $id = $this->c->providerUser->findByEmail($email);

                if (
                    $id > 0
                    && (
                        ! $originalUser instanceof User
                        || $originalUser->isGuest
                        || $id !== $originalUser->id
                    )
                ) {
                    $v->addError('Dupe email (OAuth)');

                    $ok = false;
                }
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
                $v->addError(['Account email flood', (int) (($this->c->FLOOD_INTERVAL - $flood) / 60)], FORK_MESS_ERR);

                $ok = false;
            }
        }
        // возврат данных пользователя через 4-ый параметр
        if (
            $ok
            && $originalUser instanceof User
            && null === $originalUser->id
            && null === $originalUser->group_id
            && $user instanceof User
            && ! $user->isGuest
        ) {
            $originalUser->setModelAttrs($user->getModelAttrs());
        }

        return $email;
    }
}
