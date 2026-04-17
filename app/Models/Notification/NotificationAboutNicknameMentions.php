<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Notification;

use ForkBB\Models\Notification\Notification;
use ForkBB\Models\Post\Post;
use ForkBB\Models\Topic\Topic;
use ForkBB\Models\User\User;

class NotificationAboutNicknameMentions extends Notification
{
    protected Post $post;
    protected bool $quoted;
    protected bool $mentioned;

    public function init(array $data): bool
    {
        if (
            ! $data['user'] instanceof User
            || $data['user']->isGuest
            || $data['user']->isUnverified
//          || $data['user']->isBanByName
            || (
                true !== $data['quoted']
                && true !== $data['mentioned']
            )
            || ! $data['post'] instanceof Post
            || ! ($topic = $data['post']->parent) instanceof Topic
            || true !== $this->c->notifications->permReadForum($topic->forum_id, $data['user'])
        ) {
            return false;
        }

        $this->user      = $data['user'];
        $this->post      = $data['post'];
        $this->quoted    = $data['quoted'];
        $this->mentioned = $data['mentioned'];

        return true;
    }

    public function title(): array|string
    {
        return match (true) {
            true === $this->quoted && true === $this->mentioned => 'You been quoted and mentioned',
            true === $this->quoted                              => 'You been quoted',
            default                                             => 'You been mentioned',
        };
    }

    public function text(): array|string
    {
        return ['Topic %1$s Post %2$s', $this->post->parent->name, $this->post->link];
    }
}
