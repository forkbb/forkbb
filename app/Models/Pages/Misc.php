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
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Post\Post;
use ForkBB\Models\Topic\Topic;
use function \ForkBB\__;

class Misc extends Page
{
    public function opensearch(): Page
    {
        $this->nameTpl      = "opensearch";
        $this->onlinePos    = null;
        $this->onlineDetail = null;
        $this->imageLink    = \preg_replace('%^(.*://(?:[^/]++)).*$%', '$1', $this->c->BASE_URL) . '/favicon.ico'; // ???? костыль O_o
        $this->searchLink   = \strtr(
            $this->c->Router->link('Search', ['keywords' => 'SEARCHTERMS']),
            ['SEARCHTERMS' => '{searchTerms}']
        );

        $this->header('Content-type', 'application/xml; charset=utf-8');

        return $this;
    }

    /**
     * Пометка раздела прочитанным
     */
    public function markread(array $args): Page
    {
        $forum = $this->c->forums->loadTree($args['id']);

        if (! $forum instanceof Forum) {
            return $this->c->Message->message('Bad request');
        }

        if (! $this->c->Csrf->verify($args['token'], 'MarkRead', $args)) {
            return $this->c->Redirect->url($forum->link)->message($this->c->Csrf->getError(), FORK_MESS_ERR);
        }

        $this->c->forums->markread($forum, $this->user);

        $this->c->Lang->load('misc');

        $message = $forum->id ? 'Mark forum read redirect' : 'Mark read redirect';

        return $this->c->Redirect->url($forum->link)->message($message, FORK_MESS_SUCC);
    }

    /**
     * Подписка на форум и отписка от него
     */
    public function forumSubscription(array $args): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'ForumSubscription', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
        }

        $forum = $this->c->forums->get($args['fid']);

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

        return $this->c->Redirect->url($forum->link)->message($message, FORK_MESS_SUCC);
    }

    /**
     * Подписка на топик и отписка от него
     */
    public function topicSubscription(array $args): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'TopicSubscription', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
        }

        $topic = $this->c->topics->load($args['tid']);

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

        return $this->c->Redirect->url($topic->link)->message($message, FORK_MESS_SUCC);
    }

    protected function confirmMessage(): Page
    {
        $link = $this->c->Router->link(
            'EditUserEmail',
            [
                'id' => $this->user->id,
            ]
        );

        return $this->c->Message->message(['Confirm your email address', $link], true, 100);
    }

    /**
     * Устанавливает или сбрасывает статус 'Решение' у сообщения/темы
     */
    public function solution(array $args): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'ChSolution', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
        }

        $post = $this->c->posts->load($args['id']);

        if (
            ! $post instanceof Post
            || ! ($topic = $post->parent) instanceof Topic
            || true !== $topic->canChSolution
        ) {
            return $this->c->Message->message('Bad request');
        }

        if ($args['id'] === $topic->solution) {
            $message = 'Solution removed';
            $status  = FORK_MESS_ERR;

            $topic->solution       = 0;
            $topic->solution_wa    = '';
            $topic->solution_wa_id = 0;
            $topic->solution_time  = 0;
        } else {
            if (0 === $topic->solution) {
                $message = 'Solution chosen';
                $status  = FORK_MESS_SUCC;
            } else {
                $message = 'Solution changed';
                $status  = FORK_MESS_WARN;
            }

            $topic->solution       = $args['id'];
            $topic->solution_wa    = $this->c->user->username;
            $topic->solution_wa_id = $this->c->user->id;
            $topic->solution_time  = \time();
        }

        $this->c->topics->update($topic);

        $this->c->Lang->load('misc');

        return $this->c->Redirect->url($post->link)->message($message, $status);
    }
}
