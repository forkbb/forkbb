<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Post\Model as Post;
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
    public function edit(array $args)
    {
        $post = $this->c->posts->load((int) $args['id']);

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
        $post = $this->c->posts->load((int) $args['id']);

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
        $now         = time();
        $user        = $this->c->user;
        $executive   = $user->isAdmin || $user->isModerator($post);
        $topic       = $post->parent;
        $editSubject = $post->id === $topic->first_post_id;
        $calcPost    = false;
        $calcTopic   = false;
        $calcForum   = false;

        // текст сообщения
        if ($post->message !== $v->message) {
            $post->message       = $v->message;
            $post->edited        = $now;
            $post->edited_by     = $user->username;
            $calcPost            = true;
            if ($post->id === $topic->last_post_id) {
                $calcTopic       = true;
                $calcForum       = true;
            }
        }
        // показ смайлов
        if ($this->c->config->o_smilies == '1' && (bool) $post->hide_smilies !== (bool) $v->hide_smilies ) {
            $post->hide_smilies  = $v->hide_smilies ? 1 : 0;
        }
        // редактирование без ограничений
        if ($executive && (bool) $post->edit_post !== (bool) $v->edit_post) {
            $post->edit_post     = $v->edit_post ? 1 : 0;
        }

        if ($editSubject) {
            // заголовок темы
            if ($topic->subject !== $v->subject) {
                $topic->subject  = $v->subject;
                $post->edited    = $now;
                $post->edited_by = $user->username;
                $calcForum       = true;
            }
            // выделение темы
            if ($executive && (bool) $topic->sticky !== (bool) $v->stick_topic) {
                $topic->sticky   = $v->stick_topic ? 1 : 0;
            }
            // закрепление первого сообшения
            if ($executive && (bool) $topic->stick_fp !== (bool) $v->stick_fp) {
                $topic->stick_fp = $v->stick_fp ? 1 : 0;
            }
        }

        // обновление сообщения
        $this->c->posts->update($post);

        // обновление темы
        if ($calcTopic) {
            $topic->calcStat();
        }
        $this->c->topics->update($topic);

        // обновление раздела
        if ($calcForum) {
            $topic->parent->calcStat();
        }
        $this->c->forums->update($topic->parent);
        
        // антифлуд 
        if ($calcPost || $calcForum) { 
            $user->last_post = $now; //????
            $user->update();
        }
        
        return $this->c->Redirect
            ->page('ViewPost', ['id' => $post->id])
            ->message(\ForkBB\__('Edit redirect'));
    }
}
