<?php

namespace ForkBB\Models;

use ForkBB\Core\Container;
use ForkBB\Models\Model;
use ForkBB\Models\User;
use ForkBB\Models\Page;

class Online extends Model
{
    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->users  = [];
        $this->guests = [];
        $this->bots   = [];
    }

    /**
     * Обработка данных пользователей онлайн
     * Обновление данных текущего пользователя
     * Возврат данных по пользователям онлайн
     *
     * @param Page $page
     */
    public function calc(Page $page)
    {
        if ($this->done) {
            return;
        }
        $this->done = true;

        $position = $page->onlinePos;
        if (null === $position) {
            return;
        }
        $type     = $page->onlineType;
        $filter   = $page->onlineFilter;

        $this->updateUser($position);

        $all     = 0;
        $now     = time();
        $tOnline = $now - $this->c->config->o_timeout_online;
        $tVisit  = $now - $this->c->config->o_timeout_visit;
        $users   = [];
        $guests  = [];
        $bots    = [];
        $deleteG = false;
        $deleteU = false;
        $setIdle = false;

        if ($this->c->config->o_users_online == '1' && $type) {
            $stmt = $this->c->DB->query('SELECT user_id, ident, logged, idle, o_position, o_name FROM ::online ORDER BY logged');
        } elseif ($type) {
            $stmt = $this->c->DB->query('SELECT user_id, ident, logged, idle FROM ::online ORDER BY logged');
        } else {
            $stmt = $this->c->DB->query('SELECT user_id, ident, logged, idle FROM ::online WHERE logged<?i:online', [':online' => $tOnline]);
        }
        while ($cur = $stmt->fetch()) {

            // посетитель уже не онлайн (или почти не онлайн)
            if ($cur['logged'] < $tOnline) {
                // пользователь
                if ($cur['user_id'] > 1) {
                    if ($cur['logged'] < $tVisit) {
                        $deleteU = true;
                        $this->c->DB->exec('UPDATE ::users SET last_visit=?i:last WHERE id=?i:id', [':last' => $cur['logged'], ':id' => $cur['user_id']]);
                    } elseif ($cur['idle'] == '0') {
                        $setIdle = true;
                    }
                // гость
                } else {
                    $deleteG = true;
                }

            // обработка посетителя для вывода статистики
            } elseif ($type) {
                ++$all;

                // включен фильтр и проверка не пройдена
                if ($filter && $cur['o_position'] !== $position) {
                    continue;
                }

                // пользователь
                if ($cur['user_id'] > 1) {
                    $users[$cur['user_id']] = [
                        'name' => $cur['ident'],
                        'logged' => $cur['logged'],
                    ];
                // гость
                } elseif ($cur['o_name'] == '') {
                    $guests[] = [
                        'name' => $cur['ident'],
                        'logged' => $cur['logged'],
                    ];
                // бот
                } else {
                    $bots[$cur['o_name']][] = [
                        'name' => $cur['ident'],
                        'logged' => $cur['logged'],
                    ];
                }

            // просто +1 к общему числу посетителей
            } else {
                ++$all;
            }
        }

        // удаление просроченных пользователей
        if ($deleteU) {
            $this->c->DB->exec('DELETE FROM ::online WHERE logged<?i:visit', [':visit' => $tVisit]);
        }

        // удаление просроченных гостей
        if ($deleteG) {
            $this->c->DB->exec('DELETE FROM ::online WHERE user_id=1 AND logged<?i:online', [':online' => $tOnline]);
        }

        // обновление idle
        if ($setIdle) {
            $this->c->DB->exec('UPDATE ::online SET idle=1 WHERE logged<?i:online', [':online' => $tOnline]);
        }

        // обновление максимального значение пользоватеелй онлайн
        if ($this->c->config->st_max_users < $all) {
            $this->c->config->st_max_users      = $all;
            $this->c->config->st_max_users_time = $now;
            $this->c->config->save();
        }
        $this->users  = $users;
        $this->guests = $guests;
        $this->bots   = $bots;
    }

    /**
     * Обновление данных текущего пользователя
     *
     * @param string $position
     */
    protected function updateUser($position)
    {
        $now = time();
        // гость
        if ($this->c->user->isGuest) {
            $vars = [
                ':logged' => time(),
                ':pos' => $position,
                ':name' => (string) $this->c->user->isBot,
                ':ip' => $this->c->user->ip
            ];
            if ($this->c->user->isLogged) {
                $this->c->DB->exec('UPDATE ::online SET logged=?i:logged, o_position=?s:pos, o_name=?s:name WHERE user_id=1 AND ident=?s:ip', $vars);
            } else {
                $this->c->DB->exec('INSERT INTO ::online (user_id, ident, logged, o_position, o_name) SELECT 1, ?s:ip, ?i:logged, ?s:pos, ?s:name FROM ::groups WHERE NOT EXISTS (SELECT 1 FROM ::online WHERE user_id=1 AND ident=?s:ip) LIMIT 1', $vars);
            }
        } else {
        // пользователь
            $vars = [
                ':logged' => time(),
                ':pos' => $position,
                ':id' => $this->c->user->id,
                ':name' => $this->c->user->username,
            ];
            if ($this->c->user->isLogged) {
                $this->c->DB->exec('UPDATE ::online SET logged=?i:logged, idle=0, o_position=?s:pos WHERE user_id=?i:id', $vars);
            } else {
                $this->c->DB->exec('INSERT INTO ::online (user_id, ident, logged, o_position) SELECT ?i:id, ?s:name, ?i:logged, ?s:pos FROM ::groups WHERE NOT EXISTS (SELECT 1 FROM ::online WHERE user_id=?i:id) LIMIT 1', $vars);
            }
        }
    }

    /**
     * Удаление юзера из таблицы online
     */
    public function delete(User $user)
    {
        if ($user->isGuest) {
            $this->c->DB->exec('DELETE FROM ::online WHERE user_id=1 AND ident=?s:ip', [':ip' => $user->ip]);
        } else {
            $this->c->DB->exec('DELETE FROM ::online WHERE user_id=?i:id', [':id' => $user->id]);
        }
    }
}
