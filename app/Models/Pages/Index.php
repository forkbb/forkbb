<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use function \ForkBB\__;

class Index extends Page
{
    /**
     * Подготовка данных для шаблона
     *
     * @return Page
     */
    public function view(): Page
    {
        $this->c->Lang->load('index');
        $this->c->Lang->load('subforums');

        // крайний пользователь // ???? может в stats переместить?
        $this->c->stats->userLast = $this->user->viewUsers
            ? [
                $this->c->Router->link(
                    'User', [
                        'id'   => $this->c->stats->userLast['id'],
                        'name' => $this->c->stats->userLast['username'],
                    ]
                ),
                $this->c->stats->userLast['username'],
            ]
            : $this->c->stats->userLast['username'];

        // для таблицы разделов
        $root   = $this->c->forums->loadTree(0);
        $forums = empty($root) ? [] : $root->subforums;
        $ctgs   = [];
        if (empty($forums)) {
            $this->fIswev = ['i', __('Empty board')];
        } else {
            foreach($forums as $forum) {
                $ctgs[$forum->cat_id][] = $forum;
            }
        }

        $this->nameTpl      = 'index';
        $this->onlinePos    = 'index';
        $this->onlineDetail = true;
        $this->onlineFilter = false;
        $this->canonical    = $this->c->Router->link('Index');
        $this->stats        = $this->c->stats;
        $this->online       = $this->c->Online->calc($this)->info();
        $this->categoryes   = $ctgs;

        if (! $this->user->isGuest) {
            $this->linkMarkRead = $this->c->Router->link(
                'MarkRead',
                [
                    'id'    => 0,
                    'token' => $this->c->Csrf->create(
                        'MarkRead',
                        [
                            'id' => 0,
                        ]
                    ),
                ]
            );
        }

        if ($this->c->config->o_feed_type > 0) {
            $feedType = '2' == $this->c->config->o_feed_type ? 'atom' : 'rss';
            $this->pageHeader('feed', 'link', [
                'rel'  => 'alternate',
                'type' => "application/{$feedType}+xml",
                'href' => $this->c->Router->link('Feed', ['type' => $feedType]),
            ]);
        }

        return $this;
    }
}
