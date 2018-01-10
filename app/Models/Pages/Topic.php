<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Models\Topic\Model as ModelTopic;

class Topic extends Page
{
    use CrumbTrait;
    use PostFormTrait;

    /**
     * Переход к первому новому сообщению темы (или в конец)
     *
     * @param array $args
     *
     * @return Page
     */
    public function viewNew(array $args)
    {
        return $this->view('new', $args);
    }

    /**
     * Переход к первому непрочитанному сообщению (или в конец)
     *
     * @param array $args
     *
     * @return Page
     */
    public function viewUnread(array $args)
    {
        return $this->view('unread', $args);
    }

    /**
     * Переход к последнему сообщению темы
     *
     * @param array $args
     *
     * @return Page
     */
    public function viewLast(array $args)
    {
        return $this->view('last', $args);
    }


    /**
     * Просмотр темы по номеру сообщения
     *
     * @param array $args
     *
     * @return Page
     */
    public function viewPost(array $args)
    {
        return $this->view('post', $args);
    }

    /**
     * Просмотр темы по ее номеру
     *
     * @param array $args
     *
     * @return Page
     */
    public function viewTopic(array $args)
    {
        return $this->view('topic', $args);
    }

    /**
     * @param string $type
     * @param ModelTopic $topic
     *
     * @param Page
     */
    protected function go($type, ModelTopic $topic)
    {
        switch ($type) {
            case 'new':
                $pid = $topic->firstNew;
                break;
            case 'unread':
                $pid = $topic->firstUnread;
                break;
            case 'last':
                $pid = $topic->last_post_id;
                break;
            default:
                return $this->c->Message->message('Bad request');
        }

        return $this->c->Redirect->page('ViewPost', ['id' => $pid ?: $topic->last_post_id]);
    }

    /**
     * Подготовка данных для шаблона
     *
     * @param string $type
     * @param array $args
     *
     * @return Page
     */
    protected function view($type, array $args)
    {
        if ($type === 'post') {
            $post  = $this->c->posts->load((int) $args['id']);
            $topic = null === $post ? null : $post->parent;
        } else {
            $topic = $this->c->topics->load((int) $args['id']);
        }

        if (empty($topic) || ! $topic->last_post_id) {
            return $this->c->Message->message('Bad request');
        }

        if ($topic->moved_to) {
            return $this->c->Redirect->page('Topic', ['id' => $topic->moved_to]);
        }

        switch ($type) {
            case 'topic':
                $topic->page = isset($args['page']) ? (int) $args['page'] : 1;
                break;
            case 'post':
                $topic->calcPage((int) $args['id']);
                break;
            default:
                return $this->go($type, $topic);
        }

        if (! $topic->hasPage()) {
            return $this->c->Message->message('Bad request');
        }

        if (! $posts = $this->c->posts->view($topic)) {
            return $this->go('last', $topic);
        }

        $this->c->Lang->load('topic');

        $this->nameTpl      = 'topic';
        $this->onlinePos    = 'topic-' . $topic->id;
        $this->onlineDetail = true;
        $this->canonical    = $this->c->Router->link('Topic', ['id' => $topic->id, 'name' => \ForkBB\cens($topic->subject), 'page' => $topic->page]);
        $this->topic        = $topic;
        $this->posts        = $posts;
        $this->crumbs       = $this->crumbs($topic);
        $this->online       = $this->c->Online->calc($this)->info();
        $this->stats        = null;

        if ($topic->canReply && $this->c->config->o_quickpost == '1') {
            $this->form     = $this->messageForm($topic, 'NewReply', ['id' => $topic->id], false, false, true);
        }

        if ($topic->showViews) {
            $topic->incViews();
        }
        $topic->updateVisits();

        return $this;
    }
}
