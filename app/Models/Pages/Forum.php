<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Models\Forum\Model as ForumModel;
use function \ForkBB\__;

class Forum extends Page
{
    /**
     * Подготовка данных для шаблона
     *
     * @param array $args
     *
     * @return Page
     */
    public function view(array $args): Page
    {
        $this->c->Lang->load('forum');
        $this->c->Lang->load('subforums');

        $forum = $this->c->forums->loadTree((int) $args['id']);
        if (! $forum instanceof ForumModel) {
            return $this->c->Message->message('Bad request');
        }

        // редирект, если раздел это ссылка
        if ($forum->redirect_url) {
            return $this->c->Redirect->url($forum->redirect_url);
        }

        $forum->page = isset($args['page']) ? (int) $args['page'] : 1;
        if (! $forum->hasPage()) {
            return $this->c->Message->message('Bad request');
        }

        $this->fIndex     = 'index';
        $this->nameTpl    = 'forum';
        $this->onlinePos  = 'forum-' . $args['id'];
        $this->canonical  = $this->c->Router->link(
            'Forum',
            [
                'id'   => $args['id'],
                'name' => $forum->forum_name,
                'page' => $forum->page,
            ]
        );
        $this->model      = $forum;
        $this->topics     = $forum->pageData();
        $this->crumbs     = $this->crumbs($forum);

        if (empty($this->topics)) {
            $this->fIswev = ['i', __('Empty forum')];
        } elseif (
            $this->user->isAdmin
            || $this->user->isModerator($forum)
        ) {
            $this->c->Lang->load('misc');

            $this->enableMod = true;
            $this->formMod   = $this->formMod($forum);
        }

        return $this;
    }

    /**
     * Создает массив данных для формы модерации
     *
     * @param ForumModel $forum
     *
     * @return array
     */
    protected function formMod(ForumModel $forum): array
    {
        $form = [
            'id'     => 'id-form-mod',
            'action' => $this->c->Router->link('Moderate'),
            'hidden' => [
                'token' => $this->c->Csrf->create('Moderate'),
                'forum' => $forum->id,
                'page'  => $forum->page,
                'step'  => 1,
            ],
            'sets'   => [],
            'btns'   => [
                'open' => [
                    'type'      => 'submit',
                    'value'     => __('Open'),
                ],
                'close' => [
                    'type'      => 'submit',
                    'value'     => __('Close'),
                ],
                'delete' => [
                    'type'      => 'submit',
                    'value'     => __('Delete'),
                ],
                'move' => [
                    'type'      => 'submit',
                    'value'     => __('Move'),
                ],
                'merge' => [
                    'type'      => 'submit',
                    'value'     => __('Merge'),
                ],
            ],
        ];

        return $form;
    }
}
