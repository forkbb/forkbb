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
     * @return mixed
     */
    public function load($value, $field = 'id')
    {
        if (\is_array($value)) {
            $result = \array_flip($value); // ???? а если пользователь не найдется?
            if ($field === 'id') {
                $temp = [];
                foreach ($value as $id) {
                    if ($this->get($id) instanceof User) {
                        $result[$id] = $this->get($id);
                    } else {
                        $temp[] = $id;
                    }
                }
                $value = $temp;
            }
            if (empty($value)) {
                return $result;
            }
            foreach ($this->Load->load($value, $field) as $user) {
                if ($user instanceof User) {
                    if ($this->get($user->id) instanceof User) {
                        $result[$user->id] = $this->get($user->id);
                    } else {
                        $result[$user->id] = $user;
                        $this->set($user->id, $user);
                    }
                }
            }

            return $result;
        } else {
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
