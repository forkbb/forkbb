<?php

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;
use ForkBB\Models\User\Model as User;

class IsBanned extends Method
{
    /**
     * Проверяет наличие бана на основании имени пользователя и(или) email
     *
     * @param User $user
     *
     * @return int
     */
    public function isBanned(User $user): int
    {
        $name  = $this->model->trimToNull($user->username, true);
        // бан имени пользователя
        if (
            null !== $name
            && isset($this->model->userList[$name])
        ) {
            return $this->model->userList[$name];
        }
        // бан email
        if (
            $user->isGuest
            && ! empty($this->model->emailList)
            && $user->email && $user->email_normal
        ) { // ????
            $email = $this->model->trimToNull($user->email_normal);
            $stage = 0;

            do {
                if (isset($this->model->emailList[$email])) {
                    return $this->model->emailList[$email];
                }

                switch ($stage) {                               // "super@user"@example.com
                    case 0:
                        $pos = \strrpos($email, '@');

                        if (false !== $pos) {
                            $email = \substr($email, $pos + 1); // -> example.com
                            break;
                        }

                        ++$stage;
                    case 1:
                        $email = '.' . $email;                  // -> .example.com
                        $pos = true;
                        break;
                    default:
                        $pos = \strpos($email, '.', 1);

                        if (false !== $pos) {
                            $email = \substr($email, $pos);     // -> .com
                        }

                        break;
                }

                ++$stage;
            } while (false !== $pos);
        }

        return 0;
    }
}
