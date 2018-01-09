<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\Method;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class Posts extends Method
{
    /**
     * Возвращает массив сообщений с установленной ранее страницы темы
     *
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public function posts()
    {
        if (! $this->model->hasPage()) {
            throw new InvalidArgumentException('Bad number of displayed page');
        }

        $offset = ($this->model->page - 1) * $this->c->user->disp_posts;
        $vars = [
            ':tid'    => $this->model->id,
            ':offset' => $offset,
            ':rows'   => $this->c->user->disp_posts,
        ];
        $sql = 'SELECT id
                FROM ::posts
                WHERE topic_id=?i:tid
                ORDER BY id LIMIT ?i:offset, ?i:rows';

        $ids = $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_COLUMN);
        if (empty($ids)) {
            return [];
        }

        // приклейка первого сообщения темы
        if ($this->model->stick_fp || $this->model->poll_type) {
            $ids[] = $this->model->first_post_id;
        }

        $vars = [
            ':ids' => $ids,
        ];
        $sql = 'SELECT id, message, poster, posted
                FROM ::warnings
                WHERE id IN (?ai:ids)';

        $warnings = $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_GROUP);

        $vars = [
            ':ids' => $ids,
        ];
        $sql = 'SELECT u.warning_all, u.gender, u.email, u.title, u.url, u.location, u.signature,
                       u.email_setting, u.num_posts, u.registered, u.admin_note, u.messages_enable,
                       u.group_id,
                       p.id, p.poster as username, p.poster_id, p.poster_ip, p.poster_email, p.message,
                       p.hide_smilies, p.posted, p.edited, p.edited_by, p.edit_post, p.user_agent, p.topic_id,
                       g.g_user_title, g.g_promote_next_group, g.g_pm
                FROM ::posts AS p
                INNER JOIN ::users AS u ON u.id=p.poster_id
                INNER JOIN ::groups AS g ON g.g_id=u.group_id
                WHERE p.id IN (?ai:ids) ORDER BY p.id';

        $stmt = $this->c->DB->query($sql, $vars);

        $postCount = 0;
        $timeMax = 0;
        $result = [];

        while ($cur = $stmt->fetch()) {
            if ($cur['posted'] > $timeMax) {
                $timeMax = $cur['posted'];
            }

            // номер сообшения в теме
            if ($cur['id'] == $this->model->first_post_id && $offset > 0) {
                $cur['postNumber'] = 1;
            } else {
                ++$postCount;
                $cur['postNumber'] = $offset + $postCount;
            }

            if (isset($warnings[$cur['id']])) {
                $cur['warnings'] = $warnings[$cur['id']];
            }

            $result[] = $this->c->posts->create($cur);
        }
        $this->model->timeMax = $timeMax;
        return $result;
    }
}
