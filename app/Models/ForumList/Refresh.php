<?php

namespace ForkBB\Models\ForumList;

use ForkBB\Models\MethodModel;

class Refresh extends MethodModel
{
    /**
     * @var array
     */
    protected $list = [];

    /**
     * Возвращает список доступных разделов для группы
     * Обновляет кеш
     *
     * @param int $gid
     * 
     * @return array
     */
    public function refresh($gid)
    {
        $vars = [
            ':gid' => $gid,
        ];
        
        if ($this->c->user->group_id === $gid) {
            $read = $this->c->user->g_read_board;
        } else {
            $sql = 'SELECT g_read_board FROM ::groups WHERE g_id=?i:gid';
            $read = $this->c->DB->query($sql, $vars)->fetchColumn();
        }

        if ($read == '1') {
            $list = [];
            $sql  = 'SELECT f.cat_id, c.cat_name, f.id, f.forum_name, f.redirect_url, f.parent_forum_id,
                            f.moderators, f.no_sum_mess, f.disp_position, fp.post_topics, fp.post_replies
                     FROM ::categories AS c
                     INNER JOIN ::forums AS f ON c.id=f.cat_id
                     LEFT JOIN ::forum_perms AS fp ON (fp.group_id=?i:gid AND fp.forum_id=f.id)
                     WHERE fp.read_forum IS NULL OR fp.read_forum=1
                     ORDER BY c.disp_position, c.id, f.disp_position';

            $stmt = $this->c->DB->query($sql, $vars);
            while ($row = $stmt->fetch()) {
                $row['moderators'] = $this->formatModers($row['moderators']);
                $list[$row['id']] = $row;
            }
            
            if (! empty($list)) {
                $this->createList($list);
            }
        }

        $this->c->Cache->set('forums_' . $gid, [
            'time' => time(),
            'list' => $this->list,
        ]);
        return $this->list;
    }

    /**
     * Преобразует строку со списком модераторов в массив
     * 
     * @param string $str
     * 
     * @return null|array
     */
    protected function formatModers($str)
    {
        return empty($str) ? null : array_flip(unserialize($str));
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
