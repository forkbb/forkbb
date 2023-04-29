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
use ForkBB\Models\Forum\Forum as ForumModel;
use function \ForkBB\__;

class Forum extends Page
{
    /**
     * Подготовка данных для шаблона
     */
    public function view(array $args): Page
    {
        $this->c->Lang->load('forum');
        $this->c->Lang->load('subforums');

        $forum = $this->c->forums->loadTree($args['id']);

        if (! $forum instanceof ForumModel) {
            return $this->c->Message->message('Bad request');
        }

        // редирект, если раздел это ссылка
        if ($forum->redirect_url) {
            return $this->c->Redirect->url($forum->redirect_url);
        }

        $forum->page = $args['page'] ?? 1;

        if (! $forum->hasPage()) {
            return $this->c->Message->message('Not Found', true, 404);
        }

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
            $this->fIswev = ['i', 'Empty forum'];
        } elseif (
            $this->user->isAdmin
            || $this->user->isModerator($forum)
        ) {
            $this->c->Lang->load('misc');

            $this->enableMod = true;
            $this->formMod   = $this->formMod($forum);
        }

        if ($this->c->config->i_feed_type > 0) {
            $feedType = 2 === $this->c->config->i_feed_type ? 'atom' : 'rss';

            $this->pageHeader('feed', 'link', 0, [
                'rel'  => 'alternate',
                'type' => "application/{$feedType}+xml",
                'href' => $this->c->Router->link('Feed', ['type' => $feedType, 'fid' => $forum->id]),
            ]);
        }

        return $this;
    }

    /**
     * Создает массив данных для формы модерации
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
                    'class'     => ['origin'],
                    'type'      => 'submit',
                    'value'     => __('Open'),
                ],
                'close' => [
                    'class'     => ['origin'],
                    'type'      => 'submit',
                    'value'     => __('Close'),
                ],
                'delete' => [
                    'class'     => ['origin'],
                    'type'      => 'submit',
                    'value'     => __('Delete'),
                ],
                'move' => [
                    'class'     => ['origin'],
                    'type'      => 'submit',
                    'value'     => __('Move'),
                ],
                'merge' => [
                    'class'     => ['origin'],
                    'type'      => 'submit',
                    'value'     => __('Merge'),
                ],
            ],
        ];

        return $form;
    }
}
