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
use function \ForkBB\__;

class Index extends Page
{
    /**
     * Подготовка данных для шаблона
     */
    public function view(): Page
    {
        $this->c->Lang->load('index');
        $this->c->Lang->load('subforums');

        // крайний пользователь // ???? может в stats переместить?
        $this->c->stats->userLast = [
            'name' => $this->c->stats->userLast['username'],
            'link' => $this->user->viewUsers
                ? $this->c->Router->link(
                    'User',
                    [
                        'id'   => $this->c->stats->userLast['id'],
                        'name' => $this->c->stats->userLast['username'],
                    ]
                )
                : null,
        ];

        // для таблицы разделов
        $root   = $this->c->forums->loadTree(0);
        $forums = empty($root) ? [] : $root->subforums;
        $ctgs   = [];

        if (empty($forums)) {
            $this->fIswev = ['i', 'Empty board'];
        } else {
            foreach ($forums as $forum) {
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
                    'id' => 0,
                ]
            );
        }

        if ($this->c->config->i_feed_type > 0) {
            $feedType = 2 === $this->c->config->i_feed_type ? 'atom' : 'rss';

            $this->pageHeader('feed', 'link', 0, [
                'rel'  => 'alternate',
                'type' => "application/{$feedType}+xml",
                'href' => $this->c->Router->link('Feed', ['type' => $feedType]),
            ]);
        }

        return $this;
    }
}
