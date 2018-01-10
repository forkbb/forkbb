<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\Topic\Model as Topic;
use PDO;

class View extends Action
{
    /**
     * Возвращает список тем
     *
     * @param array $list
     * @param bool $expanded
     *
     * @return array
     */
    public function view(array $list, $expanded = false)
    {
        if (empty($list)) {
            return [];
        }

        $vars = [
            ':uid' => $this->c->user->id,
            ':ids' => $list,
        ];

        if (! $this->c->user->isGuest && '1' == $this->c->config->o_show_dot) {
            $sql = 'SELECT topic_id 
                    FROM ::posts 
                    WHERE poster_id=?i:uid AND topic_id IN (?ai:ids) 
                    GROUP BY topic_id';
            $dots = $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_COLUMN);
            $dots = array_flip($dots);
        } else {
            $dots = [];
        }

        if ($this->c->user->isGuest) {
            $sql = 'SELECT t.*
                    FROM ::topics AS t
                    WHERE t.id IN(?ai:ids)';
        } elseif ($expanded) {
            $sql = 'SELECT t.*, mof.mf_mark_all_read, mot.mt_last_visit, mot.mt_last_read
                    FROM ::topics AS t
                    LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:uid AND t.forum_id=mof.fid)
                    LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid)
                    WHERE t.id IN (?ai:ids)';
        } else {
            $sql = 'SELECT t.*, mot.mt_last_visit, mot.mt_last_read
                    FROM ::topics AS t
                    LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid)
                    WHERE t.id IN (?ai:ids)';
        }
        $stmt = $this->c->DB->query($sql, $vars);

        $result = array_flip($list);
        while ($row = $stmt->fetch()) {
            $row['dot'] = isset($dots[$row['id']]);
            $result[$row['id']] = $this->manager->create($row);
            if ($expanded) {
                $result[$row['id']]->parent->__mf_mark_all_read = $row['mf_mark_all_read'];
            }
        }

        return $result;
    }
}
