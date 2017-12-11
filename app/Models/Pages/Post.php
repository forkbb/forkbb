<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Model;
use ForkBB\Models\Forum;
use ForkBB\Models\Topic;
use ForkBB\Models\Page;

class Post extends Page
{
    use CrumbTrait;
    use PostFormTrait;
    use PostValidatorTrait;

    /**
     * Подготовка данных для шаблона создания темы
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function newTopic(array $args, Forum $forum = null)
    {
        $forum = $forum ?: $this->c->forums->forum((int) $args['id']);

        if (empty($forum) || $forum->redirect_url || ! $forum->canCreateTopic) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('post');

        $this->nameTpl   = 'post';
        $this->onlinePos = 'forum-' . $forum->id;
        $this->canonical = $this->c->Router->link('NewTopic', ['id' => $forum->id]);
        $this->robots    = 'noindex';
        $this->crumbs    = $this->crumbs(__('Post new topic'), $forum);
        $this->form      = $this->messageForm($forum, 'NewTopic', $args, true);
        $this->formTitle = __('Post new topic');
        
        return $this;
    }

    /**
     * Обработчка данных от формы создания темы
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function newTopicPost(array $args)
    {
        $forum = $this->c->forums->forum((int) $args['id']);
        
        if (empty($forum) || $forum->redirect_url || ! $forum->canCreateTopic) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('post');

        $v = $this->messageValidator($forum, 'NewTopic', $args, true);

        if ($v->validation($_POST) && null === $v->preview) {
            return $this->endPost($forum, $v);
        }

        $this->fIswev  = $v->getErrors();
        $args['_vars'] = $v->getData();

        if (null !== $v->preview && ! $v->getErrors()) {
            $this->previewHtml = $this->c->Parser->parseMessage(null, (bool) $v->hide_smilies);
        }

        return $this->newTopic($args, $forum);
    }

    /**
     * Подготовка данных для шаблона создания сообщения
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function newReply(array $args, Topic $topic = null)
    {
        $topic = $topic ?: $this->c->ModelTopic->load((int) $args['id']);

        if (empty($topic) || $topic->moved_to || ! $topic->canReply) {
            return $this->c->Message->message('Bad request');
        }

        if (isset($args['quote'])) {
            $post = $this->c->ModelPost->load((int) $args['quote'], $topic);

            if (empty($post)) {
                return $this->c->Message->message('Bad request');
            }

            $message = '[quote="' . $post->poster . '"]' . $post->message . '[/quote]';

            $args['_vars'] = ['message' => $message];
            unset($args['quote']);
        }

        $this->c->Lang->load('post');
        
        $this->nameTpl   = 'post';
        $this->onlinePos = 'topic-' . $topic->id;
        $this->canonical = $this->c->Router->link('NewReply', ['id' => $topic->id]);
        $this->robots    = 'noindex';
        $this->crumbs    = $this->crumbs(__('Post a reply'), $topic);
        $this->form      = $this->messageForm($topic, 'NewReply', $args);
        $this->formTitle = __('Post a reply');
                
        return $this;
    }

    /**
     * Обработка данных от формы создания сообщения
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function newReplyPost(array $args)
    {
        $topic = $this->c->ModelTopic->load((int) $args['id']);
        
        if (empty($topic) || $topic->moved_to || ! $topic->canReply) {
            return $this->c->Message->message('Bad request');
        }
        
        $this->c->Lang->load('post');
                
        $v = $this->messageValidator($topic, 'NewReply', $args);

        if ($v->validation($_POST) && null === $v->preview) {
            return $this->endPost($topic, $v);
        }

        $this->fIswev  = $v->getErrors();
        $args['_vars'] = $v->getData();

        if (null !== $v->preview && ! $v->getErrors()) {
            $this->previewHtml = $this->c->Parser->parseMessage(null, (bool) $v->hide_smilies);
        }

        return $this->newReply($args, $topic);
    }

    /**
     * Создание темы/сообщения
     * 
     * @param Model $model
     * @param Validator $v
     * 
     * @return Page
     */
    protected function endPost(Model $model, Validator $v)
    {
        $now       = time();
        $user      = $this->c->user;
        $username  = $user->isGuest ? $v->username : $user->username;
        $merge     = false;
        $executive = $user->isAdmin || $user->isModerator($model);
        
        // подготовка к объединению/сохранению сообщения
        if (null === $v->subject) {
            $createTopic = false;
            $forum       = $model->parent;
            $topic       = $model;
            
            if (! $user->isGuest && $topic->last_poster === $username) {
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
            $topic       = $this->c->ModelTopic;

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
    
            $topic->insert();
        }

        // попытка объеденить новое сообщение с крайним в теме
        if ($merge) {
            $lastPost  = $this->c->ModelPost->load($topic->last_post_id);
            $newLength = mb_strlen($lastPost->message . $v->message, 'UTF-8');

            if ($newLength < $this->c->MAX_POST_SIZE - 100) {
                $lastPost->message   = $lastPost->message . "\n[after=" . ($now - $topic->last_post) . "]\n" . $v->message; //????
                $lastPost->edited    = $now;
                $lastPost->edited_by = $username;

                $lastPost->update();
            } else {
                $merge = false;
            }
        }
        
        // создание нового сообщения
        if (! $merge) {
            $post = $this->c->ModelPost;
        
            $post->poster       = $username;
            $post->poster_id    = $this->c->user->id;
            $post->poster_ip    = $this->c->user->ip;
            $post->poster_email = $v->email;
            $post->message      = $v->message; //?????
            $post->hide_smilies = $v->hide_smilies ? 1 : 0;
#           $post->edit_post    =
            $post->posted       = $now;
#           $post->edited       =
#           $post->edited_by    =
            $post->user_agent   = $this->c->user->userAgent;
            $post->topic_id     = $topic->id;
        
            $post->insert();
        }

        if ($createTopic) {
            $topic->forum_id      = $forum->id;
            $topic->first_post_id = $post->id;
        }

        // обновление данных в теме и разделе
        $topic->calcStat()->update();
        $forum->calcStat()->update();

        // обновление данных текущего пользователя
        if (! $merge && ! $user->isGuest && $forum->no_sum_mess != '1') {
            $user->num_posts = $user->num_posts + 1;

            if ($user->g_promote_next_group != '0' && $user->num_posts >= $user->g_promote_min_posts) {
                $user->group_id = $user->g_promote_next_group;
            }
        }
        $user->last_post = $now;
        $user->update();
        
        return $this->c->Redirect
            ->page('ViewPost', ['id' => $merge ? $lastPost->id : $post->id])
            ->message(__('Post redirect'));
    }
}
