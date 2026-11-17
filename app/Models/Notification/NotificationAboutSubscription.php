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

    protected string $subject;
    protected string $message;

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

        $this->c->Lang->load('common', $user->language);

        $topic = $post->parent;
        $forum = $topic->parent;

        if ($newTopic) {
            $tplName = 'new_topic.tpl';
            $tplData = [
                'forumName'       => $forum->name,
                'poster'          => $topic->poster,
                'topicSubject'    => $topic->name,
                'topicLink'       => $topic->link,
                'unsubscribeLink' => $forum->link,
                'button'          => __('Unsubscribe'),
            ];

        } else {
            $tplName = 'new_reply.tpl';
            $tplData = [
                'forumName'       => $forum->name,
                'replier'         => $post->poster,
                'topicSubject'    => $topic->name,
                'postLink'        => $post->link,
                'unsubscribeLink' => $topic->link,
                'button'          => __('Unsubscribe'),
            ];
        }

        $file = \rtrim($this->c->DIR_LANG, '\\/') . "/{$user->language}/mail/{$tplName}";

        if (! \is_file($file)) {
            throw new MailException("The template isn't found ({$file}).");
        }

        $tpl = \trim(\file_get_contents($file));
        $tpl = \preg_replace('%[\n\r]+[^\n\r]*?{!fMailer!}.*+$%s', '', $tpl);

        foreach ($tplData as $key => $val) {
            $tpl = \str_replace('{!' . $key . '!}', (string) $val, $tpl);
        }

        if (false !== \strpos($tpl, '{{')) {
            foreach ($data as $key => $val) {
                $tpl = \str_replace('{{' . $key . '}}', e((string) $val), $tpl);
            }
        }

        if (! \preg_match('%^(.+?)(?:\r\n\r\n|\n\n|\r\r)(.+)$%s', $tpl, $matches)) {
            throw new MailException("Unknown format template ({$file}).");
        }

        $subject = null;

        foreach (\preg_split('%\r\n|\n|\r%', $matches[1]) as $line) {
            list($type, $value) = \array_map('\\trim', \explode(':', $line, 2));

            if ('' == $value) {
                throw new MailException("Empty template header: {$type}.");

            } elseif ('Subject' === $type) {
                $subject = $value;
            }
        }

        if (null === $subject) {
            throw new MailException('No template header.');
        }

        $this->subject   = $subject;
        $this->message   = \trim($matches[2]);
        $this->user      = $user;
        $this->localRule = ($user->ntfy_subscr ?? 0) & self::BITS;

        return true;
    }

    public function title(): array|string
    {
        return $this->subject;
    }

    public function text(): array|string
    {
        return $this->message;
    }
}
