<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Draft;

use ForkBB\Models\DataModel;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Topic\Topic;
use ForkBB\Models\User\User;
use PDO;
use RuntimeException;

class Premod extends DataModel
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Premod';

    public function init(): Premod
    {
        $this->setModelAttrs([]);

        if ($this->c->user->isAdmin) {
            $query = 'SELECT d.id
                FROM ::drafts AS d
                WHERE d.pre_mod=1
                ORDER BY d.id';

            $this->idList = $this->c->DB->query($query)->fetchAll(PDO::FETCH_COLUMN);

        } else {
            $fids  = $this->c->forums->fidsForMod($this->c->user->id);
            $list  = [];
            $query = 'SELECT d.id, d.forum_id as fid, t.forum_id as tfid
                FROM ::drafts AS d
                LEFT JOIN ::topics AS t ON t.id=d.topic_id
                WHERE d.pre_mod=1
                ORDER BY d.id';

            $stmt = $this->c->DB->query($query);

            while (false !== ($row = $stmt->fetch())) {
                if (isset($fids[$row['fid'] ?: $row['tfid']])) {
                    $list[] = $row['id'];
                }
            }

            $this->idList = $list;
        }

        return $this;
    }

    /**
     * Размер очереди премодерации
     */
    protected function getcount(): int
    {
        return \count($this->idList);
    }

    /**
     * Количество страниц в очереди
     */
    public function numPages(): int
    {
        return (int) \ceil($this->count / $this->c->user->disp_posts);
    }

    /**
     * Возвращает список черновиков со старницы $page для премодерации
     */
    public function view(int $page): array
    {
        $offset = ($page - 1) * $this->c->user->disp_posts;
        $ids    = \array_slice($this->idList, $offset, $this->c->user->disp_topics);

        if (empty($ids)) {
            return [];
        }

        $userIds = [];
        $result  = $this->c->drafts->loadByIds($ids);

        foreach ($result as $draft) {
            ++$offset;

            if ($draft instanceof Draft) {
                $draft->__postNumber = $offset;

                if ($draft->poster_id > 0) {

                    $userIds[$draft->poster_id] = $draft->poster_id;
                } else {
                    $draft->user; // создание гостя до передачи данных в шаблон
                }
            }
        }

        if (! empty($userIds)) {
            $this->c->users->loadByIds($userIds);
        }

        return $result;
    }
}
