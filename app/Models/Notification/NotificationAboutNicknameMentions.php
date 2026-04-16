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
            || ! $data['post'] instanceof Post
            || (
                true !== $data['quoted']
                && true !== $data['mentioned']
            )
        ) {
            return false;
        }

        $this->user      = $data['user'];
        $this->post      = $data['post'];
        $this->quoted    = $data['quoted'];
        $this->mentioned = $data['mentioned'];

        return true;
    }
}
