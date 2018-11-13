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
    public function isBanned(User $user)
    {
        $name  = $this->model->trimToNull($user->username, true);
        if (null !== $name && isset($this->model->userList[$name])) {
            return 1;
        }
        $email = $this->model->trimToNull($user->email);
        if (null !== $email) {
            foreach ($this->model->otherList as $cur) {
                if (null === $cur['email']) {
                    continue;
                } elseif ($email == $cur['email']) {
                    return 2;
                } elseif (false === \strpos($cur['email'], '@')) {
                    $len = \strlen($cur['email']);
                    if ('.' === $cur['email']{0}) {
                        if (\substr($email, -$len) === $cur['email']) {
                            return 2;
                        }
                    } else {
                        $tmp = \substr($email, -1-$len);
                        if ($tmp === '.' . $cur['email'] || $tmp === '@' . $cur['email']) {
                            return 2;
                        }
                    }
                }
            }
        }
        return 0;
    }
}
