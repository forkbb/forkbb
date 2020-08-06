<?php

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;
use ForkBB\Models\User\Model as User;

class Check extends Method
{
    /**
     * Проверяет наличие бана (для текущего пользователя) на основании имени пользователя/ip
     * Удаляет просроченные баны
     *
     * @param User $user
     *
     * @return bool
     */
    public function check(User $user): bool
    {
        // удаление просроченных банов
        if (! empty($this->model->banList)) { // ???? зачем при каждом запуске проверять просроченность?
            $ids = [];
            $now = \time();

            foreach ($this->model->banList as $id => $row) {
                if (
                    null !== $row['expire']
                    && $row['expire'] < $now
                ) {
                    $ids[] = $id;
                }
            }

            if (! empty($ids)) {
                $this->model->delete(...$ids);
            }
        }

        // админ
        if ($user->isAdmin) {
            return false;
        }

        // проверка гостя
        if ($user->isGuest) {
            if (! empty($this->model->ipList)) {
                $ip = $this->model->trimToNull($user->ip);

                if (null !== $ip) {
                    $list    = $this->model->ipList;
                    $letters = \str_split($this->model->ip2hex($ip));

                    foreach ($letters as $letter) {
                        if (! isset($list[$letter])) {
                            break;
                        } elseif (\is_array($list[$letter])) {
                            $list = $list[$letter];
                        } else {
                            $id = $list[$letter];

                            if (isset($this->model->banList[$id])) {
                                $user->__banInfo = $this->model->banList[$id];
                            }

                            return true;
                        }
                    }
                }
            }
        // проверка пользователя
        } else {
            $id = $this->model->isBanned($user);

            if ($id > 0) {
                if (isset($this->model->banList[$id])) {
                    $user->__banInfo = $this->model->banList[$id];
                }

                return true;
            }
        }

        return false;
    }
}
