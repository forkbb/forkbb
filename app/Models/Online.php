<?php

namespace ForkBB\Models;

use ForkBB\Models\User;
use ForkBB\Models\Pages\Page;
use R2\DependencyInjection\ContainerInterface;
use RuntimeException;

class Online
{
    /**
     * Контейнер
     * @var ContainerInterface
     */
    protected $c;

    /**
     * Флаг выполнения
     * @var bool
     */
    protected $done = false;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var DB
     */
    protected $db;

    /**
     * @var User
     */
    protected $user;

    /**
     * Конструктор
     * @param array $config
     * @param DB $db
     * @param User $user
     * @param ContainerInterface $container
     */
    public function __construct(array $config, $db, User $user, ContainerInterface $container)
    {
        $this->config = $config;
        $this->db = $db;
        $this->user = $user;
        $this->c = $container;
    }

    /**
     * Обработка данных пользователей онлайн
     * Обновление данных текущего пользователя
     * Возврат данных по пользователям онлайн
     * @param Page $page
     * @return array
     */
    public function handle(Page $page)
    {
        if ($this->done) {
            return [[], [], []]; //????
        }
        $this->done = true;

        //  string|null  bool   bool
        list($position, $type, $filter) = $page->getDataForOnline();  //???? возможно стоит возвращать полное имя страницы для отображение
        if (null === $position) {
            return [[], [], []]; //????
        }

        $this->updateUser($position);

        $all = 0;
        $now = time();
        $tOnline = $now - $this->config['o_timeout_online'];
        $tVisit = $now - $this->config['o_timeout_visit'];
        $users = $guests = $bots = [];
        $deleteG = false;
        $deleteU = false;
        $setIdle = false;

        if ($this->config['o_users_online'] == '1' && $type) {
            $result = $this->db->query('SELECT user_id, ident, logged, idle, o_position, o_name FROM '.$this->db->prefix.'online ORDER BY logged') or error('Unable to fetch users from online list', __FILE__, __LINE__, $this->db->error());
        } elseif ($type) {
            $result = $this->db->query('SELECT user_id, ident, logged, idle FROM '.$this->db->prefix.'online ORDER BY logged') or error('Unable to fetch users from online list', __FILE__, __LINE__, $this->db->error());
        } else {
            $result = $this->db->query('SELECT user_id, ident, logged, idle FROM '.$this->db->prefix.'online WHERE logged<'.$tOnline) or error('Unable to fetch users from online list', __FILE__, __LINE__, $this->db->error());
        }
        while ($cur = $this->db->fetch_assoc($result)) {

            // посетитель уже не онлайн (или почти не онлайн)
            if ($cur['logged'] < $tOnline) {
                // пользователь
                if ($cur['user_id'] > 1) {
                    if ($cur['logged'] < $tVisit) {
                        $deleteU = true;
                        $this->db->query('UPDATE '.$this->db->prefix.'users SET last_visit='.$cur['logged'].' WHERE id='.$cur['user_id']) or error('Unable to update user visit data', __FILE__, __LINE__, $this->db->error());
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
        $this->db->free_result($result);

        // удаление просроченных пользователей
        if ($deleteU) {
            $this->db->query('DELETE FROM '.$this->db->prefix.'online WHERE logged<'.$tVisit) or error('Unable to delete from online list', __FILE__, __LINE__, $this->db->error());
        }

        // удаление просроченных гостей
        if ($deleteG) {
            $this->db->query('DELETE FROM '.$this->db->prefix.'online WHERE user_id=1 AND logged<'.$tOnline) or error('Unable to delete from online list', __FILE__, __LINE__, $this->db->error());
        }

        // обновление idle
        if ($setIdle) {
            $this->db->query('UPDATE '.$this->db->prefix.'online SET idle=1 WHERE logged<'.$tOnline) or error('Unable to update into online list', __FILE__, __LINE__, $this->db->error());
        }

        // обновление максимального значение пользоватеелй онлайн
        if ($this->config['st_max_users'] < $all) {
            $this->db->query('UPDATE '.$this->db->prefix.'config SET conf_value=\''.$all.'\' WHERE conf_name=\'st_max_users\'') or error('Unable to update config value \'st_max_users\'', __FILE__, __LINE__, $this->db->error());
            $this->db->query('UPDATE '.$this->db->prefix.'config SET conf_value=\''.$now.'\' WHERE conf_name=\'st_max_users_time\'') or error('Unable to update config value \'st_max_users_time\'', __FILE__, __LINE__, $this->db->error());

            $this->c->get('config update');
        }
/*
@set_time_limit(0);
for ($i=0;$i<100;++$i) {
    $this->db->query('REPLACE INTO '.$this->db->prefix.'online (user_id, ident, logged, o_position, o_name) VALUES(1, \''.$this->db->escape($i).'\', '.time().', \''.$this->db->escape($position).'\', \'Super Puper '.$this->db->escape($i).'\')') or error('Unable to insert into online list', __FILE__, __LINE__, $this->db->error());
}
*/
        return [$users, $guests, $bots];
    }

    /**
     * Обновление данных текущего пользователя
     * @param string $position
     */
    protected function updateUser($position)
    {
        $now = time();
        // гость
        if ($this->user->isGuest) {
            $oname = (string) $this->user->isBot;

            if ($this->user->isLogged) {
                $this->db->query('UPDATE '.$this->db->prefix.'online SET logged='.$now.', o_position=\''.$this->db->escape($position).'\', o_name=\''.$this->db->escape($oname).'\' WHERE user_id=1 AND ident=\''.$this->db->escape($this->user->ip).'\'') or error('Unable to update online list', __FILE__, __LINE__, $this->db->error());
            } else {
                $this->db->query('INSERT INTO '.$this->db->prefix.'online (user_id, ident, logged, o_position, o_name) SELECT 1, \''.$this->db->escape($this->user->ip).'\', '.$now.', \''.$this->db->escape($position).'\', \''.$this->db->escape($oname).'\' FROM '.$this->db->prefix.'groups WHERE NOT EXISTS (SELECT 1 FROM '.$this->db->prefix.'online WHERE user_id=1 AND ident=\''.$this->db->escape($this->user->ip).'\') LIMIT 1') or error('Unable to insert into online list', __FILE__, __LINE__, $this->db->error());

                // With MySQL/MySQLi/SQLite, REPLACE INTO avoids a user having two rows in the online table
/*                switch ($this->c->getParameter('DB_TYPE')) {
                    case 'mysql':
                    case 'mysqli':
                    case 'mysql_innodb':
                    case 'mysqli_innodb':
                    case 'sqlite':
                        $this->db->query('REPLACE INTO '.$this->db->prefix.'online (user_id, ident, logged, o_position, o_name) VALUES(1, \''.$this->db->escape($this->user->ip).'\', '.$now.', \''.$this->db->escape($position).'\', \''.$this->db->escape($oname).'\')') or error('Unable to insert into online list', __FILE__, __LINE__, $this->db->error());
                        break;

                    default:
                        $this->db->query('INSERT INTO '.$this->db->prefix.'online (user_id, ident, logged, o_position, o_name) SELECT 1, \''.$this->db->escape($this->user->ip).'\', '.$now.', \''.$this->db->escape($position).'\', \''.$this->db->escape($oname).'\' WHERE NOT EXISTS (SELECT 1 FROM '.$this->db->prefix.'online WHERE user_id=1 AND ident=\''.$this->db->escape($this->user->ip).'\')') or error('Unable to insert into online list', __FILE__, __LINE__, $this->db->error());
                        break;
                }
*/
            }
        } else {
        // пользователь
            if ($this->user->isLogged) {
                $idle_sql = ($this->user->idle == '1') ? ', idle=0' : '';
                $this->db->query('UPDATE '.$this->db->prefix.'online SET logged='.$now.$idle_sql.', o_position=\''.$this->db->escape($position).'\' WHERE user_id='.$this->user->id) or error('Unable to update online list', __FILE__, __LINE__, $this->db->error());
            } else {
                $this->db->query('INSERT INTO '.$this->db->prefix.'online (user_id, ident, logged, o_position) SELECT '.$this->user->id.', \''.$this->db->escape($this->user->username).'\', '.$now.', \''.$this->db->escape($position).'\' FROM '.$this->db->prefix.'groups WHERE NOT EXISTS (SELECT 1 FROM '.$this->db->prefix.'online WHERE user_id='.$this->user->id.') LIMIT 1') or error('Unable to insert into online list', __FILE__, __LINE__, $this->db->error());
                // With MySQL/MySQLi/SQLite, REPLACE INTO avoids a user having two rows in the online table
/*                switch ($this->c->getParameter('DB_TYPE')) {
                    case 'mysql':
                    case 'mysqli':
                    case 'mysql_innodb':
                    case 'mysqli_innodb':
                    case 'sqlite':
                        $this->db->query('REPLACE INTO '.$this->db->prefix.'online (user_id, ident, logged, o_position) VALUES('.$this->user->id.', \''.$this->db->escape($this->user->username).'\', '.$now.', \''.$this->db->escape($position).'\')') or error('Unable to insert into online list', __FILE__, __LINE__, $this->db->error());
                        break;

                    default:
                        $this->db->query('INSERT INTO '.$this->db->prefix.'online (user_id, ident, logged, o_position) SELECT '.$this->user->id.', \''.$this->db->escape($this->user->username).'\', '.$now.', \''.$this->db->escape($position).'\' WHERE NOT EXISTS (SELECT 1 FROM '.$this->db->prefix.'online WHERE user_id='.$this->user->id.')') or error('Unable to insert into online list', __FILE__, __LINE__, $this->db->error());
                        break;
                }
*/
            }
        }
    }

    /**
     * Удаление юзера из таблицы online
     */
    public function delete(User $user)
    {
        if ($user->isGuest) {
            $this->db->query('DELETE FROM '.$this->db->prefix.'online WHERE user_id=1 AND ident=\''.$this->db->escape($user->ip).'\'') or error('Unable to delete from online list', __FILE__, __LINE__, $this->db->error());
        } else {
            $this->db->query('DELETE FROM '.$this->db->prefix.'online WHERE user_id='.$user->id) or error('Unable to delete from online list', __FILE__, __LINE__, $this->db->error());
        }
    }
}
