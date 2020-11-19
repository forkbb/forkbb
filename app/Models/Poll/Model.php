<?php

declare(strict_types=1);

namespace ForkBB\Models\Poll;

use ForkBB\Core\Container;
use ForkBB\Models\DataModel;
use ForkBB\Models\Topic\Model as Topic;
use PDO;
use RuntimeException;

class Model extends DataModel
{
    /**
     * Возвращает родительскую тему
     */
    protected function getparent(): Topic
    {
        if ($this->tid < 1) {
            throw new RuntimeException('Parent is not defined');
        }

        $topic = $this->c->topics->get($this->tid);

        if (! $topic instanceof Topic) {
            throw new RuntimeException('Parent not found');
        }

        return $topic;
    }

    /**
     * Устанавливает родительскую тему
     */
    protected function setparent(Topic $topic): void
    {
        if ($topic->id < 1) {
            throw new RuntimeException('Parent has a bad id');
        }

        if (
            $this->tid > 0
            && $this->tid !== $topic->id
        ) {
            throw new RuntimeException('Alien parent');
        }

        $this->tid = $topic->id;
    }

}
