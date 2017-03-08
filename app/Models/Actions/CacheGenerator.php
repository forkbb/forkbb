<?php

namespace ForkBB\Models\Actions;

use ForkBB\Core\Container;
use ForkBB\Models\User;

class CacheGenerator
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Конструктор
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    /**
     * Возвращает массив конфигурации форума
     * @return array
     */
    public function config()
    {
        return $this->c->DB->query('SELECT conf_name, conf_value FROM ::config')->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /**
     * Возвращает массив банов
     * @return array
     */
    public function bans()
    {
        return $this->c->DB->query('SELECT id, username, ip, email, message, expire FROM ::bans')->fetchAll();
    }

    /**
     * Возвращает массив слов попадающий под цензуру
     * @return array
     */
    public function censoring()
    {
        $stmt = $this->c->DB->query('SELECT search_for, replace_with FROM ::censoring');
        $search = $replace = [];
        while ($row = $stmt->fetch()) {
            $replace[] = $row['replace_with'];
            $search[] = '%(?<![\p{L}\p{N}])('.str_replace('\*', '[\p{L}\p{N}]*?', preg_quote($row['search_for'], '%')).')(?![\p{L}\p{N}])%iu';
        }
        return [$search, $replace];
    }

    /**
     * Возвращает информацию о последнем зарегистрированном пользователе и
     * общем числе пользователей
     * @return array
     */
    public function usersInfo()
    {
        $stats = [];
        $stats['total_users'] = $this->c->DB->query('SELECT COUNT(id)-1 FROM ::users WHERE group_id!='.PUN_UNVERIFIED)->fetchColumn();
        $stats['last_user'] = $this->c->DB->query('SELECT id, username FROM ::users WHERE group_id!='.PUN_UNVERIFIED.' ORDER BY registered DESC LIMIT 1')->fetch();
        return $stats;
    }

    /**
     * Возвращает спимок id админов
     * @return array
     */
    public function admins()
    {
        return $this->c->DB->query('SELECT id FROM ::users WHERE group_id='.PUN_ADMIN)->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Возвращает массив с описанием смайлов
     * @return array
     */
    public function smilies()
    {
        return $this->c->DB->query('SELECT text, image FROM ::smilies ORDER BY disp_position')->fetchAll(\PDO::FETCH_KEY_PAIR); //???? text уникальное?
    }

    /**
     * Возвращает массив с описанием форумов для текущего пользователя
     * @return array
     */
    public function forums(User $user)
    {
        $stmt = $this->c->DB->query('SELECT g_read_board FROM ::groups WHERE g_id=?i:id', [':id' => $user->gId]);
        $read = $stmt->fetchColumn();
        $stmt->closeCursor();

        $tree = $desc = $asc = [];

        if ($read) {
            $stmt = $this->c->DB->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.redirect_url, f.parent_forum_id, f.disp_position FROM ::categories AS c INNER JOIN ::forums AS f ON c.id=f.cat_id LEFT JOIN ::forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id=?i:id) WHERE fp.read_forum IS NULL OR fp.read_forum=1 ORDER BY c.disp_position, c.id, f.disp_position', [':id' => $user->gId]);
            while ($f = $stmt->fetch()) {
                $tree[$f['parent_forum_id']][$f['fid']] = $f;
            }
            $this->forumsDesc($desc, $tree);
            $this->forumsAsc($asc, $tree);
        }
        return [$tree, $desc, $asc];
    }

    protected function forumsDesc(&$list, $tree, $node = 0)
    {
        if (empty($tree[$node])) {
            return;
        }
        foreach ($tree[$node] as $id => $forum) {
            $list[$id] = $node ? array_merge([$node], $list[$node]) : []; //????
            $list[$id]['forum_name'] = $forum['forum_name'];
            $this->forumsDesc($list, $tree, $id);
        }
    }

    protected function forumsAsc(&$list, $tree, $nodes = [0])
    {
        $list[$nodes[0]][] = $nodes[0];

        if (empty($tree[$nodes[0]])) {
            return;
        }
        foreach ($tree[$nodes[0]] as $id => $forum) {
            $temp = [$id];
            foreach ($nodes as $i) {
                $list[$i][] = $id;
                $temp[] = $i;
            }
            $this->forumsAsc($list, $tree, $temp);
        }
    }
}
