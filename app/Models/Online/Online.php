<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Online;

use ForkBB\Models\Model;
use ForkBB\Models\User\User;
use ForkBB\Models\Page;
use RuntimeException;

class Online extends Model
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Online';

    protected array $visits = [];
    protected array $online = [];

    /**
     * Флаг выполнения
     */
    protected int $done = 0;

    protected function isReady(): void
    {
        switch ($this->done) {
            case 1:
                return;
            case 2:
                throw new RuntimeException('Online user calculation is disabled on this page');
            default:
                throw new RuntimeException('The calc() method was not executed');
        }
    }

    /**
     * Время последнего визита в текущем сеансе ?
     */
    public function currentVisit(User $user): ?int
    {
        $this->isReady();

        return $this->visits[$user->id] ?? null;
    }

    /**
     * Статус пользователя
     */
    public function isOnline(User $user): bool
    {
        $this->isReady();

        return isset($this->online[$user->id]);
    }

    /**
     * Обработка данных пользователей онлайн
     * Обновление данных текущего пользователя
     * Возврат данных по пользователям онлайн
     */
    public function calc(Page $page): Online
    {
        if ($this->done) {
            return $this;
        }

        $this->done = 1;
        $position   = $page->onlinePos;

        if (null === $position) {
            return $this;
        }

        $this->updateUser($position);

        if (null === $page->onlineDetail) {
            $this->done = 2;

            return $this;
        }

        $detail = $page->onlineDetail && 1 === $this->c->config->b_users_online;
        $filter = $page->onlineFilter;

        $all       = 0;
        $now       = \time();
        $tOnline   = $now - $this->c->config->i_timeout_online;
        $tVisit    = $now - $this->c->config->i_timeout_visit;
        $users     = [];
        $guests    = [];
        $bots      = [];
        $needClean = false;

        if ($detail) {
            $query = 'SELECT o.user_id, o.ident, o.logged, o.o_position, o.o_name
                FROM ::online AS o';
        } else {
            $query = 'SELECT o.user_id, o.ident, o.logged
                FROM ::online AS o';
        }

        $stmt = $this->c->DB->query($query);

        while ($cur = $stmt->fetch()) {
            $this->visits[$cur['user_id']] = $cur['logged'];

            // посетитель уже не онлайн (или почти не онлайн)
            if ($cur['logged'] < $tOnline) {
                if ($cur['logged'] < $tVisit) {
                    $needClean = true;

                    if ($cur['user_id'] > 0) {
                        $this->c->users->updateLastVisit(
                            $this->c->users->create([
                                'id'       => $cur['user_id'],
                                'group_id' => FORK_GROUP_MEMBER,
                                'logged'   => $cur['logged'],
                            ])
                        );
                    }
                }

                continue;
            }

            // пользователи онлайн и общее количество
            $this->online[$cur['user_id']] = true;
            ++$all;

            if (! $detail) {
                continue;
            }

            // включен фильтр и проверка не пройдена
            if (
                $filter
                && $cur['o_position'] !== $position
            ) {
                continue;
            }

            // пользователь
            if ($cur['user_id'] > 0) {
                $users[$cur['user_id']] = $cur['o_name'];
            // гость
            } elseif ('' == $cur['o_name']) {
                $guests[] = $cur['ident'];
            // бот
            } else {
                $bots[$cur['o_name']][] = $cur['ident'];
            }
        }

        // удаление просроченных посетителей
        if ($needClean) {
            $vars = [
                ':visit' => $tVisit,
            ];
            $query = 'DELETE FROM ::online
                    WHERE logged<?i:visit';

            $this->c->DB->exec($query, $vars);
        }

        // обновление максимального значение посетителей онлайн
        if ($this->c->config->a_max_users['number'] < $all) {
            $this->c->config->a_max_users = [
                'number' => $all,
                'time'   => $now,
            ];

            $this->c->config->save();
        }

        $this->all    = $all;
        $this->detail = $detail;

        unset($this->online[0]);

        if ($detail) {
            $this->users  = $users;
            $this->guests = $guests;
            $this->bots   = $bots;
        }

        return $this;
    }

    /**
     * Обновление данных текущего посетителя
     */
    protected function updateUser(string $position): void
    {
        // Может быть делать меньше обновлений?
        if ($this->c->user->logged > 0) {
            $diff = \time() - $this->c->user->logged;

            if (
                $diff < 5
                || (
                    $position === $this->c->user->o_position
                    && $diff < $this->c->config->i_timeout_online / 10
                )
            ) {
                return;
            }
        }

        $guest = $this->c->user->isGuest;
        $vars  = [
            ':id'     => $this->c->user->id,
            ':ident'  => $guest ? $this->c->user->ip : '',
            ':logged' => \time(),
            ':pos'    => $position,
            ':name'   => $guest ? (string) $this->c->user->isBot : $this->c->user->username,
        ];

        if ($this->c->user->logged > 0) {
            if ($guest) {
                $query = 'UPDATE ::online
                    SET logged=?i:logged, o_position=?s:pos, o_name=?s:name
                    WHERE user_id=0 AND ident=?s:ident';
            } else {
                $query = 'UPDATE ::online
                    SET logged=?i:logged, o_position=?s:pos
                    WHERE user_id=?i:id';
            }
        } else {
            switch ($this->c->DB->getType()) {
                case 'mysql':
                    $query = 'INSERT IGNORE INTO ::online (user_id, ident, logged, o_position, o_name)
                        VALUES (?i:id, ?s:ident, ?i:logged, ?s:pos, ?s:name)';

                    break;
                case 'sqlite':
                case 'pgsql':
                    $query = 'INSERT INTO ::online (user_id, ident, logged, o_position, o_name)
                        VALUES (?i:id, ?s:ident, ?i:logged, ?s:pos, ?s:name)
                        ON CONFLICT(user_id, ident) DO NOTHING';

                    break;
                default:
                    $query = 'INSERT INTO ::online (user_id, ident, logged, o_position, o_name)
                        SELECT tmp.*
                        FROM (SELECT ?i:id AS f1, ?s:ident AS f2, ?i:logged AS f3, ?s:pos AS f4, ?s:name AS f5) AS tmp
                        WHERE NOT EXISTS (
                            SELECT 1
                            FROM ::online
                            WHERE user_id=?i:id AND ident=?s:ident
                        )';

                    break;
            }
        }

        $this->c->DB->exec($query, $vars);
    }

    /**
     * Удаление пользователя из таблицы online
     */
    public function delete(User $user): void
    {
        if ($user->isGuest) {
            $vars = [
                ':ip' => $user->ip,
            ];
            $query = 'DELETE
                FROM ::online
                WHERE user_id=0 AND ident=?s:ip';
        } else {
            $vars = [
                ':id' => $user->id,
            ];
            $query = 'DELETE
                FROM ::online
                WHERE user_id=?i:id';
        }

        $this->c->DB->exec($query, $vars);
    }
}
