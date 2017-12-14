<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Post;
use ForkBB\Models\Page;

class Edit extends Page
{
    use CrumbTrait;
    use PostFormTrait;
    use PostValidatorTrait;

    /**
     * Подготовка данных для шаблона редактироания сообщения
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function edit(array $args, Post $post = null)
    {
        $post = $post ?: $this->c->ModelPost->load((int) $args['id']);

        if (empty($post) || ! $post->canEdit) {
            return $this->c->Message->message('Bad request');
        }

        $topic       = $post->parent;
        $editSubject = $post->id === $topic->first_post_id;

        if (! isset($args['_vars'])) {
            $args['_vars'] = [
                'message'      => $post->message,
                'subject'      => $topic->subject,
                'hide_smilies' => $post->hide_smilies,
                'stick_topic'  => $topic->sticky,
                'stick_fp'     => $topic->stick_fp,
                'edit_post'    => $post->edit_post,
            ];
        }

        $this->c->Lang->load('post');
        
        $this->nameTpl   = 'post';
        $this->onlinePos = 'topic-' . $topic->id;
        $this->canonical = $post->linkEdit;
        $this->robots    = 'noindex';
        $this->formTitle = $editSubject ? \ForkBB\__('Edit topic') : \ForkBB\__('Edit post');
        $this->crumbs    = $this->crumbs($this->formTitle, $topic);
        $this->form      = $this->messageForm($post, 'EditPost', $args, true, $editSubject);
                
        return $this;
    }

    /**
     * Обработка данных от формы редактирования сообщения
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function editPost(array $args)
    {
        $post = $this->c->ModelPost->load((int) $args['id']);

        if (empty($post) || ! $post->canEdit) {
            return $this->c->Message->message('Bad request');
        }

        $topic       = $post->parent;
        $editSubject = $post->id === $topic->first_post_id;

        $this->c->Lang->load('post');

        $v = $this->messageValidator($topic, 'EditPost', $args, true, $editSubject);

        if ($v->validation($_POST) && null === $v->preview) {
            return $this->endEdit($post, $v);
        }

        $this->fIswev  = $v->getErrors();
        $args['_vars'] = $v->getData();

        if (null !== $v->preview && ! $v->getErrors()) {
            $this->previewHtml = $this->c->Parser->parseMessage(null, (bool) $v->hide_smilies);
        }

        return $this->edit($args, $post);
    }

    /**
     * Сохранение сообщения
     * 
     * @param Post $post
     * @param Validator $v
     * 
     * @return Page
     */
    protected function endEdit(Post $post, Validator $v)
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
            ->message(\ForkBB\__('Post redirect'));
    }
}
