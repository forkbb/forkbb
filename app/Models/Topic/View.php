<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Search\Model as Search;
use ForkBB\Models\Topic\Model as Topic;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class View extends Action
{
    /**
     * Возвращает список тем
     *
     * @param mixed $arg
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     *
     * @return array
     */
    public function view($arg)
    {
        if ($arg instanceof Forum) {
            $expanded = false;
        } elseif ($arg instanceof Search) {
            $expanded = true;
        } else {
            throw new InvalidArgumentException('Expected Forum or Search');
        }

        if (empty($arg->idsList) || ! is_array($arg->idsList)) {
            throw new RuntimeException('Model does not contain of topics list for display');
        }

        $vars = [
            ':uid' => $this->c->user->id,
            ':ids' => $arg->idsList,
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

        $result = array_flip($arg->idsList);
        while ($row = $stmt->fetch()) {
            $row['dot'] = isset($dots[$row['id']]);
            $result[$row['id']] = $this->manager->create($row);
            if ($expanded && ! $this->c->user->isGuest) {
                $result[$row['id']]->parent->__mf_mark_all_read = $row['mf_mark_all_read'];
            }
        }

        return $result;
    }
}
