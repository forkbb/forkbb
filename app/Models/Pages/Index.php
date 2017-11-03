<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;

class Index extends Page
{
    use ForumsTrait;
    use OnlineTrait;

    /**
     * Подготовка данных для шаблона
     * 
     * @return Page
     */
    public function view()
    {
        $this->c->Lang->load('index');
        $this->c->Lang->load('subforums');

        $stats = [];
        $stats['total_users']  = $this->number($this->c->stats->userTotal);
        $stats['total_posts']  = $this->number($this->c->stats->postTotal);
        $stats['total_topics'] = $this->number($this->c->stats->topicTotal);

        if ($this->c->user->g_view_users == '1') {
            $stats['newest_user'] = [
                $this->c->Router->link('User', [
                    'id'   => $this->c->stats->userLast['id'],
                    'name' => $this->c->stats->userLast['username'],
                ]),
                $this->c->stats->userLast['username']
            ];
        } else {
            $stats['newest_user'] = $this->c->stats->userLast['username'];
        }

        $this->nameTpl      = 'index';
        $this->onlinePos    = 'index';
        $this->onlineType   = true;
        $this->onlineFilter = false;
        $this->canonical    = $this->c->Router->link('Index');
        $this->stats        = $stats;
        $this->online       = $this->usersOnlineInfo();
        $this->forums       = $this->forumsData();

        return $this;
    }
}
