<?php
/**
 * This file is part of the ForkBB <https://forkbb.org, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Notification;

use ForkBB\Models\Notification\Notification;
use ForkBB\Models\Notification\Notifications;
use ForkBB\Models\Post\Post;
use ForkBB\Models\User\User;
use function \ForkBB\{__, e};

class NotificationAboutSubscription extends Notification
{
    const BITS = Notifications::ALL ^ (Notifications::PM | Notifications::EMAIL);

    protected Post $post;
    protected bool $newTopic;

    public function init(array $data): bool
    {
        $user     = $data['user'] ?? null;
        $post     = $data['post'] ?? null;
        $newTopic = $data['new'] ?? null;

        if (
            ! $user instanceof User
            || false === $user->subscrPossible
            || false === $user->subscrPossibleTele
            || ! $post instanceof Post
            || false === \is_bool($newTopic)
        ) {
            return false;
        }

        $this->user      = $user;
        $this->post      = $post;
        $this->newTopic  = $newTopic;
        $this->localRule = ($user->ntfy_subscr ?? 0) & self::BITS;

        return true;
    }

    public function title(): array|string
    {
        return $this->newTopic ? 'New topic' : 'New post';
    }

    public function text(): array|string
    {
        return [
            'Topic %1$s Post %2$s User %3$s',
            $this->post->parent->name,
            $this->newTopic ? $this->post->parent->link : $this->post->link,
            $this->post->user->username
        ];
    }
}
