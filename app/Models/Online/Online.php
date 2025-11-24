<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
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
        $upUsers   = [];
        $delGuests = [];

        if ($detail) {
            $query = 'SELECT o.user_id, o.ident, o.logged, o.o_position, o.o_name, o.o_misc
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
                    if ($cur['user_id'] > 0) {
                        $upUsers[$cur['user_id']] = $cur['logged'];

                    } else {
                        $delGuests[] = $cur['ident'];
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

            } else {
                $name  = '' === $cur['o_name'] ? 'Unknown' : $cur['o_name'];

                if (128 & $cur['o_misc']) {
                    if (1 === $this->c->config->b_block_hidden_bots) {
                        $bots["[Blocked] {$name}"][] = $cur['ident'];

                    } else {
                        $bots["[Hidden Bot] {$name}"][] = $cur['ident'];
                    }

                // бот
                } elseif (64 & $cur['o_misc']) {
                    $bots[$name][] = $cur['ident'];

                // гость
                } elseif (48 === (48 & $cur['o_misc'])) {
                    $guests[] = $cur['ident'];

                } else {
                    $bots["[ ? ] {$name}"][] = $cur['ident'];
                }
            }
        }

        // удаление просроченных посетителей
        if ($upUsers) {
            if (\count($upUsers) > 1) {
                \ksort($upUsers, \SORT_NUMERIC);
            }

            foreach ($upUsers as $id => $logged) {
                $this->c->users->updateLastVisit(
                    $this->c->users->create([
                        'id'       => $id,
                        'group_id' => FORK_GROUP_MEMBER,
                        'logged'   => $logged,
                    ])
                );
            }

            $vars = [
                ':ids' => \array_keys($upUsers),
            ];
            $query = 'DELETE FROM ::online
                WHERE user_id IN (?ai:ids)';

            $this->c->DB->exec($query, $vars);
        }

        // удаление просроченных гостей
        if ($delGuests) {
            if (\count($delGuests) > 1) {
                \sort($delGuests, \SORT_STRING);
            }

            $vars = [
                ':idents' => $delGuests,
            ];
            $query = 'DELETE FROM ::online
                WHERE user_id=0 AND ident IN (?as:idents)';

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
        $user = $this->c->user;

        if ($user->isGuest) {
            $ident = $user->ip;
            $name  = $user->botName;
            $misc  = $old = $user->o_misc ?? 0;

            if (
                false === FORK_CLI
                && empty(128 & $misc)
            ) {
                if ($user->logged > 0) {
                    $bBot  = (bool) (64 & $misc);
                    $count = 15 & $misc;

                    if (
                        $count > 1
                        || ($bBot xor '' !== $name)
                    ) {
                        $misc = 128;

                    } elseif ('' !== $name) {
                        $misc = 64;

                    } elseif (
                        (48 !== (48 & $misc))
                        && $this->c->curReqVisible > 0
                    ) {
                        if ($count < 2) {
                            $this->c->curReqVisible = 2;
                        }

                        ++$misc;
                    }

                } else {
                    if ('' !== $name) {
                        $misc = 64;

                    } elseif ($this->c->curReqVisible > 0) {
                        ++$misc;
                        $this->c->curReqVisible = 2;
                    }
                }
            }

        } else {
            $ident = '';
            $name  = $user->username;
            $misc  = $old = 0;
        }

        // Может быть делать меньше обновлений?
        if (
            $old === $misc
            && $user->logged > 0
        ) {
            $diff = \time() - $user->logged;

            if (
                $diff < 3
                || (
                    $position === $user->o_position
                    && $diff < $this->c->config->i_timeout_online / 10
                )
            ) {
                return;
            }
        }

        $vars  = [
            ':id'     => $user->id,
            ':ident'  => $ident,
            ':logged' => \time(),
            ':pos'    => $position,
            ':name'   => $name,
            ':misc'   => $misc,
        ];

        if ($user->logged > 0) {
            if ($user->isGuest) {
                $query = 'UPDATE ::online
                    SET logged=?i:logged, o_position=?s:pos, o_name=?s:name, o_misc=?i:misc
                    WHERE user_id=0 AND ident=?s:ident';

            } else {
                $query = 'UPDATE ::online
                    SET logged=?i:logged, o_position=?s:pos
                    WHERE user_id=?i:id';
            }

        } else {
            $query = match ($this->c->DB->getType()) {
                'mysql' => 'INSERT IGNORE INTO ::online (user_id, ident, logged, o_position, o_name, o_misc)
                    VALUES (?i:id, ?s:ident, ?i:logged, ?s:pos, ?s:name, ?i:misc)',

                'sqlite', 'pgsql' => 'INSERT INTO ::online (user_id, ident, logged, o_position, o_name, o_misc)
                    VALUES (?i:id, ?s:ident, ?i:logged, ?s:pos, ?s:name, ?i:misc)
                    ON CONFLICT(user_id, ident) DO NOTHING',

                default => 'INSERT INTO ::online (user_id, ident, logged, o_position, o_name, o_misc)
                    SELECT tmp.*
                    FROM (SELECT ?i:id AS f1, ?s:ident AS f2, ?i:logged AS f3, ?s:pos AS f4, ?s:name AS f5, ?i:misc AS f6) AS tmp
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM ::online
                        WHERE user_id=?i:id AND ident=?s:ident
                    )',
            };
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

    /**
     * Прописывает флаги текущего гостя
     */
    public function flags(string $name): void
    {
        $user = $this->c->user;
        $misc = $new = $user->o_misc ?? 0;

        if (
            ! $user->isGuest
            || 0 === (15 & $misc)
        ) {
            return;

        } elseif ('style' === $name) {
            $new = 16 | $new;

        } elseif (
            'img' === $name
            && 16 === (16 & $misc)
        ) {
            $new = 32 | $new;
        }

        if ($new === $misc) {
            return;
        }

        $vars  = [
            ':ident' => $user->ip,
            ':misc'  => $new,
        ];

        $query = 'UPDATE ::online
            SET o_misc=?i:misc
            WHERE user_id=0 AND ident=?s:ident';

        $this->c->DB->exec($query, $vars);
    }
}
