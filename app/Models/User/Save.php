<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class Save extends Action
{
    /**
     * Обновляет данные пользователя
     *
     * @param User $user
     * 
     * @throws RuntimeException
     * 
     * @return User
     */
    public function update(User $user)
    {
        if ($user->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }
        $modified = $user->getModified();
        if (empty($modified)) {
            return $user;
        }
        $values = $user->getAttrs();

        if ($user->isGuest && ! $user->isUnverified) {
            $fileds = $this->c->dbMap->online;
            $table  = 'online';
            $where  = 'user_id=1 AND ident=?s';
        } else {
            $fileds = $this->c->dbMap->users;
            $table  = 'users';
            $where  = 'id=?i';
        }
        $set = $vars = [];
        foreach ($modified as $name) {
            if (! isset($fileds[$name])) {
                continue;
            }
            $vars[] = $values[$name];
            $set[] = $name . '=?' . $fileds[$name];
        }
        if (empty($set)) {
            return $user;
        }
        if ($user->isGuest && ! $user->isUnverified) {
            $vars[] = $user->ip;
        } else {
            $vars[] = $user->id;
        }
        $this->c->DB->query('UPDATE ::' . $table . ' SET ' . implode(', ', $set) . ' WHERE ' . $where, $vars);
        $user->resModified();

        return $user;
    }

    /**
     * Добавляет новую запись в таблицу пользователей
     *
     * @param User $user
     * 
     * @throws RuntimeException
     * 
     * @return int
     */
    public function insert(User $user)
    {
        if (null !== $user->id) {
            throw new RuntimeException('The model has ID');
        }
        $attrs  = $user->getAttrs();
        $fileds = $this->c->dbMap->users;
        $set = $set2 = $vars = [];
        foreach ($attrs as $key => $value) {
            if (! isset($fileds[$key])) {
                continue;
            }
            $vars[] = $value;
            $set[]  = $key;
            $set2[] = '?' . $fileds[$key];
        }
        if (empty($set)) {
            throw new RuntimeException('The model is empty');
        }
        $this->c->DB->query('INSERT INTO ::users (' . implode(', ', $set) . ') VALUES (' . implode(', ', $set2) . ')', $vars);
        $user->id = $this->c->DB->lastInsertId();
        $user->resModified();

        return $user->id;
    }
}
