<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Model;
use ForkBB\Models\Page;
use ForkBB\Models\Topic\Model as Topic;

class Post extends Page
{
    use PostFormTrait;
    use PostValidatorTrait;

    /**
     * Создание новой темы
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function newTopic(array $args, string $method): Page
    {
        $forum = $this->c->forums->get((int) $args['id']);

        if (empty($forum) || $forum->redirect_url || ! $forum->canCreateTopic) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('post');

        if ('POST' === $method) {
            $v = $this->messageValidator($forum, 'NewTopic', $args, false, true);

            if ($v->validation($_POST) && null === $v->preview) {
                return $this->endPost($forum, $v);
            }

            $this->fIswev  = $v->getErrors();
            $args['_vars'] = $v->getData(); //????

            if (null !== $v->preview && ! $v->getErrors()) {
                $this->previewHtml = $this->c->Parser->parseMessage(null, (bool) $v->hide_smilies);
            }
        }

        $this->nameTpl   = 'post';
        $this->onlinePos = 'forum-' . $forum->id;
        $this->canonical = $this->c->Router->link('NewTopic', ['id' => $forum->id]);
        $this->robots    = 'noindex';
        $this->crumbs    = $this->crumbs(\ForkBB\__('Post new topic'), $forum);
        $this->formTitle = \ForkBB\__('Post new topic');
        $this->form      = $this->messageForm($args, $forum, 'NewTopic', false, true);

        return $this;
    }

    /**
     * Подготовка данных для шаблона создания сообщения
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function newReply(array $args, string $method): Page
    {
        $topic = $this->c->topics->load((int) $args['id']);

        if (empty($topic) || $topic->moved_to || ! $topic->canReply) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('post');

        if ('POST' === $method) {
            $v = $this->messageValidator($topic, 'NewReply', $args);

            if ($v->validation($_POST) && null === $v->preview) {
                return $this->endPost($topic, $v);
            }

            $this->fIswev  = $v->getErrors();
            $args['_vars'] = $v->getData(); //????

            if (null !== $v->preview && ! $v->getErrors()) {
                $this->previewHtml = $this->c->Parser->parseMessage(null, (bool) $v->hide_smilies);
            }
        } elseif (isset($args['quote'])) {
            $post = $this->c->posts->load((int) $args['quote'], $topic->id);

            if (empty($post)) {
                return $this->c->Message->message('Bad request');
            }

            $message = '[quote="' . $post->poster . '"]' . $post->message . '[/quote]';

            $args['_vars'] = ['message' => $message]; //????
            unset($args['quote']);
        }

        $this->nameTpl    = 'post';
        $this->onlinePos  = 'topic-' . $topic->id;
        $this->canonical  = $this->c->Router->link('NewReply', ['id' => $topic->id]);
        $this->robots     = 'noindex';
        $this->crumbs     = $this->crumbs(\ForkBB\__('Post a reply'), $topic);
        $this->formTitle  = \ForkBB\__('Post a reply');
        $this->form       = $this->messageForm($args, $topic, 'NewReply');
        $this->postsTitle = \ForkBB\__('Topic review');
        $this->posts      = $topic->review();

        return $this;
    }

    /**
     * Создание темы/сообщения
     *
     * @param Model $model
     * @param Validator $v
     *
     * @return Page
     */
    protected function endPost(Model $model, Validator $v): Page
    {
        $now       = \time();
        $username  = $this->user->isGuest ? $v->username : $this->user->username;
        $merge     = false;
        $executive = $this->user->isAdmin || $this->user->isModerator($model);

        // подготовка к объединению/сохранению сообщения
        if (null === $v->subject) {
            $createTopic = false;
            $forum       = $model->parent;
            $topic       = $model;

            if (! $this->user->isGuest && $topic->last_poster === $username) {
                if ($executive) {
                    if ($v->merge_post) {
                        $merge = true;
                    }
                } else {
                    if ($this->c->config->o_merge_timeout > 0 // ???? стоит завязать на время редактирование сообщений?
                        && $now - $topic->last_post < $this->c->config->o_merge_timeout
                    ) {
                        $merge = true;
                    }
                }
            }
        // создание темы
        } else {
            $createTopic = true;
            $forum       = $model;
            $topic       = $this->c->topics->create();

            $topic->subject     = $v->subject;
            $topic->poster      = $username;
            $topic->last_poster = $username;
            $topic->posted      = $now;
            $topic->last_post   = $now;
            $topic->sticky      = $v->stick_topic ? 1 : 0;
            $topic->stick_fp    = $v->stick_fp ? 1 : 0;
#           $topic->poll_type   = ;
#           $topic->poll_time   = ;
#           $topic->poll_term   = ;
#           $topic->poll_kol    = ;

            $this->c->topics->insert($topic);
        }

        // попытка объеденить новое сообщение с крайним в теме
        if ($merge) {
            $lastPost  = $this->c->posts->load($topic->last_post_id, $topic->id);
            $newLength = \mb_strlen($lastPost->message . $v->message, 'UTF-8');

            if ($newLength < $this->c->MAX_POST_SIZE - 100) {
                $lastPost->message   = $lastPost->message . "\n[after=" . ($now - $topic->last_post) . "]\n" . $v->message; //????
                $lastPost->edited    = $now;
                $lastPost->edited_by = $username;

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
#           $post->edited_by    =
            $post->user_agent   = $this->user->userAgent;
            $post->topic_id     = $topic->id;

            $this->c->posts->insert($post);
        }

        if ($createTopic) {
            $topic->forum_id      = $forum->id;
            $topic->first_post_id = $post->id;
        }

        // обновление данных в теме и разделе
        $this->c->topics->update($topic->calcStat());
        $this->c->forums->update($forum->calcStat());

        // обновление данных текущего пользователя
        if (! $merge && ! $this->user->isGuest) {
            if ($forum->no_sum_mess != '1') {
                $this->user->num_posts = $this->user->num_posts + 1;

                if (0 != $this->user->g_promote_next_group && $this->user->num_posts >= $this->user->g_promote_min_posts) {
                    $this->user->group_id = $this->user->g_promote_next_group;
                }
            }
            if ($createTopic) {
                $this->user->num_topics = $this->user->num_topics + 1;
            }
        }
        $this->user->last_post = $now;
        $this->c->users->update($this->user);

        if ($merge) {
            $this->c->search->index($lastPost, 'merge');
        } else {
            $this->c->search->index($post);
        }

        return $this->c->Redirect->page('ViewPost', ['id' => $merge ? $lastPost->id : $post->id])->message('Post redirect');
    }
}
