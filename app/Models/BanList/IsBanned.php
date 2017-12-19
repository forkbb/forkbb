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
        $name  = $this->model->trimToNull($this->model->username, true);
        if (null !== $name && isset($this->model->userList[$name])) {
            return 1;
        }
        $email = $this->model->trimToNull($this->model->email);
        if (null !== $email) {
            foreach ($this->model->otherList as $row) {
                if (null === $row['email']) {
                    continue;
                } elseif ($email == $row['email']) {
                    return 2;
                }
            }
        }
        return 0;
    }
}
