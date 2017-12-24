<?php

namespace ForkBB\Models\User;

use ForkBB\Models\ManagerModel;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class Manager extends ManagerModel
{
    /**
     * Создает новую модель пользователя
     * 
     * @param array $attrs
     * 
     * @return User
     */
    public function create(array $attrs = [])
    {
        return $this->c->UserModel->setAttrs($attrs);
    }

    /**
     * Получение пользователя по условию
     * 
     * @param mixed $value
     * @param string $field
     * 
     * @return int|User
     */
    public function load($value, $field = 'id')
    {
        $user = $field === 'id' ? $this->get($value) : null;

        if (! $user instanceof User) {
            $user = $this->Load->load($value, $field);

            if ($user instanceof User) {
                $test = $this->get($user->id);

                if ($test instanceof User) {
                    return $test;
                }

                $this->set($user->id, $user);
            }
        }

        return $user;
    }

    /**
     * Обновляет данные пользователя
     *
     * @param User $user
     * 
     * @return User
     */
    public function update(User $user)
    {
        return $this->Save->update($user);
    }

    /**
     * Добавляет новую запись в таблицу пользователей
     *
     * @param User $user
     * 
     * @return int
     */
    public function insert(User $user)
    {
        $id = $this->Save->insert($user);
        $this->set($id, $user);
        return $id;
    }
}
