<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\Topic\Model as Topic;

class Access extends Action
{
    /**
     * Устанавливает/снимает флаг закрытия тем(ы)
     *
     * @param bool $open
     * @param Topic ...$topics
     */
    public function access(bool $open, Topic ...$topics): void
    {
        $ids = [];
        foreach ($topics as $topic) {
            $ids[]           = $topic->id;
            $topic->__closed = $open ? 0 : 1;
        }

        if (! empty($ids)) {
            $vars = [
                ':ids'    => $ids,
                ':closed' => $open ? 0 : 1,
            ];
            $sql = 'UPDATE ::topics SET closed=?i:closed WHERE id IN (?ai:ids)';
            $this->c->DB->exec($sql, $vars);
        }
    }
}
