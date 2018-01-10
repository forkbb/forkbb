<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Topic\Model as Topic;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class View extends Action
{
    /**
     * Возвращает список сообщений
     *
     * @param mixed $arg
     * 
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public function view($arg)
    {
        $stickFP = false;

        if ($arg instanceof Topic) {
            if (! $arg->hasPage()) {
                throw new InvalidArgumentException('Bad number of displayed page');
            }

            $offset = ($arg->page - 1) * $this->c->user->disp_posts;
            $vars = [
                ':tid'    => $arg->id,
                ':offset' => $offset,
                ':rows'   => $this->c->user->disp_posts,
            ];
            $sql = 'SELECT id
                    FROM ::posts
                    WHERE topic_id=?i:tid
                    ORDER BY id LIMIT ?i:offset, ?i:rows';
            $list = $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_COLUMN);

            if (empty($list)) {
                return [];
            }

            // приклейка первого сообщения темы
            if (($arg->stick_fp || $arg->poll_type) && ! in_array($arg->first_post_id, $list)) {
                array_unshift($list, $arg->first_post_id);
                $stickFP = true;
            }

        } elseif (is_array($arg)) {
            $list = $arg; //????
        } else {
            throw new InvalidArgumentException('Expected Topic or array');
        }

        $vars = [
            ':ids' => $list,
        ];
        $sql = 'SELECT id, message, poster, posted
                FROM ::warnings
                WHERE id IN (?ai:ids)';
        $warnings = $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_GROUP);

        //????
        $sql = 'SELECT u.warning_all, u.gender, u.email, u.title, u.url, u.location, u.signature,
                       u.email_setting, u.num_posts, u.registered, u.admin_note, u.messages_enable,
                       u.group_id,
                       p.id, p.poster as username, p.poster_id, p.poster_ip, p.poster_email, p.message,
                       p.hide_smilies, p.posted, p.edited, p.edited_by, p.edit_post, p.user_agent, p.topic_id,
                       g.g_user_title, g.g_promote_next_group, g.g_pm
                FROM ::posts AS p
                INNER JOIN ::users AS u ON u.id=p.poster_id
                INNER JOIN ::groups AS g ON g.g_id=u.group_id
                WHERE p.id IN (?ai:ids)';
        $stmt = $this->c->DB->query($sql, $vars);

        $result = array_flip($list);
        while ($row = $stmt->fetch()) {
            if (isset($warnings[$row['id']])) {
                $row['warnings'] = $warnings[$row['id']];
            }
            $result[$row['id']] = $this->manager->create($row);
        }

        $postCount = 0;
        $timeMax   = 0;

        if ($arg instanceof Topic) {
            foreach ($result as $post) {
                if ($post->posted > $timeMax) {
                    $timeMax = $post->posted;
                }
                if ($stickFP && $post->id === $arg->first_post_id) {
                    $post->postNumber = 1;
                } else {
                    ++$postCount;
                    $post->postNumber = $offset + $postCount;
                }
            }
            $arg->timeMax = $timeMax;
        } else {
            foreach ($result as $post) {
                ++$postCount;
                $post->postNumber = $offset + $postCount; //????
            }
        }
        return $result;
    }
}
