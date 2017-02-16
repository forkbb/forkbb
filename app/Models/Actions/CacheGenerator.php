<?php

namespace ForkBB\Models\Actions;

//use ForkBB\Core\DB;
use ForkBB\Models\User;

class CacheGenerator
{
    /**
     * @var ForkBB\Core\DB
     */
    protected $db;

    /**
     * Конструктор
     * @param ForkBB\Core\DB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Возвращает массив конфигурации форума
     * @return array
     */
    public function config()
    {
        // Get the forum config from the DB
        $result = $this->db->query('SELECT * FROM '.$this->db->prefix.'config', true) or error('Unable to fetch forum config', __FILE__, __LINE__, $this->db->error());
        $arr = [];
        while ($cur = $this->db->fetch_row($result)) {
            $arr[$cur[0]] = $cur[1];
        }
        $this->db->free_result($result);
        return $arr;
    }

    /**
     * Возвращает массив банов
     * @return array
     */
    public function bans()
    {
        // Get the ban list from the DB
        $result = $this->db->query('SELECT * FROM '.$this->db->prefix.'bans', true) or error('Unable to fetch ban list', __FILE__, __LINE__, $this->db->error());
        $arr = [];
        while ($cur = $this->db->fetch_assoc($result)) {
            $arr[] = $cur;
        }
        $this->db->free_result($result);
        return $arr;
    }

    /**
     * Возвращает массив слов попадающий под цензуру
     * @return array
     */
    public function censoring()
    {
        $result = $this->db->query('SELECT search_for, replace_with FROM '.$this->db->prefix.'censoring') or error('Unable to fetch censoring list', __FILE__, __LINE__, $this->db->error());
        $num_words = $this->db->num_rows($result);

        $search_for = $replace_with = [];
        for ($i = 0; $i < $num_words; $i++) {
            list($search_for[$i], $replace_with[$i]) = $this->db->fetch_row($result);
            $search_for[$i] = '%(?<=[^\p{L}\p{N}])('.str_replace('\*', '[\p{L}\p{N}]*?', preg_quote($search_for[$i], '%')).')(?=[^\p{L}\p{N}])%iu';
        }
        $this->db->free_result($result);

        return [
            'search_for' => $search_for,
            'replace_with' => $replace_with
        ];
    }

    /**
     * Возвращает информацию о последнем зарегистрированном пользователе и
     * общем числе пользователей
     * @return array
     */
    public function usersInfo()
    {
        $stats = [];

        $result = $this->db->query('SELECT COUNT(id)-1 FROM '.$this->db->prefix.'users WHERE group_id!='.PUN_UNVERIFIED) or error('Unable to fetch total user count', __FILE__, __LINE__, $this->db->error());
        $stats['total_users'] = $this->db->result($result);

        $result = $this->db->query('SELECT id, username FROM '.$this->db->prefix.'users WHERE group_id!='.PUN_UNVERIFIED.' ORDER BY registered DESC LIMIT 1') or error('Unable to fetch newest registered user', __FILE__, __LINE__, $this->db->error());
        $stats['last_user'] = $this->db->fetch_assoc($result);

        return $stats;
    }

    /**
     * Возвращает спимок id админов
     * @return array
     */
    public function admins()
    {
        // Get admins from the DB
        $result = $this->db->query('SELECT id FROM '.$this->db->prefix.'users WHERE group_id='.PUN_ADMIN) or error('Unable to fetch users info', __FILE__, __LINE__, $this->db->error());
        $arr = [];
        while ($row = $this->db->fetch_row($result)) {
            $arr[] = $row[0];
        }
        $this->db->free_result($result);

        return $arr;
    }

    /**
     * Возвращает массив с описанием смайлов
     * @return array
     */
    public function smilies()
    {
        $arr = [];
        $result = $this->db->query('SELECT text, image FROM '.$this->db->prefix.'smilies ORDER BY disp_position') or error('Unable to retrieve smilies', __FILE__, __LINE__, $this->db->error());
        while ($cur = $this->db->fetch_assoc($result)) {
            $arr[$cur['text']] = $cur['image'];
        }
        $this->db->free_result($result);

        return $arr;
    }

    /**
     * Возвращает массив с описанием форумов для текущего пользователя
     * @return array
     */
    public function forums(User $user)
    {
        $groupId = $user->gId;
		$result = $this->db->query('SELECT g_read_board FROM '.$this->db->prefix.'groups WHERE g_id='.$groupId) or error('Unable to fetch user group read permission', __FILE__, __LINE__, $this->db->error());
		$read = $this->db->result($result);

        $tree = $desc = $asc = [];

        if ($read) {
            $result = $this->db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.redirect_url, f.parent_forum_id, f.disp_position FROM '.$this->db->prefix.'categories AS c INNER JOIN '.$this->db->prefix.'forums AS f ON c.id=f.cat_id LEFT JOIN '.$this->db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$groupId.') WHERE fp.read_forum IS NULL OR fp.read_forum=1 ORDER BY c.disp_position, c.id, f.disp_position', true) or error('Unable to fetch category/forum list', __FILE__, __LINE__, $this->db->error());

            while ($f = $this->db->fetch_assoc($result)) {
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
