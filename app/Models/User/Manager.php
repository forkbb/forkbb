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
     * @param mixed ...$args
     *
     * @throws InvalidArgumentException
     *
     * @return mixed
     */
    public function load(...$args)
    {
        $result = [];
        $count = \count($args);
        $countID = 0;
        $countUser = 0;
        $reqIDs = [];
        $error = false;
        $user = null;

        foreach ($args as $arg) {
            if ($arg instanceof User) {
                ++$countUser;
                $user = $arg;
            } elseif (\is_int($arg) && $arg > 0) {
                ++$countID;
                if ($this->get($arg) instanceof User) {
                    $result[$arg] = $this->get($arg);
                } else {
                    $result[$arg] = false;
                    $reqIDs[] = $arg;
                }
            } else {
                $error = true;
            }
        }

        if ($error || $countUser * $countID > 0 || $countUser > 1 || ($countID > 0 && $count > $countID)) {
            throw new InvalidArgumentException('Expected only integer, integer array or User');
        }

        if (! empty($reqIDs) || null !== $user) {
            if (null !== $user) {
                $data = $user;
            } elseif (1 === \count($reqIDs)) {
                $data = \array_pop($reqIDs);
            } else {
                $data = $reqIDs;
            }

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

        return 1 === \count($result) ? \array_pop($result) : $result;
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
