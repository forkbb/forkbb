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
    public function check(User $user)
    {
        // админ
        if ($user->isAdmin) {
            return false;
        }

        // удаление просроченных банов
        $now = time();
        $ids = [];
        foreach ($this->model->otherList as $id => $row) {
            if (null !== $row['expire'] && $row['expire'] < $now) {
                $ids[] = $id;
            }
        }
        if (! empty($ids)) {
            $this->model->delete($ids);
        }

        // проверка гостя
        if ($user->isGuest) {
            $ip = $this->model->trimToNull($user->ip);
            if (null === $ip) {
                return false; //????
            }
            $add = strpos($ip, ':') === false ? '.' : ':'; //????
            $ip .= $add;
            foreach ($this->model->ipList as $addr => $id) {
                $addr .= $add;
                if (substr($ip, 0, strlen($addr)) == $addr) {
                    if (isset($this->model->otherList[$id])) {
                        $user->__banInfo = $this->model->otherList[$id];
                    }
                    return true;
                }
            }
        // проверка пользователя
        } else {
            $name = $this->model->trimToNull($user->username, true);
            if (isset($this->model->userList[$name])) {
                $id = $this->model->userList[$name];
                if (isset($this->model->otherList[$id])) {
                    $user->__banInfo = $this->model->otherList[$id];
                }
                return true;
            }
        }
        return false;
    }
}
