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
use ForkBB\Models\User\Model as User;
use function \ForkBB\__;

class Username extends RulesValidator
{
    /**
     * Проверяет имя пользователя
     *
     * @param Validator $v
     * @param null|string $username
     * @param string $attrs
     * @param mixed $originalUser
     *
     * @return null|string
     */
    public function username(Validator $v, $username, $attrs, $originalUser): ?string
    {
        if ($originalUser instanceof User) {
            $id   = $originalUser->id;
            $old  = $originalUser->username;
        } else {
            $id   = null;
            $old  = null;
        }

        if ($old !== $username) {

            $user = $this->c->users->create(['id' => $id, 'username' => $username]);

            // 2-25 символов, буквы, цифры, пробел, подчеркивание, точка и тире
            if (! \preg_match($this->c->USERNAME_PATTERN, $username)) {
                $v->addError('Login format');
            // идущие подряд пробелы
            } elseif (\preg_match('%\s{2,}%u', $username)) {
                $v->addError('Username contains consecutive spaces');
            // цензура
            } elseif ($this->c->censorship->censor($username) !== $username) {
                $v->addError('Username censor');
            // username забанен
            } elseif ($this->c->bans->banFromName($username) > 0) {
                $v->addError('Banned username');
            // есть пользователь с похожим именем
            } elseif (
                empty($v->getErrors())
                && ! $this->c->users->isUniqueName($user)
            ) {
                $v->addError('Username not unique');
            }
        }

        return $username;
    }
}
