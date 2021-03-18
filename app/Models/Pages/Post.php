<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Model;
use ForkBB\Models\Page;
use ForkBB\Models\Topic\Model as Topic;
use function \ForkBB\__;

class Post extends Page
{
    use PostFormTrait;
    use PostValidatorTrait;

    /**
     * Создание новой темы
     */
    public function newTopic(array $args, string $method): Page
    {
        $forum = $this->c->forums->get($args['id']);

        if (
            empty($forum)
            || $forum->redirect_url
            || ! $forum->canCreateTopic
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('post');

        if (1 === $this->c->config->b_poll_enabled) {
            $this->c->Lang->load('poll');
        }

        $this->onlinePos = 'forum-' . $forum->id;

        if ('POST' === $method) {
            $v = $this->messageValidator($forum, 'NewTopic', $args, false, true);

            if ($this->user->isGuest) {
                $v = $this->c->Test->beforeValidation($v);
            }

            if (
                $v->validation($_POST, $this->user->isGuest) //????
                && null === $v->preview
                && null !== $v->submit
            ) {
                return $this->endPost($forum, $v);
            }

            $this->fIswev  = $v->getErrors();
            $args['_vars'] = $v->getData();

            if (
                null !== $v->preview
                && ! $v->getErrors()
            ) {
                $this->previewHtml = $this->c->censorship->censor(
                    $this->c->Parser->parseMessage(null, (bool) $v->hide_smilies)
                );

                if (
                    $this->user->usePoll
                    && $v->poll_enable
                ) {
                    $this->poll = $this->c->polls->create($v->poll);
                    $this->c->polls->revision($this->poll, true);
                }
            }
        }

        $this->nameTpl   = 'post';
        $this->canonical = $this->c->Router->link(
            'NewTopic',
            [
                'id' => $forum->id,
            ]
        );
        $this->robots    = 'noindex';
        $this->crumbs    = $this->crumbs(__('Post new topic'), $forum);
        $this->formTitle = __('Post new topic');
        $this->form      = $this->messageForm($forum, 'NewTopic', $args, false, true, false);

        return $this;
    }

    /**
     * Подготовка данных для шаблона создания сообщения
     */
    public function newReply(array $args, string $method): Page
    {
        $topic = $this->c->topics->load($args['id']);

        if (
            ! $topic instanceof Topic
            || ! $topic->canReply
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('post');

        $this->onlinePos = 'topic-' . $topic->id;

        if ('POST' === $method) {
            $v = $this->messageValidator($topic, 'NewReply', $args, false, false);

            if ($this->user->isGuest) {
                $v = $this->c->Test->beforeValidation($v);
            }

            if (
                $v->validation($_POST, $this->user->isGuest) //????
                && null === $v->preview
                && null !== $v->submit
            ) {
                return $this->endPost($topic, $v);
            }

            $this->fIswev  = $v->getErrors();
            $args['_vars'] = $v->getData(); //????

            if (
                null !== $v->preview
                && ! $v->getErrors()
            ) {
                $this->previewHtml = $this->c->Parser->parseMessage(null, (bool) $v->hide_smilies);
            }
        } elseif (isset($args['quote'])) {
            $post = $this->c->posts->load($args['quote'], $topic->id);

            if (empty($post)) {
                return $this->c->Message->message('Bad request');
            }

            $message = '[quote="' . $post->poster . '"]' . $post->message . '[/quote]';

            $args['_vars'] = ['message' => $message]; //????
            unset($args['quote']);
        }

        $this->nameTpl    = 'post';
        $this->canonical  = $this->c->Router->link(
            'NewReply',
            [
                'id' => $topic->id,
            ]
        );
        $this->robots     = 'noindex';
        $this->crumbs     = $this->crumbs(__('Post a reply'), $topic);
        $this->formTitle  = __('Post a reply');
        $this->form       = $this->messageForm($topic, 'NewReply', $args, false, false, false);
        $this->postsTitle = __('Topic review');
        $this->posts      = $topic->review();

        return $this;
    }

    /**
     * Создание темы/сообщения
     */
    protected function endPost(Model $model, Validator $v): Page
    {
        $this->c->Online->calc($this); // для подписок

        $now       = \time();
        $username  = $this->user->isGuest ? $v->username : $this->user->username;
        $merge     = false;
        $executive = $this->user->isAdmin || $this->user->isModerator($model);

        // подготовка к объединению/сохранению сообщения
        if (null === $v->subject) {
            $createTopic = false;
            $forum       = $model->parent;
            $topic       = $model;

            if (
                ! $this->user->isGuest
                && $topic->last_poster_id === $this->user->id
            ) {
                if ($executive) {
                    if ($v->merge_post) {
                        $merge = true;
                    }
                } else {
                    $merge = true;
                }
            }
        // создание темы
        } else {
            $createTopic = true;
            $forum       = $model;
            $topic       = $this->c->topics->create();

            $topic->subject        = $v->subject;
            $topic->poster         = $username;
            $topic->poster_id      = $this->user->id;
            $topic->last_poster    = $username;
            $topic->last_poster_id = $this->user->id;
            $topic->posted         = $now;
            $topic->last_post      = $now;
            $topic->sticky         = $v->stick_topic ? 1 : 0;
            $topic->stick_fp       = $v->stick_fp ? 1 : 0;

            $this->c->topics->insert($topic);
        }

        // попытка объеденить новое сообщение с крайним в теме
        if ($merge) {
            $lastPost  = $this->c->posts->load($topic->last_post_id, $topic->id);
            $newLength = \mb_strlen($lastPost->message . $v->message, 'UTF-8');

            if ($newLength < $this->c->MAX_POST_SIZE - 100) {
                $lastPost->message   = $lastPost->message . "\n[after=" . ($now - $topic->last_post) . "]\n" . $v->message; //????
                $lastPost->edited    = $now;
                $lastPost->editor    = $username;       // ???? может копировать из poster
                $lastPost->editor_id = $this->user->id; // ???? может копировать из poster_id

                $this->c->posts->update($lastPost);
            } else {
                $merge = false;
            }
        }

        // создание нового сообщения
        if (! $merge) {
            $post = $this->c->posts->create();

            $post->poster       = $username;
            $post->poster_id    = $this->user->id;
            $post->poster_ip    = $this->user->ip;
            $post->poster_email = (string) $v->email;
            $post->message      = $v->message; //?????
            $post->hide_smilies = $v->hide_smilies ? 1 : 0;
#           $post->edit_post    =
            $post->posted       = $now;
#           $post->edited       =
#           $post->editor       =
#           $post->editor_id    =
            $post->user_agent   = $this->user->userAgent;
            $post->topic_id     = $topic->id;

            $this->c->posts->insert($post);
        }

        if ($createTopic) {
            $topic->forum_id      = $forum->id;
            $topic->first_post_id = $post->id;

            if (
                $this->user->usePoll
                && $v->poll_enable
            ) {
                $topic->poll_type  = $v->poll['duration'] > 0 ? 1000 + $v->poll['duration'] : 1; // ???? перенести в модель poll?
                $topic->poll_time  = $now;
                $topic->poll_term  = $v->poll['hide_result'] ? $this->c->config->i_poll_term : 0;

                $poll = $this->c->polls->create([
                    'tid'      => $topic->id,
                    'question' => $v->poll['question'],
                    'answer'   => $v->poll['answer'],
                    'type'     => $v->poll['type'],
                ]);

                $this->c->polls->insert($poll);
            }
        }

        // обновление данных в теме и разделе
        $this->c->topics->update($topic->calcStat());
        $this->c->forums->update($forum->calcStat());

        // обновление данных текущего пользователя
        if (
            ! $merge
            && ! $this->user->isGuest
        ) {
            if (0 == $forum->no_sum_mess) {
                $this->user->num_posts = $this->user->num_posts + 1;

                if (
                    0 != $this->user->g_promote_next_group
                    && $this->user->num_posts >= $this->user->g_promote_min_posts
                ) {
                    $this->user->group_id = $this->user->g_promote_next_group;
                }
            }
            if ($createTopic) {
                $this->user->num_topics = $this->user->num_topics + 1;
            }
        }
        $this->user->last_post = $now;
        $this->c->users->update($this->user);

        if ('1' == $this->c->config->o_topic_subscriptions) { // ????
            if ($v->subscribe && ! $topic->is_subscribed) {
                $this->c->subscriptions->subscribe($this->user, $topic);
            } elseif (! $v->subscribe && $topic->is_subscribed) {
                $this->c->subscriptions->unsubscribe($this->user, $topic);
            }
        }

        if ($merge) {
            $this->c->search->index($lastPost, 'merge');
        } else {
            $this->c->search->index($post);

            if ($createTopic) {
                if ('1' == $this->c->config->o_forum_subscriptions) { // ????
                    $this->c->subscriptions->send($post, $topic);
                }
            } else {
                if ('1' == $this->c->config->o_topic_subscriptions) { // ????
                    $this->c->subscriptions->send($post);
                }
            }
        }

        return $this->c->Redirect->page('ViewPost', ['id' => $merge ? $lastPost->id : $post->id])->message('Post redirect');
    }
}
