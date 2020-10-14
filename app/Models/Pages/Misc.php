<?php

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Topic\Model as Topic;
use function \ForkBB\__;

class Misc extends Page
{
    /**
     * Пометка раздела прочитанным
     */
    public function markread(array $args): Page
    {
        $forum = $this->c->forums->loadTree((int) $args['id']);
        if (! $forum instanceof Forum) {
            return $this->c->Message->message('Bad request');
        }

        if (! $this->c->Csrf->verify($args['token'], 'MarkRead', $args)) {
            return $this->c->Redirect->url($forum->link)->message($this->c->Csrf->getError());
        }

        $this->c->forums->markread($forum, $this->user); // ???? флуд интервал?

        $this->c->Lang->load('misc');

        $message = $forum->id ? 'Mark forum read redirect' : 'Mark read redirect';

        return $this->c->Redirect->url($forum->link)->message($message);
    }

    /**
     * Подписка на форум и отписка от него
     */
    public function forumSubscription(array $args): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'ForumSubscription', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
        }

        $forum = $this->c->forums->get((int) $args['fid']);
        if (! $forum instanceof Forum) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('misc');

        if ('subscribe' === $args['type']) {
            if (! $this->user->email_confirmed) {
                return $this->confirmMessage();
            }

            $this->c->subscriptions->subscribe($this->user, $forum);

            $message = 'Subscribe redirect';
        } else {
            $this->c->subscriptions->unsubscribe($this->user, $forum);

            $message = 'Unsubscribe redirect';
        }

        return $this->c->Redirect->url($forum->link)->message($message);
    }

    /**
     * Подписка на топик и отписка от него
     */
    public function topicSubscription(array $args): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'TopicSubscription', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
        }

        $topic = $this->c->topics->load((int) $args['tid']);
        if (! $topic instanceof Topic) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('misc');

        if ('subscribe' === $args['type']) {
            if (! $this->user->email_confirmed) {
                return $this->confirmMessage();
            }

            $this->c->subscriptions->subscribe($this->user, $topic);

            $message = 'Subscribe redirect';
        } else {
            $this->c->subscriptions->unsubscribe($this->user, $topic);

            $message = 'Unsubscribe redirect';
        }

        return $this->c->Redirect->url($topic->link)->message($message);
    }

    protected function confirmMessage(): Page
    {
        $link = $this->c->Router->link(
            'EditUserEmail',
            [
                'id' => $this->user->id,
            ]
        );

        return $this->c->Message->message(__('Confirm your email address', $link), true, 100);
    }
}
