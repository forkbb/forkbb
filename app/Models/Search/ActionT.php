<?php

namespace ForkBB\Models\Search;

use ForkBB\Models\Method;
use ForkBB\Models\Forum\Model as Forum;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class ActionT extends Method
{
    /**
     * Поисковые действия по темам
     *
     * @param string $action
     * @param Forum $root
     * @param int $uid
     *
     * @throws InvalidArgumentException
     *
     * @return false|array
     */
    public function actionT($action, Forum $root, $uid = null)
    {
        $forums = \array_keys($root->descendants);
        if ($root->id) {
            $forums[] = $root->id;
        }
        if (empty($forums)) {
            return [];
        }

        $sql = null;
        switch ($action) {
            case 'search':
                $list = $this->model->queryIds;
                break;
            case 'latest_active_topics':
                $sql = 'SELECT t.id
                        FROM ::topics AS t
                        WHERE t.forum_id IN (?ai:forums) AND t.moved_to IS NULL
                        ORDER BY t.last_post DESC';
                break;
            case 'unanswered_topics':
                $sql = 'SELECT t.id
                        FROM ::topics AS t
                        WHERE t.forum_id IN (?ai:forums) AND t.moved_to IS NULL AND t.num_replies=0
                        ORDER BY t.last_post DESC';
                break;
            case 'topics_with_your_posts':
                $sql = 'SELECT t.id
                        FROM ::topics AS t
                        INNER JOIN ::posts AS p ON t.id=p.topic_id
                        WHERE t.forum_id IN (?ai:forums) AND t.moved_to IS NULL AND p.poster_id=?i:uid
                        GROUP BY t.id
                        ORDER BY t.last_post DESC';
                break;
            case 'topics':
                $sql = 'SELECT t.id
                        FROM ::topics AS t
                        INNER JOIN ::posts AS p ON t.first_post_id=p.id
                        WHERE t.forum_id IN (?ai:forums) AND t.moved_to IS NULL AND p.poster_id=?i:uid
                        ORDER BY t.last_post DESC';
                break;
            default:
                throw new InvalidArgumentException('Unknown action: ' . $action);
        }

        if (null !== $sql) {
            $vars = [
                ':forums' => $forums,
                ':uid'    => $uid,
            ];
            $list = $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_COLUMN);
        }

        $this->model->numPages = (int) \ceil((\count($list) ?: 1) / $this->c->user->disp_topics);

        // нет такой страницы в результате поиска
        if (! $this->model->hasPage()) {
            return false;
        // результат пуст
        } elseif (empty($list)) {
            return [];
        }

        $this->model->idsList = \array_slice($list, ($this->model->page - 1) * $this->c->user->disp_topics, $this->c->user->disp_topics);

        return $this->c->topics->view($this->model);
    }
}
