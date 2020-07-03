<?php

namespace ForkBB\Models\User;

use ForkBB\Models\ManagerModel;
use ForkBB\Models\User\Model as User;

class Manager extends ManagerModel
{
    /**
     * Создает новую модель пользователя
     *
     * @param array $attrs
     *
     * @return User
     */
    public function create(array $attrs = []): User
    {
        return $this->c->UserModel->setAttrs($attrs);
    }

    /**
     * Получает пользователя по id
     */
    public function load(int $id): ?User
    {
        if ($this->isset($id)) {
            return $this->get($id);
        } else {
            $user = $this->Load->load($id);
            $this->set($id, $user);

            return $user;
        }
    }

    /**
     * Получает массив пользователей по ids
     */
    public function loadByIds(array $ids): array
    {
        $result = [];
        $data   = [];

        foreach ($ids as $id) {
            if ($this->isset($id)) {
                $result[$id] = $this->get($id);
            } else {
                $result[$id] = null;
                $data[]      = $id;
                $this->set($id, null);
            }
        }

        if (empty($data)) {
            return $result;
        }

        foreach ($this->Load->loadByIds($data) as $user) {
            if ($user instanceof User) {
                $result[$user->id] = $user;
                $this->set($user->id, $user);
            }
        }

        return $result;
    }

    /**
     * Возвращает результат
     */
    protected function returnUser(?User $user): ?User
    {
        if ($user instanceof User) {
            $loadedUser = $this->get($user->id);

            if ($loadedUser instanceof User) {
                return $loadedUser;
            } else {
                $this->set($user->id, $user);
                return $user;
            }
        } else {
            return null;
        }
    }

    /**
     * Получает пользователя по имени
     */
    public function loadByName(string $name, bool $caseInsencytive = false): ?User
    {
        return $this->returnUser($this->Load->loadByName($name, $caseInsencytive));
    }

    /**
     * Получает пользователя по email
     */
    public function loadByEmail(string $email): ?User
    {
        return $this->returnUser($this->Load->loadByEmail($email));
    }

    /**
     * Обновляет данные пользователя
     *
     * @param User $user
     *
     * @return User
     */
    public function update(User $user): User
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
    public function insert(User $user): int
    {
        $id = $this->Save->insert($user);
        $this->set($id, $user);

        return $id;
    }
}
