<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Models\Topic\Topic as TopicModel;
use function \ForkBB\__;

class Topic extends Page
{
    use PostFormTrait;

    /**
     * Переход к первому новому сообщению темы (или в конец)
     */
    public function viewNew(array $args): Page
    {
        return $this->view('new', $args);
    }

    /**
     * Переход к первому непрочитанному сообщению (или в конец)
     */
    public function viewUnread(array $args): Page
    {
        return $this->view('unread', $args);
    }

    /**
     * Переход к последнему сообщению темы
     */
    public function viewLast(array $args): Page
    {
        return $this->view('last', $args);
    }


    /**
     * Просмотр темы по номеру сообщения
     */
    public function viewPost(array $args): Page
    {
        return $this->view('post', $args);
    }

    /**
     * Просмотр темы по ее номеру
     */
    public function viewTopic(array $args): Page
    {
        return $this->view('topic', $args);
    }

    /**
     * Просмотр
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
     */
    protected function view(string $type, array $args): Page
    {
        if ('post' === $type) {
            $post  = $this->c->posts->load($args['id']);
            $topic = null === $post ? null : $post->parent;
        } else {
            $topic = $this->c->topics->load($args['id']);
        }

        if (! $topic instanceof TopicModel) {
            return $this->c->Message->message('Bad request');
        }

        if ($topic->moved_to) {
            return $this->c->Redirect->url($topic->link);
        }

        if (! $topic->last_post_id) {
            return $this->c->Message->message('Bad request');
        }

        switch ($type) {
            case 'topic':
                $topic->page = $args['page'] ?? 1;
                break;
            case 'post':
                $topic->calcPage($args['id']);
                break;
            default:
                return $this->go($type, $topic);
        }

        if (! $topic->hasPage()) {
            return $this->c->Message->message('Not Found', true, 404);
        }

        $this->posts        = $topic->pageData();

        if (empty($this->posts)) {             // ???? зацикливание?
            return $this->go('last', $topic);
        }

        $this->c->Lang->load('topic');

        $this->nameTpl      = 'topic';
        $this->onlinePos    = 'topic-' . $topic->id;
        $this->onlineDetail = true;
        $this->canonical    = $this->c->Router->link(
            'Topic',
            [
                'id'   => $topic->id,
                'name' => $topic->name,
                'page' => $topic->page
            ]
        );
        $this->model        = $topic;
        $this->crumbs       = $this->crumbs($topic);
        $this->online       = $this->c->Online->calc($this)->info();
        $this->stats        = null;

        if (
            $topic->canReply
            && 1 == $this->c->config->b_quickpost
        ) {
            $this->form     = $this->messageForm($topic, 'NewReply', ['id' => $topic->id], false, false, true);
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

        if ($this->c->config->i_feed_type > 0) {
            $feedType = 2 === $this->c->config->i_feed_type ? 'atom' : 'rss';
            $this->pageHeader('feed', 'link', 0, [
                'rel'  => 'alternate',
                'type' => "application/{$feedType}+xml",
                'href' => $this->c->Router->link('Feed', ['type' => $feedType, 'tid' => $topic->id]),
            ]);
        }

        if (
            $topic->poll_type > 0
            && 1 === $this->c->config->b_poll_enabled
        ) {
            $this->c->Lang->load('poll');

            $this->poll = $topic->poll;
        }

        return $this;
    }

    /**
     * Создает массив данных для формы модерации
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
                'class'     => ['origin'],
                'type'      => 'submit',
                'value'     => __(['Open topic btn', 1]),
            ];
        } else {
            $form['btns']['close'] = [
                'class'     => ['origin'],
                'type'      => 'submit',
                'value'     => __(['Close topic btn', 1]),
            ];
        }

        if ($topic->sticky) {
            $form['btns']['unstick'] = [
                'class'     => ['origin'],
                'type'      => 'submit',
                'value'     => __(['Unstick btn', 1]),
            ];
        } else {
            $form['btns']['stick'] = [
                'class'     => ['origin'],
                'type'      => 'submit',
                'value'     => __(['Stick btn', 1]),
            ];
        }

        $form['btns'] += [
            'move' => [
                'class'     => ['origin'],
                'type'      => 'submit',
                'value'     => __(['Move topic btn', 1]),
            ],
            'delete' => [
                'class'     => ['origin'],
                'type'      => 'submit',
                'value'     => __('Delete'),
            ],
            'split' => [
                'class'     => ['origin'],
                'type'      => 'submit',
                'value'     => __('Split'),
            ],
        ];

        return $form;
    }
}
