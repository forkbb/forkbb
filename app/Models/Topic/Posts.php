<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\Method;
use Iterator;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class Posts extends Method implements Iterator
{
    protected $key;

    protected $row;

    protected $stmt;

    protected $warnings;

    protected $postCount;

    protected $post;

    protected $offset;

    /**
     * 
     * 
     * @throws RuntimeException
     * 
     * @return null|Method
     */
    public function posts()
    {
        if ($this->model->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }

        if (! $this->model->hasPage()) {
            throw new InvalidArgumentException('Bad number of displayed page');
        }

        $this->offset = ($this->model->page - 1) * $this->c->user->disp_posts;
        $vars = [
            ':tid'    => $this->model->id,
            ':offset' => $this->offset,
            ':rows'   => $this->c->user->disp_posts,
        ];
        $sql = 'SELECT id
                FROM ::posts
                WHERE topic_id=?i:tid
                ORDER BY id LIMIT ?i:offset, ?i:rows';

        $ids = $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_COLUMN);
        if (empty($ids)) {
            return null;
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

        $this->warnings = $this->c->DB->query($sql, $vars)->fetchAll(\PDO::FETCH_GROUP);

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

        $this->stmt = $this->c->DB->query($sql, $vars);
        $this->model->timeMax = 0;
        $this->postCount = 0;
        $this->post = $this->c->posts->create();

        return $this;
    }

    public function rewind()
    {
        $this->key = 0;
    }
  
    public function current()
    {
        if (empty($this->row)) { //????
            return false;
        }

        $cur = $this->row;

        if ($cur['posted'] > $this->model->timeMax) {
            $this->model->timeMax = $cur['posted'];
        }

        // номер сообшения в теме
        if ($cur['id'] == $this->model->first_post_id && $this->offset > 0) {
            $cur['postNumber'] = 1;
        } else {
            ++$this->postCount;
            $cur['postNumber'] = $this->offset + $this->postCount;
        }

        if (isset($this->warnings[$cur['id']])) {
            $cur['warnings'] = $this->warnings[$cur['id']];
        }

        return $this->post->setAttrs($cur);
    }
  
    public function key() 
    {
        return $this->key;
    }
  
    public function next() 
    {
        ++$this->key;
    }
  
    public function valid()
    {
        $this->row = $this->stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_ABS, $this->key);
        return false !== $this->row;
    }

}
