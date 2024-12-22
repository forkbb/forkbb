<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\User;

use ForkBB\Models\Manager;
use ForkBB\Models\User\User;
use RuntimeException;

class Users extends Manager
{
    const CACHE_KEY = 'guest';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Users';

    /**
     * Создает новую модель пользователя
     */
    public function create(array $attrs = []): User
    {
        return $this->c->UserModel->setModelAttrs($attrs);
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
        $result   = [];
        $data     = [];
        $preGuest = false;

        foreach ($ids as $id) {
            if (0 === $id) { // это гость, его грузим через guest()
                $preGuest = true;

                continue;

            } elseif ($this->isset($id)) {
                $result[$id] = $this->get($id);

            } else {
                $result[$id] = null;
                $data[]      = $id;

                $this->set($id, null);
            }
        }

        if (true === $preGuest) {
            $this->guest();
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
        if ('' === $name) {
            return null;

        } else {
            return $this->returnUser($this->Load->loadByName($name, $caseInsencytive));
        }
    }

    /**
     * Получает пользователя по email
     */
    public function loadByEmail(string $email): ?User
    {
        if ('' === $email) {
            return null;

        } else {
            return $this->returnUser($this->Load->loadByEmail($email));
        }
    }

    /**
     * Обновляет данные пользователя
     */
    public function update(User $user): User
    {
        return $this->Save->update($user);
    }

    /**
     * Добавляет новую запись в таблицу пользователей
     */
    public function insert(User $user): int
    {
        $id = $this->Save->insert($user);

        $this->set($id, $user);

        return $id;
    }

    /**
     * Создает гостя
     */
    public function guest(array $attrs = []): User
    {
        $cache = $this->c->Cache->get(self::CACHE_KEY);

        if (! \is_array($cache)) {
            $cache = $this->c->groups->get(FORK_GROUP_GUEST)->getModelAttrs();

            if (true !== $this->c->Cache->set(self::CACHE_KEY, $cache)) {
                throw new RuntimeException('Unable to write value to cache - ' . self::CACHE_KEY);
            }
        }

        $set = [
            'id'          => 0,
            'group_id'    => FORK_GROUP_GUEST,
            'time_format' => 1,
            'date_format' => 1,
        ] + $attrs;

        if (isset($this->c->config->a_guest_set)) {
            $set += $this->c->config->a_guest_set;
        }

        return $this->create($set + $cache);
    }

    /**
     * Сбрасывает кеш гостя
     */
    public function resetGuest(): Users
    {
        if (true !== $this->c->Cache->delete(self::CACHE_KEY)) {
            throw new RuntimeException('Unable to remove key from cache - ' . self::CACHE_KEY);
        }

        return $this;
    }
}
