<?php

namespace ForkBB\Models\Online;

use ForkBB\Models\Model as ParentModel;
use ForkBB\Models\User\Model as User;
use ForkBB\Models\Page;

class Model extends ParentModel
{
    protected $visits = [];
    protected $online = [];

    public function lastVisit(User $user): ?int
    {
        return $this->visits[$user->id] ?? null;
    }

    public function isOnline(User $user): bool
    {
        return isset($this->online[$user->id]);
    }

    /**
     * Обработка данных пользователей онлайн
     * Обновление данных текущего пользователя
     * Возврат данных по пользователям онлайн
     *
     * @param Page $page
     *
     * @return Online\Model
     */
    public function calc(Page $page): self
    {
        if ($this->done) {
            return $this;
        }
        $this->done = true;

        $position = $page->onlinePos;
        if (null === $position) {
            return $this;
        }
        $detail = $page->onlineDetail && '1' == $this->c->config->o_users_online;
        $filter = $page->onlineFilter;

        $this->updateUser($position);

        $all       = 0;
        $now       = \time();
        $tOnline   = $now - $this->c->config->o_timeout_online;
        $tVisit    = $now - $this->c->config->o_timeout_visit;
        $users     = [];
        $guests    = [];
        $bots      = [];
        $needClean = false;

        if ($detail) {
            $sql = 'SELECT o.user_id, o.ident, o.logged, o.o_position, o.o_name FROM ::online AS o ORDER BY o.logged';
        } else {
            $sql = 'SELECT o.user_id, o.ident, o.logged FROM ::online AS o ORDER BY o.logged';
        }
        $stmt = $this->c->DB->query($sql);

        while ($cur = $stmt->fetch()) {
            $this->visits[$cur['user_id']] = $cur['logged'];

            // посетитель уже не онлайн (или почти не онлайн)
            if ($cur['logged'] < $tOnline) {
                if ($cur['logged'] < $tVisit) {
                    $needClean = true;

                    if ($cur['user_id'] > 1) {
                        $this->c->DB->exec('UPDATE ::users SET last_visit=?i:last WHERE id=?i:id', [':last' => $cur['logged'], ':id' => $cur['user_id']]); //????
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
            if ($cur['user_id'] > 1) {
                $users[$cur['user_id']] = $cur['ident'];
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
            $this->c->DB->exec('DELETE FROM ::online WHERE logged<?i:visit', [':visit' => $tVisit]);
        }

        // обновление максимального значение посетителей онлайн
        if ($this->c->config->st_max_users < $all) {
            $this->c->config->st_max_users      = $all;
            $this->c->config->st_max_users_time = $now;
            $this->c->config->save();
        }

        $this->all    = $all;
        $this->detail = $detail;

        unset($this->online[1]);

        if ($detail) {
            $this->users  = $users;
            $this->guests = $guests;
            $this->bots   = $bots;
        }

        return $this;
    }

    /**
     * Обновление данных текущего посетителя
     *
     * @param string $position
     */
    protected function updateUser(string $position): void
    {
        // гость
        if ($this->c->user->isGuest) {
            $vars = [
                ':logged' => \time(),
                ':pos'    => $position,
                ':name'   => (string) $this->c->user->isBot,
                ':ip'     => $this->c->user->ip
            ];
            if ($this->c->user->isLogged) {
                $this->c->DB->exec('UPDATE ::online SET logged=?i:logged, o_position=?s:pos, o_name=?s:name WHERE user_id=1 AND ident=?s:ip', $vars);
            } else {
                $this->c->DB->exec('INSERT INTO ::online (user_id, ident, logged, o_position, o_name) SELECT 1, ?s:ip, ?i:logged, ?s:pos, ?s:name FROM ::groups WHERE NOT EXISTS (SELECT 1 FROM ::online WHERE user_id=1 AND ident=?s:ip) LIMIT 1', $vars);
            }
        } else {
        // пользователь
            $vars = [
                ':logged' => \time(),
                ':pos'    => $position,
                ':id'     => $this->c->user->id,
                ':name'   => $this->c->user->username,
            ];
            if ($this->c->user->isLogged) {
                $this->c->DB->exec('UPDATE ::online SET logged=?i:logged, o_position=?s:pos WHERE user_id=?i:id', $vars);
            } else {
                $this->c->DB->exec('INSERT INTO ::online (user_id, ident, logged, o_position) SELECT ?i:id, ?s:name, ?i:logged, ?s:pos FROM ::groups WHERE NOT EXISTS (SELECT 1 FROM ::online WHERE user_id=?i:id) LIMIT 1', $vars);
            }
        }
    }

    /**
     * Удаление юзера из таблицы online
     *
     * @param User $user
     */
    public function delete(User $user): void
    {
        if ($user->isGuest) {
            $this->c->DB->exec('DELETE FROM ::online WHERE user_id=1 AND ident=?s:ip', [':ip' => $user->ip]);
        } else {
            $this->c->DB->exec('DELETE FROM ::online WHERE user_id=?i:id', [':id' => $user->id]);
        }
    }
}
