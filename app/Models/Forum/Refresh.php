<?php

namespace ForkBB\Models\Forum;

use ForkBB\Models\Action;
use ForkBB\Models\Group\Model as Group;

class Refresh extends Action
{
    /**
     * @var array
     */
    protected $list = [];

    /**
     * Возвращает список доступных разделов для группы
     * Обновляет кеш
     */
    public function refresh(Group $group = null): array
    {
        if (null === $group) {
            $gid  = $this->c->user->group_id;
            $read = $this->c->user->g_read_board;
        } else {
            $gid  = $group->g_id;
            $read = $group->g_read_board;
        }

        if ('1' == $read) {
            $list  = [];
            $vars  = [
                ':gid' => $gid,
            ];
            $query = 'SELECT f.cat_id, c.cat_name, f.id, f.forum_name, f.redirect_url, f.parent_forum_id,
                    f.moderators, f.no_sum_mess, f.disp_position, fp.post_topics, fp.post_replies
                FROM ::categories AS c
                INNER JOIN ::forums AS f ON c.id=f.cat_id
                LEFT JOIN ::forum_perms AS fp ON (fp.group_id=?i:gid AND fp.forum_id=f.id)
                WHERE fp.read_forum IS NULL OR fp.read_forum=1
                ORDER BY c.disp_position, c.id, f.disp_position';

            $stmt = $this->c->DB->query($query, $vars);
            while ($row = $stmt->fetch()) {
                $row['moderators'] = $this->formatModers($row['moderators']);
                $list[$row['id']]  = $row;
            }

            if (! empty($list)) {
                $this->createList($list);
            }
        }

        $this->c->Cache->set('forums_' . $gid, [
            'time' => \time(),
            'list' => $this->list,
        ]);

        return $this->list;
    }

    /**
     * Преобразует строку со списком модераторов в массив
     */
    protected function formatModers(string $str): ?array
    {
        $moderators = \json_decode($str, true);

        return $moderators ?: null;
    }

    /**
     * Формирует список доступных разделов
     */
    protected function createList(array $list, int $parent = 0): array
    {
        $sub = [];
        $all = [];
        foreach ($list as $id => $f) {
            if (
                $parent === $id
                || $parent !== $f['parent_forum_id']
            ) {
                continue;
            }
            $sub[] = $id;
            $all   = \array_merge($this->createList($list, $id), $all);
        }
        if (0 === $parent) {
            if (empty($sub)) {
                return [];
            }
            $list[0]['id']    = $parent;
            $list[0]['ready'] = true;
        }
        $all = \array_merge($sub, $all);
        $list[$parent]['subforums']   = $sub ?: null;
        $list[$parent]['descendants'] = $all ?: null;

        $this->list[$parent] = \array_filter($list[$parent], function($val) {
            return null !== $val;
        });

        return $all;
    }
}
