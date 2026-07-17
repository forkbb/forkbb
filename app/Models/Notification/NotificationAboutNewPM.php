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
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\PM\PTopic;
use ForkBB\Models\User\User;

class NotificationAboutNewPM extends Notification
{
    const BITS = Notifications::ALL ^ Notifications::PM;

    protected PTopic $topic;
    protected User $sender;

    public function init(array $data): bool
    {
        $target = $data['target'] ?? null;
        $sender = $data['sender'] ?? null;
        $topic  = $data['topic'] ?? null;

        if (
            ! $target instanceof User
            || $target->isGuest
            || $target->isUnverified
            || $target->isBanByName
            || ! $sender instanceof User
            || $sender->isGuest
            || $sender->isUnverified
            || $sender->isBanByName
            || ! $topic instanceof PTopic
            || (
                $target->id !== $topic->poster_id
                && $target->id !== $topic->target_id
            )
            || (
                $sender->id !== $topic->poster_id
                && $sender->id !== $topic->target_id
            )
        ) {
            return false;
        }

        $this->user      = $target;
        $this->sender    = $sender;
        $this->topic     = $topic;
        $this->localRule = ($this->user->ntfy_pm ?? 0) & self::BITS;

        return true;
    }

    public function title(): array|string
    {
        return ['New PM: %s', $this->topic->subject];
    }

    public function text(): array|string
    {
        $link = $this->c->Router->link(
            'PMAction',
            [
                'second' => null,
                'action' => Cnst::ACTION_TOPIC,
                'more1'  => $this->topic->id,
                'more2'  => Cnst::ACTION_NEW,
            ]
        );

        return ['User %1$s sent PM. Located %2$s', $this->sender->username, $link];
    }
}
