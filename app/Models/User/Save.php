<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\User;
use RuntimeException;

class Save extends Action
{
    /**
     * Обновляет данные пользователя
     */
    public function update(User $user): User
    {
        if (
            (! $user->isGuest && $user->id < 1)
            || ($user->isGuest && 0 !== $user->id)
        ) {
            throw new RuntimeException('Bad ID');
        }

        $modified = $user->getModified();

        if (empty($modified)) {
            return $user;
        }

        $values = $user->getAttrs();

        if ($user->isGuest) {
            $fileds = $this->c->dbMap->online;
            $table  = 'online';
            $where  = 'user_id=0 AND ident=?s';
        } else {
            $fileds = $this->c->dbMap->users;
            $table  = 'users';
            $where  = 'id=?i';
        }

        $set = $vars = [];
        $grChange   = false;
        $nameChange = false;

        foreach ($modified as $name) {
            if (! isset($fileds[$name])) {
                continue;
            }

            $vars[] = $values[$name];
            $set[]  = $name . '=?' . $fileds[$name];

            if ('username' === $name) {
                $nameChange = true;

                // пересчет username_normal при изменении username
                $name = 'username_normal';

                if (isset($fileds[$name])) {
                    $vars[] = $this->manager->normUsername($user->username);
                    $set[]  = $name . '=?' . $fileds[$name];
                }
                // пересчет username_normal при изменении username
            } elseif ('group_id' === $name) {
                $grChange = true;
            }
        }

        if (empty($set)) {
            return $user;
        }

        if (
            $user->isGuest
            && ! $user->isUnverified
        ) {
            $vars[] = $user->ip;
        } else {
            $vars[] = $user->id;
        }

        $set   = \implode(', ', $set);
        $query = "UPDATE ::{$table}
            SET {$set}
            WHERE {$where}";

        $this->c->DB->exec($query, $vars);
        $user->resModified();

        if ($nameChange) {
            $this->updateUsernameInOtherTables($user);
        }

        if ($grChange) {
            $this->c->admins->reset();
            $this->c->stats->reset();
        }

        return $user;
    }

    /**
     * Добавляет новую запись в таблицу пользователей
     */
    public function insert(User $user): int
    {
        if (null !== $user->id) {
            throw new RuntimeException('The model has ID');
        } elseif ($user->isGuest) {
            throw new RuntimeException('Unexpected guest');
        }

        // вычисление username_normal для нового пользователя
        $user->username_normal = $this->manager->normUsername($user->username);

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

        $set   = \implode(', ', $set);
        $set2  = \implode(', ', $set2);
        $query = "INSERT INTO ::users ({$set})
            VALUES ({$set2})";

        $this->c->DB->exec($query, $vars);
        $user->id = (int) $this->c->DB->lastInsertId();
        $user->resModified();

        if ($user->isAdmin) {
            $this->c->admins->reset();
        }
        if (! $user->isUnverified) {
            $this->c->stats->reset();
        }

        return $user->id;
    }

    /**
     * Обновляет username по всей(?) DB
     */
    protected function updateUsernameInOtherTables(User $user): void
    {
        if ($user->isGuest) {
            return;
        }

        $this->c->posts->updateUsername($user);
        $this->c->topics->updateUsername($user);
        $this->c->forums->updateUsername($user);
        $this->c->Online->updateUsername($user);
        $this->c->pms->updateUsername($user);

        // ???? и т.д.
    }
}
