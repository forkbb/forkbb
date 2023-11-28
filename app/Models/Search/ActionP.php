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

        switch ($action) {
            case 'search':
                $list = $this->model->queryIds;

                $this->model->numPages = (int) \ceil(($this->model->count($list) ?: 1) / $this->c->user->disp_posts);

                break;
            case 'posts':
                $vars = [
                    ':forums' => $forums,
                    ':uid'    => $uid,
                ];
                $query = 'SELECT COUNT(p.id)
                    FROM ::posts AS p
                    INNER JOIN ::topics AS t ON t.id=p.topic_id
                    WHERE p.poster_id=?i:uid AND t.forum_id IN (?ai:forums)';

                $count = (int) $this->c->DB->query($query, $vars)->fetchColumn();

                $this->model->numPages = (int) \ceil(($count ?: 1) / $this->c->user->disp_posts);

                break;
            default:
                throw new InvalidArgumentException('Unknown action: ' . $action);
        }

        // нет такой страницы в результате поиска
        if (! $this->model->hasPage()) {
            return false;
        }

        switch ($action) {
            case 'search':
                $this->model->idsList = $this->model->slice(
                    $list,
                    ($this->model->page - 1) * $this->c->user->disp_posts,
                    (int) $this->c->user->disp_posts
                );

                break;
            case 'posts':
                $vars[':offset'] = ($this->model->page - 1) * $this->c->user->disp_posts;
                $vars[':rows']   = (int) $this->c->user->disp_posts;

                $query = 'SELECT p.id
                    FROM ::posts AS p
                    INNER JOIN ::topics AS t ON t.id=p.topic_id
                    WHERE p.poster_id=?i:uid AND t.forum_id IN (?ai:forums)
                    ORDER BY p.posted DESC
                    LIMIT ?i:rows OFFSET ?i:offset';

                $this->model->idsList = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

                break;
        }

        if (empty($this->model->idsList)) {
            return [];
        }

        return $this->c->posts->view($this->model);
    }
}
