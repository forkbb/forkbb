<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Models\Topic\Model as TopicModel;
use function \ForkBB\__;

class Topic extends Page
{
    use PostFormTrait;

    /**
     * Переход к первому новому сообщению темы (или в конец)
     *
     * @param array $args
     *
     * @return Page
     */
    public function viewNew(array $args): Page
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
    public function viewUnread(array $args): Page
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
    public function viewLast(array $args): Page
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
    public function viewPost(array $args): Page
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
    public function viewTopic(array $args): Page
    {
        return $this->view('topic', $args);
    }

    /**
     * @param string $type
     * @param TopicModel $topic
     *
     * @param Page
     */
    protected function go(string $type, TopicModel $topic): Page
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
    protected function view(string $type, array $args): Page
    {
        if ('post' === $type) {
            $post  = $this->c->posts->load((int) $args['id']);
            $topic = null === $post ? null : $post->parent;
        } else {
            $topic = $this->c->topics->load((int) $args['id']);
        }

        if (
            ! $topic instanceof TopicModel
            || ! $topic->last_post_id
        ) {
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

        if (! $posts = $topic->pageData()) {
            return $this->go('last', $topic);
        }

        $this->c->Lang->load('topic');

        $this->nameTpl      = 'topic';
        $this->onlinePos    = 'topic-' . $topic->id;
        $this->onlineDetail = true;
        $this->canonical    = $this->c->Router->link('Topic', ['id' => $topic->id, 'name' => \ForkBB\cens($topic->subject), 'page' => $topic->page]);
        $this->model        = $topic;
        $this->posts        = $posts;
        $this->crumbs       = $this->crumbs($topic);
        $this->online       = $this->c->Online->calc($this)->info();
        $this->stats        = null;

        if (
            $topic->canReply
            && '1' == $this->c->config->o_quickpost
        ) {
            $this->form     = $this->messageForm(['id' => $topic->id], $topic, 'NewReply', false, false, true);
        }

        if (
            $this->user->isAdmin
            || $this->user->isModerator($topic)
        ) {
            $this->c->Lang->load('misc');

            $this->enableMod = true;
            $this->formMod   = $this->formMod($topic);
        }

        if ($topic->showViews) {
            $topic->incViews();
        }
        $topic->updateVisits();

        return $this;
    }

    /**
     * Создает массив данных для формы модерации
     *
     * @param TopicModel $topic
     *
     * @return array
     */
    protected function formMod(TopicModel $topic): array
    {
        $form = [
            'id'     => 'id-form-mod',
            'action' => $this->c->Router->link('Moderate'),
            'hidden' => [
                'token' => $this->c->Csrf->create('Moderate'),
                'forum' => $topic->parent->id,
                'topic' => $topic->id,
                'page'  => $topic->page,
                'ids'   => [$topic->first_post_id => $topic->first_post_id],
                'step'  => 1,
            ],
            'sets'   => [],
            'btns'   => [],
        ];

        if ($topic->closed) {
            $form['btns']['open'] = [
                'type'      => 'submit',
                'value'     => __('Open topic'),
            ];
        } else {
            $form['btns']['close'] = [
                'type'      => 'submit',
                'value'     => __('Close topic'),
            ];
        }

        if ($topic->sticky) {
            $form['btns']['unstick'] = [
                'type'      => 'submit',
                'value'     => __('Unstick topic'),
            ];
        } else {
            $form['btns']['stick'] = [
                'type'      => 'submit',
                'value'     => __('Stick topic'),
            ];
        }

        $form['btns'] += [
            'move' => [
                'type'      => 'submit',
                'value'     => __('Move topic'),
            ],
            'delete' => [
                'type'      => 'submit',
                'value'     => __('Delete'),
            ],
            'split' => [
                'type'      => 'submit',
                'value'     => __('Split'),
            ],
        ];

        return $form;
    }
}
