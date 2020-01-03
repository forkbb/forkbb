<?php

namespace ForkBB\Models\User;

use ForkBB\Models\ManagerModel;
use ForkBB\Models\User\Model as User;
use InvalidArgumentException;

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
     * Получение пользователя(ей) по id, массиву id или по модели User
     *
     * @param mixed $value
     *
     * @throws InvalidArgumentException
     *
     * @return mixed
     */
    public function load($value)
    {
        $error      = false;
        $result     = [];
        $returnUser = true;

        if ($value instanceof User) {
            $data = $value;
        } elseif (\is_int($value) && $value > 0) {
            $data = $value;
        } elseif (\is_array($value)) {
            $data = [];
            foreach ($value as $arg) {
                if (\is_int($arg) && $arg > 0) {
                    if ($this->get($arg) instanceof User) {
                        $result[$arg] = $this->get($arg);
                    } else {
                        $result[$arg] = false;
                        $data[]       = $arg;
                    }
                } else {
                    $error = true;
                }
            }
            $returnUser = false;
        } else {
            $error = true;
        }

        if ($error) {
            throw new InvalidArgumentException('Expected only integer, integer array or User');
        }

        if (! empty($data)) {
            foreach ($this->Load->load($data) as $user) {
                if ($user instanceof User) {
                    if ($this->get($user->id) instanceof User) {
                        $result[$user->id] = $this->get($user->id);
                    } else {
                        $result[$user->id] = $user;
                        $this->set($user->id, $user);
                    }
                }
            }
        }

        return $returnUser && 1 === \count($result) ? \array_pop($result) : $result;
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
