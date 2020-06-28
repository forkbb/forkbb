<?php

namespace ForkBB\Models\Validators;

use ForkBB\Core\Validator;
use ForkBB\Core\Validators;
use ForkBB\Models\User\Model as User;

class Username extends Validators
{
    /**
     * Проверяет имя пользователя
     *
     * @param Validator $v
     * @param null|string $username
     * @param string $z
     * @param mixed $originalUser
     *
     * @return null|string
     */
    public function username(Validator $v, $username, $z, $originalUser): ?string
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
            if (! \preg_match('%^(?=.{2,25}$)\p{L}[\p{L}\p{N}\x20\._-]+$%uD', $username)) {
                $v->addError('The :alias is not valid format'); // ???? выводить отдельное сообщение для username
            // username = Гость
            } elseif (\preg_match('%^(guest|' . \preg_quote(\ForkBB\__('Guest'), '%') . ')$%iu', $username)) { // ???? а зачем?
                $v->addError('Username guest');
            // цензура
            } elseif ($this->c->censorship->censor($username) !== $username) {
                $v->addError('Username censor');
            // username забанен
            } elseif ($this->c->bans->isBanned($user) > 0) {
                $v->addError('Banned username');
            // есть пользователь с похожим именем
            } elseif (
                empty($v->getErrors())
                && ! $this->c->users->isUniqueName($user) // ???? как вычислить похожее?
            ) {
                $v->addError('Username not unique');
            }
        }

        return $username;
    }
}
