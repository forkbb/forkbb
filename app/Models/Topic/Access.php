<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\Topic\Model as Topic;

class Access extends Action
{
    /**
     * Устанавливает/снимает флаг закрытия тем(ы)
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
            $query = 'UPDATE ::topics
                SET closed=?i:closed
                WHERE id IN (?ai:ids)';

            $this->c->DB->exec($query, $vars);
        }
    }
}
