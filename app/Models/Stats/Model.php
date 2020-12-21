<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Stats;

use ForkBB\Models\Model as ParentModel;
use PDO;
use RuntimeException;

class Model extends ParentModel
{
    /**
     * Загружает статистику из кеша/БД
     */
    public function init(): Model
    {
        $list = $this->c->Cache->get('stats');

        if (! \is_array($list)) {
            $list = $this->c->users->stats();

            if (true !== $this->c->Cache->set('stats', $list)) {
                throw new RuntimeException('Unable to write value to cache - stats');
            }
        }

        $this->userTotal = $list['total'];
        $this->userLast  = $list['last'];

        $query = 'SELECT SUM(f.num_topics), SUM(f.num_posts)
            FROM ::forums AS f';

        list($this->topicTotal, $this->postTotal) = $this->c->DB->query($query)->fetch(PDO::FETCH_NUM);

        return $this;
    }

    /**
     * Сбрасывает кеш статистики
     */
    public function reset(): Model
    {
        if (true !== $this->c->Cache->delete('stats')) {
            throw new RuntimeException('Unable to remove key from cache - stats');
        }

        return $this;
    }
}
