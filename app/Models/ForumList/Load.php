<?php

namespace ForkBB\Models\ForumList;

use ForkBB\Models\MethodModel;

class Load extends MethodModel
{
    /**
     * @var array
     */
    protected $list = [];

    /**
     * Заполняет модель данными из БД для текущего пользователя
     * Создает кеш
     *
     * @return ForumList
     */
    public function load()
    {
        $this->getList();
        $this->model->list = $this->list; //????
        $this->c->Cache->set('forums_' . $this->c->user->group_id, [
            'time' => time(),
            'list' => $this->list,
        ]);
        return $this->model;
    }

    /**
     * Получает данные из БД
     */
    protected function getList()
    {
        if ($this->c->user->g_read_board != '1') {
            return;
        }
        $list = [];
        $vars = [':gid' => $this->c->user->group_id];
        $sql = 'SELECT c.id AS cid, c.cat_name, f.id, f.forum_name, f.redirect_url, f.parent_forum_id,
                       f.disp_position, fp.post_topics, fp.post_replies
                FROM ::categories AS c
                INNER JOIN ::forums AS f ON c.id=f.cat_id
                LEFT JOIN ::forum_perms AS fp ON (fp.group_id=?i:gid AND fp.forum_id=f.id)
                WHERE fp.read_forum IS NULL OR fp.read_forum=1
                ORDER BY c.disp_position, c.id, f.disp_position';
        $stmt = $this->c->DB->query($sql, $vars);
        while ($row = $stmt->fetch()) {
            $list[$row['id']] = $row;
        }
        if (empty($list)) {
            return;
        }
        $this->createList($list);
    }

    /**
     * Формирует список доступных разделов
     *
     * @param array $list
     * @param int $parent
     *
     * @return array
     */
    protected function createList(array $list, $parent = 0)
    {
        $sub = [];
        $all = [];
        foreach ($list as $id => $f) {
            if ($parent === $id || $parent !== $f['parent_forum_id']) {
                continue;
            }
            $sub[] = $id;
            $all   = array_merge($this->createList($list, $id), $all);
        }
        if ($parent === 0) {
            if (empty($sub)) {
                return [];
            }
            $list[0]['id']    = $parent;
            $list[0]['ready'] = true;
        }
        $all = array_merge($sub, $all);
        $list[$parent]['subforums']   = $sub ?: null;
        $list[$parent]['descendants'] = $all ?: null;
        
        $this->list[$parent] = array_filter($list[$parent], function($val) {
            return $val !== null;
        });
        return $all;
    }
}
