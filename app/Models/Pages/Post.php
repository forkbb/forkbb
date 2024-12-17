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
use ForkBB\Models\Draft\Draft;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Topic\Topic;
use function \ForkBB\__;

class Post extends Page
{
    use PostFormTrait;
    use PostValidatorTrait;
    use PostCFTrait;

    protected ?Draft $draft  = null;
    protected string $marker = '';

    /**
     * Обрабатывает черновик
     */
    public function draft(array $args, string $method): Page
    {
        if (
            ! $this->c->userRules->useDraft
            || ! ($draft = $this->c->drafts->load($args['did'])) instanceof Draft
            || $draft->poster_id !== $this->user->id
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->draft          = $draft;
        $this->marker         = 'Draft';

        if ('POST' !== $method) {
            $vars                 = $draft->form_data;
            $vars['subject']      = $draft->subject;
            $vars['message']      = $draft->message;
            $vars['hide_smilies'] = $draft->hide_smilies;
            $args['_vars']        = $vars;
        }

        if ($draft->topic_id > 0)  {
            $args['id'] = $draft->topic_id;
            $result     = $this->newReply($args, $method);
        } else {
            $args['id'] = $draft->forum_id;
            $result     = $this->newTopic($args, $method);
        }

        return $result;
    }

    /**
     * Создание новой темы
     */
    public function newTopic(array $args, string $method): Page
    {
        $forum = $this->c->forums->get($args['id']);

        if (
            ! $forum instanceof Forum
            || $forum->redirect_url
            || ! $forum->canCreateTopic
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('post');

        if (1 === $this->c->config->b_poll_enabled) {
            $this->c->Lang->load('poll');
        }

        $this->onlinePos         = 'forum-' . $forum->id;
        $this->customFieldsLevel = 0;

        if (1 === $forum->use_custom_fields) {
            $forum = $this->c->forums->loadTree($args['id']);

            if (! empty($forum->custom_fields)) {
                $this->customFieldsLevel = 4;
            }
        }

        if ('POST' === $method) {
            $v = $this->messageValidator($forum, $this->marker ?: 'NewTopic', $args, false, true);

            if ($this->customFieldsLevel > 0) {
                $this->addCFtoMessageValidator($forum->custom_fields, $this->customFieldsLevel, $v);
            }

            if (
                $this->user->isGuest
                || empty($this->user->last_post)
            ) {
                $v = $this->c->Test->beforeValidation($v);
            }

            if (
                $v->validation($_POST, $this->user->isGuest) //????
                && null === $v->preview
            ) {
                if (null !== $v->submit) {
                    return $this->endPost($forum, $v);
                } elseif (null !== $v->draft) {
                    return $this->endDraft($forum, $v);
                }
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
                $this->useMediaJS  = true;

                if (
                    $this->userRules->usePoll
                    && $v->poll_enable
                ) {
                    $this->poll = $this->c->polls->create($v->poll);
                    $this->c->polls->revision($this->poll, true);
                }
            }
        }

        $this->identifier = 'post';
        $this->nameTpl    = 'post';
        $this->canonical  = $forum->linkCreateTopic;
        $this->robots     = 'noindex';
        $this->formTitle  = $this->draft instanceof Draft ? '[Draft] Post new topic' : 'Post new topic';
        $this->crumbs     = $this->crumbs($this->formTitle, $forum);
        $this->form       = $this->messageForm($forum, $this->marker ?: 'NewTopic', $args, false, true, false);

        if ($this->customFieldsLevel > 0) {
            $this->form = $this->addCFtoMessageForm($forum->custom_fields, $this->customFieldsLevel, $this->form, $args);
        }

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
            $v = $this->messageValidator($topic, $this->marker ?: 'NewReply', $args, false, false);

            if ($this->user->isGuest) {
                $v = $this->c->Test->beforeValidation($v);
            }

            if (
                $v->validation($_POST, $this->user->isGuest) //????
                && null === $v->preview
            ) {
                if (null !== $v->submit) {
                    return $this->endPost($topic, $v);
                } elseif (null !== $v->draft) {
                    return $this->endDraft($topic, $v);
                }
            }

            $this->fIswev  = $v->getErrors();
            $args['_vars'] = $v->getData(); //????

            if (
                null !== $v->preview
                && ! $v->getErrors()
            ) {
                $this->previewHtml = $this->c->Parser->parseMessage(null, (bool) $v->hide_smilies);
                $this->useMediaJS  = true;
            }
        } elseif (isset($args['quote'])) {
            $post = $this->c->posts->load($args['quote'], $topic->id);

            if (empty($post)) {
                return $this->c->Message->message('Bad request');
            }

            $args['_vars'] = [
                'message' => "[quote=\"{$post->poster}\"]{$post->message}[/quote]",
            ];

            unset($args['quote']);
        }

        $this->c->Parser; // предзагрузка

        $this->identifier = 'post';
        $this->nameTpl    = 'post';
        $this->canonical  = $topic->linkReply;
        $this->robots     = 'noindex';
        $this->formTitle  = $this->draft instanceof Draft ? '[Draft] Post a reply' : 'Post a reply';
        $this->crumbs     = $this->crumbs($this->formTitle, $topic);
        $this->form       = $this->messageForm($topic, $this->marker ?: 'NewReply', $args, false, false, false);
        $this->postsTitle = 'Topic review';
        $this->posts      = $topic->review();

        return $this;
    }

    /**
     * Создает черновик
     */
    protected function endDraft(Model $model, Validator $v): Page
    {
        if ($this->draft instanceof Draft) {
            $draft = $this->draft;
        } else {
            $draft = $this->c->drafts->create();

            if ($model instanceof Forum) {
                $draft->forum_id = $model->id;
            } else {
                $draft->topic_id = $model->id;
            }

            $draft->poster_id = $this->user->id;
        }

        $draft->subject      = $v->subject ?? '';
        $draft->message      = $v->message;
        $draft->hide_smilies = $v->hide_smilies ? 1 : 0;
        $draft->form_data    = $v->getData(false, ['token', 'subject', 'message', 'hide_smilies', 'preview', 'submit', 'draft']);
        $draft->poster_ip    = $this->user->ip;

        if ($this->draft instanceof Draft) {
            $this->c->drafts->update($draft);
        } else {
            $this->c->drafts->insert($draft);

            ++$this->user->num_drafts;
        }

        $this->user->last_post = \time();

        $this->c->users->update($this->user);

        return $this->c->Redirect->url($draft->link)->message('Draft redirect', FORK_MESS_SUCC);
    }

    /**
     * Создает тему/сообщение
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
                && (
                    $topic->first_post_id !== $topic->last_post_id
                    || 0 === $topic->poll_type
                )
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

            if ($this->customFieldsLevel > 0) {
                $topic->cf_data  = $this->setCFData($forum->custom_fields, $this->customFieldsLevel, $v->cf_data);
                $topic->cf_level = $this->setCFLevel($topic->cf_data);
            }

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
            $post->user_agent   = \mb_substr($this->user->userAgent, 0, 255, 'UTF-8');
            $post->topic_id     = $topic->id;

            $this->c->posts->insert($post);
        }

        if ($createTopic) {
            $topic->forum_id      = $forum->id;
            $topic->first_post_id = $post->id;

            if (
                $this->userRules->usePoll
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

        // изменить (возможно!?) оглавление темы
        $topic->addPostToToc($merge ? $lastPost : $post, $merge);

        // обновление данных в теме и разделе
        $this->c->topics->update($topic->calcStat());
        $this->c->forums->update($forum->calcStat());

        // синхронизация вложений
        if ($this->userRules->useUpload) {
            $this->c->attachments->syncWithPost($merge ? $lastPost : $post);
        }

        // удалить черновик и пересчитать их количество
        if ($this->draft instanceof Draft) {
            $this->c->drafts->delete($this->draft);

            $this->user->num_drafts = $this->c->drafts->count();
        }

        // обновление данных текущего пользователя
        if (
            ! $merge
            && ! $this->user->isGuest
        ) {
            if (0 == $forum->no_sum_mess) {
                $this->user->num_posts = $this->user->num_posts + 1;

                if (
                    $this->user->g_promote_next_group > 0
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

        if (1 === $this->c->config->b_topic_subscriptions) { // ????
            if (
                $v->subscribe
                && ! $topic->is_subscribed
            ) {
                $this->c->subscriptions->subscribe($this->user, $topic);
            } elseif (
                ! $v->subscribe
                && $topic->is_subscribed
            ) {
                $this->c->subscriptions->unsubscribe($this->user, $topic);
            }
        }

        if ($merge) {
            $this->c->search->index($lastPost, 'merge');
        } else {
            $this->c->search->index($post);

            if ($createTopic) {
                if (1 === $this->c->config->b_forum_subscriptions) { // ????
                    $this->c->subscriptions->send($post, $topic);
                }
            } else {
                if (1 === $this->c->config->b_topic_subscriptions) { // ????
                    $this->c->subscriptions->send($post);
                }
            }
        }

        return $this->c->Redirect->url($merge ? $lastPost->link : $post->link)->message('Post redirect', FORK_MESS_SUCC);
    }
}
