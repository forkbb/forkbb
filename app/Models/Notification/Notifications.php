<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Notification;

use ForkBB\Models\Model;
use ForkBB\Models\Notification\NotificationAboutNicknameMentions;
use ForkBB\Models\Post\Post;



use ForkBB\Models\DataModel;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Topic\Topic;
use ForkBB\Models\User\User;
use PDO;
use InvalidArgumentException;

class Notifications extends Model
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Notifications';

    public function notifyAboutNicknameMentions(string $text, Post $post): void
    {
        list($nQuoted, $nMentioned) = $this->c->Parser->findNicknames($text);
        $nicks                      = \array_merge($nQuoted, $nMentioned);

        if (empty($nicks)) {
            return;

        } elseif (\count($nicks) > 50) {
            $this->c->Log->warning('Notifications: many nicknames', [
                'user'  => $this->c->user->fLog(),
                'post'  => $post->link,
                'count' => \count($nicks),
            ]);

            return;
        }

        foreach ($nicks as $nick => $z) {
            $user = $this->c->users->loadByName($nick, true);

            if (null === $user) {
                continue;
            }

            $notification = new NotificationAboutNicknameMentions($this->c);

            if (true === $notification->init([
                'user'      => $user,
                'post'      => $post,
                'quoted'    => isset($nQuoted[$nick]),
                'mentioned' => isset($nMentioned[$nick]),
            ])) {
                $this->add($notification);
            }
        }
    }
}
