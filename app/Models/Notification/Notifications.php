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
use ForkBB\Models\Notification\Notification;
use ForkBB\Models\Notification\NotificationAboutNicknameMentions;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\PM\PTopic;
use ForkBB\Models\Post\Post;
use ForkBB\Models\User\User;
use function \ForkBB\__;

use ForkBB\Models\DataModel;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Topic\Topic;
use PDO;
use InvalidArgumentException;

class Notifications extends Model
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Notifications';

    protected array $listFM = [];
    protected array $permRF = [];

    /**
     * Статус доступности раздела № $fid для пользователя $user
     */
    public function permReadForum(int $fid, User $user): bool
    {
        $gid = $user->group_id;
        $key = "{$fid}_{$gid}";

        $this->listFM[$gid] ??= $this->c->ForumManager->init($gid);
        $this->permRF[$key] ??= $this->listFM[$gid]->get($fid) instanceof Forum;

        return $this->permRF[$key];
    }

    /**
     * Создает уведоления о встречающихся в $text никнеймах пользователей
     * $text либо совпадает с, либо является частью сообщения $post
     */
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

        $unique = [];

        foreach ($nicks as $nick => $z) {
            $user = $this->c->users->loadByName($nick, true);

            if (null === $user) {
                continue;
            }

            if (isset($unique[$user->id])) {
                if (isset($nQuoted[$nick])) {
                    $unique[$user->id]['quoted'] = true;
                }

                if (isset($nMentioned[$nick])) {
                    $unique[$user->id]['mentioned'] = true;
                }

            } else {
                $unique[$user->id] = [
                    'user'      => $user,
                    'quoted'    => isset($nQuoted[$nick]),
                    'mentioned' => isset($nMentioned[$nick]),
                ];
            }
        }

        foreach ($unique as $cur) {
            $notification = new NotificationAboutNicknameMentions($this->c);

            if (true === $notification->init([
                'user'      => $cur['user'],
                'post'      => $post,
                'quoted'    => $cur['quoted'],
                'mentioned' => $cur['mentioned'],
            ])) {
                $this->add($notification);
            }
        }
    }

    /**
     * Добавляет (отправляет) уведоление
     */
    public function add(Notification $notification): bool
    {
        if ($notification->user()->id === $this->c->user->id) {
            return false;
        }

        return $this->addPM($notification);
    }

    protected function addPM(Notification $notification): bool
    {
        $rName = '[Notification bot]';
        $robot = $this->c->users->guest(['username' => $rName]);
        $user  = $notification->user();

        $this->c->Lang->load('notification', $user->language);

        list($idsNew, $idsCur, $idsArc, $totalNew, $totalCur, $totalArc) = $this->c->pms->infoForUser($user, "\"{$rName}\"");

        if (\count($idsCur) > 0) {
            $this->c->pms->idsCurrent = $idsCur; // ???? это костыль для загрузки диалога

            $topic = $this->c->pms->load(Cnst::PTOPIC, \array_key_first($idsCur));
            $new   = false;

        } else {
            $topic = $this->c->pms->create(Cnst::PTOPIC);
            $new   = true;

            $topic->sender        = $robot;
            $topic->recipient     = $user;
            $topic->subject       = __('Notification subject title');
            $topic->poster_status = Cnst::PT_NORMAL;
            $topic->target_status = Cnst::PT_NORMAL;

            $this->c->pms->insert(Cnst::PTOPIC, $topic);
        }

        $post               = $this->c->pms->create(Cnst::PPOST);
        $post->poster       = $robot->username;
        $post->poster_id    = $robot->id;
        $post->poster_ip    = '0.0.0.0';
        $post->message      = __($notification->title()) . "\n" . __($notification->text());
        $post->hide_smilies = 0;
        $post->posted       = \time();
        $post->topic_id     = $topic->id;

        $this->c->pms->insert(Cnst::PPOST, $post);

        if (true === $new) {
            $topic->first_post_id = $post->id;
        }

        $user->u_pm_flash = 1;

        $this->c->pms->update(Cnst::PTOPIC, $topic->calcStat());
        $this->c->pms->recalculate($user);

        if ( // ???? рассылка почты
            1 === $user->u_pm_notify
            && 1 === $user->email_confirmed
            && true !== $user->isBanByName
            && ! $this->c->Online->isOnline($user)
        ) {
            try {
                $this->c->Lang->load('common', $user->language);

                $tplData = [
                    'fTitle'     => $this->c->config->o_board_title,
                    'fMailer'    => __(['Mailer', $this->c->config->o_board_title]),
                    'pmSubject'  => $topic->subject,
                    'username'   => $user->username,
                    'sender'     => $robot->username,
                    'messageUrl' => $new ? $topic->link : $post->link,
                ];

                $this->c->Mail
                    ->reset()
                    ->setMaxRecipients(1)
                    ->setFolder($this->c->DIR_LANG)
                    ->setLanguage($user->language)
                    ->setTo($user->email, $user->username)
                    ->setFrom($this->c->config->o_webmaster_email, $tplData['fMailer'])
                    ->setTpl('new_pm.tpl', $tplData)
                    ->send();

            } catch (MailException $e) {
                $this->c->Log->error('Notification (PM): MailException', [
                    'exception' => $e,
                    'headers'   => false,
                ]);
            }

            $this->c->Lang->load('common', $this->c->user->language);
        }

        return true;
    }
}
