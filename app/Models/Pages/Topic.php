<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;

class Topic extends Page
{
    use CrumbTrait;

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
     * @param Models\Topic $topic
     *
     * @param Page
     */
    protected function go($type, $topic)
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
        $topic = $this->c->ModelTopic->load((int) $args['id'], $type === 'post');

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

        if (! $posts = $topic->posts()) {
            return $this->go('last', $topic);
        }

        $this->c->Lang->load('topic');

        $user = $this->c->user;

        // данные для формы быстрого ответа
        $form = null;
        if ($topic->canReply && $this->c->config->o_quickpost == '1') {
            $form = [
                'action' => $this->c->Router->link('NewReply', ['id' => $topic->id]),
                'hidden' => [
                    'token' => $this->c->Csrf->create('NewReply', ['id' => $topic->id]),
                ],
                'sets'   => [],
                'btns'   => [
                    'submit'  => ['submit', __('Submit'), 's'],
                    'preview' => ['submit', __('Preview'), 'p'],
                ],
            ];

            $fieldset = [];
            if ($user->isGuest) {
                $fieldset['username'] = [
                    'dl'        => 't1',
                    'type'      => 'text',
                    'maxlength' => 25,
                    'title'     => __('Username'),
                    'required'  => true,
                    'pattern'   => '^.{2,25}$',
                ];
                $fieldset['email'] = [
                    'dl'        => 't2',
                    'type'      => 'text',
                    'maxlength' => 80,
                    'title'     => __('Email'),
                    'required'  => $this->c->config->p_force_guest_email == '1',
                    'pattern'   => '.+@.+',
                ];
            }

            $fieldset['message'] = [
                'type'     => 'textarea',
                'title'    => __('Message'),
                'required' => true,
                'bb'       => [
                    ['link', __('BBCode'), __($this->c->config->p_message_bbcode == '1' ? 'on' : 'off')],
                    ['link', __('url tag'), __($this->c->config->p_message_bbcode == '1' && $user->g_post_links == '1' ? 'on' : 'off')],
                    ['link', __('img tag'), __($this->c->config->p_message_bbcode == '1' && $this->c->config->p_message_img_tag == '1' ? 'on' : 'off')],
                    ['link', __('Smilies'), __($this->c->config->o_smilies == '1' ? 'on' : 'off')],
                ],
            ];
            $form['sets'][] = [
                'fields' => $fieldset,
            ];

            $fieldset = [];
            if ($user->isAdmin || $user->isModerator($topic)) {
                $fieldset['merge_post'] = [
                    'type'    => 'checkbox',
                    'label'   => __('Merge posts'),
                    'value'   => '1',
                    'checked' => true,
                ];
            }
            if ($fieldset) {
                $form['sets'][] = [
                    'legend' => __('Options'),
                    'fields' => $fieldset,
                ];
            }
        }

        $this->nameTpl      = 'topic';
        $this->onlinePos    = 'topic-' . $topic->id;
        $this->onlineDetail = true;
        $this->canonical    = $this->c->Router->link('Topic', ['id' => $topic->id, 'name' => $topic->cens()->subject, 'page' => $topic->page]);
        $this->topic        = $topic;
        $this->posts        = $posts;
        $this->crumbs       = $this->crumbs($topic);
        $this->online       = $this->c->Online->calc($this)->info();
        $this->stats        = null;
        $this->form         = $form;

        if ($topic->showViews) {
            $topic->incViews();
        }
        $topic->updateVisits();

        return $this;
    }
}
