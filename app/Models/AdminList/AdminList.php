<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\AdminList;

use ForkBB\Models\Model;
use RuntimeException;

class AdminList extends Model
{
    /**
     * Ключ модели для контейнера
     * @var string
     */
    protected $cKey = 'AdminList';

    /**
     * Загружает список id админов из кеша/БД
     * Создает кеш
     */
    public function init(): AdminList
    {
        $this->list = $this->c->Cache->get('admins');

        if (! \is_array($this->list)) {
            $this->list = \array_flip($this->c->users->adminsIds());

            if (true !== $this->c->Cache->set('admins', $this->list)) {
                throw new RuntimeException('Unable to write value to cache - admins');
            }
        }

        return $this;
    }

    /**
     * Сбрасывает кеш списка id админов
     */
    public function reset(): AdminList
    {
        if (true !== $this->c->Cache->delete('admins')) {
            throw new RuntimeException('Unable to remove key from cache - admins');
        }

        return $this;
    }
}
