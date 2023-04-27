<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Search;

use ForkBB\Models\Method;
use ForkBB\Models\Forum\Forum;
use PDO;
use InvalidArgumentException;

class ActionP extends Method
{
    /**
     * Поисковые действия по сообщениям
     */
    public function actionP(string $action, Forum $root, int $uid = null): array|false
    {
        $forums = \array_keys($root->descendants);

        if ($root->id) {
            $forums[] = $root->id;
        }
        if (empty($forums)) {
            return [];
        }

        $query = null;
        switch ($action) {
            case 'search':
                $list = $this->model->queryIds;
                break;
            case 'posts':
                $query = 'SELECT p.id
                    FROM ::posts AS p
                    INNER JOIN ::topics AS t ON t.id=p.topic_id
                    WHERE t.forum_id IN (?ai:forums) AND t.moved_to=0 AND p.poster_id=?i:uid
                    ORDER BY p.posted DESC';
                break;
            default:
                throw new InvalidArgumentException('Unknown action: ' . $action);
        }

        if (null !== $query) {
            $vars = [
                ':forums' => $forums,
                ':uid'    => $uid,
            ];

            $list = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
        }

        $this->model->numPages = (int) \ceil((\count($list) ?: 1) / $this->c->user->disp_posts);

        // нет такой страницы в результате поиска
        if (! $this->model->hasPage()) {
            return false;
        // результат пуст
        } elseif (empty($list)) {
            return [];
        }

        $this->model->idsList = \array_slice(
            $list,
            ($this->model->page - 1) * $this->c->user->disp_posts,
            (int) $this->c->user->disp_posts
        );

        return $this->c->posts->view($this->model);
    }
}
