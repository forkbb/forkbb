<?php

declare(strict_types=1);

namespace ForkBB\Models\Subscription;

use ForkBB\Models\Method;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Post\Model as Post;
use ForkBB\Models\Topic\Model as Topic;
use ForkBB\Core\Exceptions\MailException;
use function \ForkBB\__;

class Send extends Method
{
    /**
     * Рассылает письма по подпискам для новых тем/ответов
     */
    public function send(Post $post, Topic $topic = null)
    {
        try {
            if (null === $topic) {
                $newTopic     = false;
                $tplNameFull  = 'new_reply_full.tpl';
                $tplNameShort = 'new_reply.tpl';
                $topic        = $post->parent;
                $forum        = $topic->parent;

                $vars = [
                    ':uid'  => $this->c->user->id,
                    ':tid'  => $topic->id,
                    ':prev' => $this->c->posts->previousPost($post, false),
                ];
                $query = 'SELECT u.id, u.username, u.group_id, u.email, u.email_confirmed, u.notify_with_post, u.language
                    FROM ::users AS u
                    INNER JOIN ::topic_subscriptions AS s ON u.id=s.user_id
                    LEFT JOIN ::online AS o ON u.id=o.user_id
                    WHERE s.topic_id=?i:tid AND u.id!=?i:uid AND COALESCE(o.logged, u.last_visit)>?i:prev';
            } else {
                $newTopic     = true;
                $tplNameFull  = 'new_topic_full.tpl';
                $tplNameShort = 'new_topic.tpl';
                $forum        = $topic->parent;

                $vars = [
                    ':uid' => $this->c->user->id,
                    ':fid' => $forum->id,
                ];
                $query = 'SELECT u.id, u.username, u.group_id, u.email, u.email_confirmed, u.notify_with_post, u.language
                    FROM ::users AS u
                    INNER JOIN ::forum_subscriptions AS s ON u.id=s.user_id
                    WHERE s.forum_id=?i:fid AND u.id!=?i:uid';
            }

            $data   = [];
            $grPerm = [
                $this->c->user->group_id => true,
            ];

            $stmt = $this->c->DB->query($query, $vars);

            while ($row = $stmt->fetch()) {
                $user = $this->c->users->create($row);

                if (
                    1 !== $user->email_confirmed
                    || $this->c->bans->banFromName($user->username) > 0
                    || $this->c->Online->isOnline($user)
                ) {
                    continue;
                }

                if (! isset($grPerm[$user->group_id])) {
                    $group                   = $this->c->groups->get($user->group_id);
                    $grPerm[$user->group_id] = $this->c->ForumManager->init($group)->get($forum->id) instanceof Forum;
                }

                if (! $grPerm[$user->group_id]) {
                    continue;
                }

                if (empty($data[$user->language])) {
                    $data[$user->language] = [
                        'short' => [],
                        'full'  => [],
                    ];
                }

                $type                                       = 1 === $user->notify_with_post ? 'full' : 'short';
                $data[$user->language][$type][$user->email] = $user->username;
            }

            foreach ($data as $lang => $dataLang) {
                $this->c->Lang->load('common', $lang);

                if ($newTopic) {
                    $tplData = [
                        'forumName'       => $forum->name,
                        'poster'          => $topic->poster,
                        'topicSubject'    => $topic->name,
                        'topicLink'       => $topic->link,
                        'unsubscribeLink' => $forum->link,
                        'button'          => __('Unsubscribe'),
                        'fMailer'         => __('Mailer', $this->c->config->o_board_title),
                    ];
                } else {
                    $tplData = [
                        'forumName'       => $forum->name,
                        'replier'         => $post->poster,
                        'topicSubject'    => $topic->name,
                        'postLink'        => $post->link,
                        'unsubscribeLink' => $topic->link,
                        'button'          => __('Unsubscribe'),
                        'fMailer'         => __('Mailer', $this->c->config->o_board_title),
                    ];
                }

                if (! empty($dataLang['short'])) {
                    $this->c->Mail
                        ->reset()
                        ->setMaxRecipients((int) $this->c->config->i_email_max_recipients)
                        ->setFolder($this->c->DIR_LANG)
                        ->setLanguage($lang)
                        ->setFrom($this->c->config->o_webmaster_email, $tplData['fMailer'])
                        ->setTpl($tplNameShort, $tplData);

                    foreach ($dataLang['short'] as $email => $name) {
                        $this->c->Mail->addTo($email, $name);
                    }

                    $this->c->Mail->send();
                }

                if (! empty($dataLang['full'])) {
                    // $message = $this->c->Parser->prepare($post->message); // парсер хранит сообщение
                    $tplData['message'] = $this->c->Parser->parseMessage(null, (bool) $post->hide_smilies);

                    $this->c->Mail
                        ->reset()
                        ->setMaxRecipients((int) $this->c->config->i_email_max_recipients)
                        ->setFolder($this->c->DIR_LANG)
                        ->setLanguage($lang)
                        ->setFrom($this->c->config->o_webmaster_email, $tplData['fMailer'])
                        ->setTpl($tplNameFull, $tplData);

                    foreach ($dataLang['full'] as $email => $name) {
                        $this->c->Mail->addTo($email, $name);
                    }

                    $this->c->Mail->send();
                }
            }

            $this->c->Lang->load('common', $this->c->user->language);
        } catch (MailException $e) {
            // ????
        }
    }
}
