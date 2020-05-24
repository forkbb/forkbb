<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;

class Misc extends Page
{
    /**
     * Пометка раздела прочитанным
     *
     * @param array $args
     *
     * @return Page
     */
    public function markread(array $args): Page
    {
        $forum = $this->c->forums->loadTree($args['id']);
        if (null === $forum) {
            return $this->c->Message->message('Bad request');
        }

        if (! $this->c->Csrf->verify($args['token'], 'MarkRead', $args)) {
            return $this->c->Redirect->url($forum->link)->message('Bad token');
        }

        $this->c->forums->markread($forum, $this->user); // ???? флуд интервал?

        $this->c->Lang->load('misc');

        $message = $forum->id ? 'Mark forum read redirect' : 'Mark read redirect';

        return $this->c->Redirect->url($forum->link)->message($message);
    }
}
