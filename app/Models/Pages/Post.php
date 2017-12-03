<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Model;
use ForkBB\Models\Page;

class Post extends Page
{
    use CrumbTrait;

    /**
     * Подготовка данных для шаблона
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function newTopic(array $args)
    {
        $forum = $this->c->forums->forum($args['id']);

        // раздел отсутствует в доступных или является ссылкой
        if (empty($forum) || $forum->redirect_url || ! $forum->canCreateTopic) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('post');

        $this->nameTpl   = 'post';
        $this->onlinePos = 'forum-' . $forum->id;
        $this->canonical = $this->c->Router->link('NewTopic', $args);
        $this->robots    = 'noindex';
        $this->crumbs    = $this->crumbs(__('Post new topic'), $forum);
        $this->form      = $this->messageForm($forum, 'NewTopic', $args, true);
        $this->titleForm = __('Post new topic');
        
        return $this;
    }

    public function newTopicPost(array $args)
    {
        $forum = $this->c->forums->forum($args['id']);
        
        // раздел отсутствует в доступных или является ссылкой
        if (empty($forum) || $forum->redirect_url || ! $forum->canCreateTopic) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('post');

        $v = $this->messageValidator($forum, 'NewTopic', $args, true);

        if (! $v->validation($_POST) || isset($v->preview)) {
            $this->fIswev = $v->getErrors();
            $args['_vars'] = $v->getData();
            return $this->newTopic($args);
        }

        $now = time();
        $poster = $v->username ?: $this->c->user->username;

        // создание темы
        $topic = $this->c->ModelTopic;

        $topic->subject     = $v->subject;
        $topic->poster      = $poster;
        $topic->last_poster = $poster;
        $topic->posted      = $now;
        $topic->last_post   = $now;
        $topic->sticky      = $v->stick_topic ? 1 : 0;
        $topic->stick_fp    = $v->stick_fp ? 1 : 0;
#       $topic->poll_type = ;
#       $topic->poll_time = ;
#       $topic->poll_term = ;
#       $topic->poll_kol = ;

        $topic->insert();

        // создание сообщения
        $post = $this->c->ModelPost;

        $post->poster       = $poster;
        $post->poster_id    = $this->c->user->id;
        $post->poster_ip    = $this->c->user->ip;
        $post->poster_email = $v->email;
        $post->message      = $v->message; //?????
        $post->hide_smilies = $v->hide_smilies ? 1 : 0;
#       $post->edit_post    =
        $post->posted       = $now;
#       $post->edited       =
#       $post->edited_by    =
        $post->user_agent   = $this->c->user->userAgent;
        $post->topic_id     = $topic->id;

        $post->insert();

        // обновление созданной темы
        $topic->forum_id      = $forum->id; //????
        $topic->first_post_id = $post->id;
        $topic->last_post_id  = $post->id;

        $topic->update();
        
        $forum->calcStat()->update();
        
        return $this->c->Redirect
            ->page('Topic', ['id' => $topic->id, 'name' => $topic->cens()->subject])
            ->message(__('Post redirect'));
    }

    public function newReply(array $args)
    {
        $topic = $this->c->ModelTopic->load((int) $args['id']);

        if (empty($topic) || $topic->moved_to || ! $topic->canReply) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('post');
        
        $this->nameTpl   = 'post';
        $this->onlinePos = 'topic-' . $topic->id;
        $this->canonical = $this->c->Router->link('NewReply', $args);
        $this->robots    = 'noindex';
        $this->crumbs    = $this->crumbs(__('Post a reply'), $topic);
        $this->form      = $this->messageForm($topic, 'NewReply', $args);
        $this->titleForm = __('Post a reply');
                
        return $this;
    }

    public function newReplyPost(array $args)
    {
        $topic = $this->c->ModelTopic->load((int) $args['id']);
        
        if (empty($topic) || $topic->moved_to || ! $topic->canReply) {
            return $this->c->Message->message('Bad request');
        }
        
        $this->c->Lang->load('post');
                
        $v = $this->messageValidator($topic, 'NewReply', $args);

        if (! $v->validation($_POST) || isset($v->preview)) {
            $this->fIswev = $v->getErrors();
            $args['_vars'] = $v->getData();
            return $this->newReply($args);
        }


        exit('ok');
    }

    /**
     * Дополнительная проверка email
     * 
     * @param Validator $v
     * @param string $email
     * 
     * @return array
     */
    public function vCheckEmail(Validator $v, $email)
    {
        $user = $this->c->ModelUser;
        $user->__email = $email;

        // email забанен
        if ($this->c->bans->isBanned($user) > 0) {
            $v->addError('Banned email');
        }
        return $email;
    }

    /**
     * Дополнительная проверка username
     * 
     * @param Validator $v
     * @param string $username
     * 
     * @return array
     */
    public function vCheckUsername(Validator $v, $username)
    {
        $user = $this->c->ModelUser;
        $user->__username = $username;

        // username = Гость
        if (preg_match('%^(guest|' . preg_quote(__('Guest'), '%') . ')$%iu', $username)) {
            $v->addError('Username guest');
        // цензура
        } elseif ($user->cens()->$username !== $username) {
            $v->addError('Username censor');
        // username забанен
        } elseif ($this->c->bans->isBanned($user) > 0) {
            $v->addError('Banned username');
        }
        return $username;
    }

    /**
     * Дополнительная проверка subject
     * 
     * @param Validator $v
     * @param string $subject
     * 
     * @return array
     */
    public function vCheckSubject(Validator $v, $subject)
    {
        if ($this->c->censorship->censor($subject) == '') {
            $v->addError('No subject after censoring');
        } elseif (! $this->tmpAdmMod
            && $this->c->config->p_subject_all_caps == '0'
            && preg_match('%\p{Lu}%u', $subject)
            && ! preg_match('%\p{Ll}%u', $subject)
        ) {
            $v->addError('All caps subject');
        }
        return $subject;
    }

    /**
     * Дополнительная проверка message
     * 
     * @param Validator $v
     * @param string $message
     * 
     * @return array
     */
    public function vCheckMessage(Validator $v, $message)
    {
        if ($this->c->censorship->censor($message) == '') {
            $v->addError('No message after censoring');
        } elseif (! $this->tmpAdmMod
            && $this->c->config->p_message_all_caps == '0'
            && preg_match('%\p{Lu}%u', $message)
            && ! preg_match('%\p{Ll}%u', $message)
        ) {
            $v->addError('All caps message');
        } else {
            
            $bbWList = $this->c->config->p_message_bbcode == '1' ? null : [];
            $bbBList = $this->c->config->p_message_img_tag == '1' ? [] : ['img'];

            $this->c->Parser->setAttr('isSign', false)
                ->setWhiteList($bbWList)
                ->setBlackList($bbBList)
                ->parse($message, ['strict' => true])
                ->stripEmptyTags(" \n\t\r\v", true);

            if ($this->c->config->o_make_links == '1') {
                $this->c->Parser->detectUrls();
            }

            if ($v->hide_smilies != '1' && $this->c->config->o_smilies == '1') {
                $this->c->Parser->detectSmilies();
            }

            $errors = $this->c->Parser->getErrors();
            if ($errors) {
                foreach($errors as $error) {
                    $v->addError($error);
                } 
            } else {
                $this->parser = $this->c->Parser;
            }
        }

        return $message;
    }

    /**
     * Проверка данных поступивших из формы сообщения
     * 
     * @param Model $model
     * @param string $marker
     * @param array $args
     * @param bool $editSubject
     * 
     * @return Validator
     */
    protected function messageValidator(Model $model, $marker, array $args, $editSubject = false)
    {
        if ($this->c->user->isGuest) {
            $ruleEmail    = ($this->c->config->p_force_guest_email == '1' ? 'required|' : '') . 'string:trim,lower|email|check_email';
            $ruleUsername = 'required|string:trim,spaces|min:2|max:25|login|check_username';
        } else {
            $ruleEmail    = 'absent';
            $ruleUsername = 'absent';
        }

        if ($editSubject) {
            $ruleSubject = 'required|string:trim,spaces|min:1|max:70|check_subject';
        } else {
            $ruleSubject = 'absent';
        }

        if ($this->c->user->isAdmin || $this->c->user->isModerator($model)) {
            $this->tmpAdmMod   = true;
            $ruleStickTopic    = 'checkbox';

            if ($editSubject) {
                $ruleStickFP   = 'checkbox';
                $ruleMergePost = 'absent';
            } else {
                $ruleStickFP   = 'absent';
                $ruleMergePost = 'checkbox';
            }
        } else {
            $ruleStickTopic    = 'absent';
            $ruleStickFP       = 'absent';
            $ruleMergePost     = 'absent';
        }

        if ($this->c->config->o_smilies == '1') {
            $ruleHideSmilies = 'checkbox';
        } else {
            $ruleHideSmilies = 'absent';
        }
            
        $v = $this->c->Validator->addValidators([
            'check_email'    => [$this, 'vCheckEmail'],
            'check_username' => [$this, 'vCheckUsername'],
            'check_subject'  => [$this, 'vCheckSubject'],
            'check_message'  => [$this, 'vCheckMessage'],
        ])->setRules([
            'token'        => 'token:' . $marker,
            'email'        => [$ruleEmail, __('Email')],
            'username'     => [$ruleUsername, __('Username')],
            'subject'      => [$ruleSubject, __('Subject')],
            'stick_topic'  => $ruleStickTopic,
            'stick_fp'     => $ruleStickFP,
            'merge_post'   => $ruleMergePost,
            'hide_smilies' => $ruleHideSmilies,
            'submit'       => 'string', //????
            'preview'      => 'string', //????
            'message'      => 'required|string:trim|max:65536|check_message',
        ])->setArguments([
            'token' => $args,
        ])->setMessages([
            'username.login'    => __('Login format'),
        ]);

        return $v;
    }

    /**
     * Возвращает данные для построения формы сообщения
     * 
     * @param Model $model
     * @param string $marker
     * @param array $args
     * @param bool $editSubject
     * 
     * @return array
     */
    protected function messageForm(Model $model, $marker, array $args, $editSubject = false)
    {
        $vars = isset($args['_vars']) ? $args['_vars'] : null;
        unset($args['_vars']);

        $form = [
            'action' => $this->c->Router->link($marker, $args),
            'hidden' => [
                'token' => $this->c->Csrf->create($marker, $args),
            ],
            'sets'   => [],
            'btns'   => [
                'submit'  => ['submit', __('Submit'), 's'],
                'preview' => ['submit', __('Preview'), 'p'],
            ],
        ];

        $fieldset = [];
        if ($this->c->user->isGuest) {
            $fieldset['username'] = [
                'dl'        => 't1',
                'type'      => 'text',
                'maxlength' => 25,
                'title'     => __('Username'),
                'required'  => true,
                'pattern'   => '^.{2,25}$',
                'value'     => isset($vars['username']) ? $vars['username'] : null,
            ];
            $fieldset['email'] = [
                'dl'        => 't2',
                'type'      => 'text',
                'maxlength' => 80,
                'title'     => __('Email'),
                'required'  => $this->c->config->p_force_guest_email == '1',
                'pattern'   => '.+@.+',
                'value'     => isset($vars['email']) ? $vars['email'] : null,
            ];
        }

        if ($editSubject) {
            $fieldset['subject'] = [
                'type'      => 'text',
                'maxlength' => 70,
                'title'     => __('Subject'),
                'required'  => true,
                'value'     => isset($vars['subject']) ? $vars['subject'] : null,
            ];
        }

        $fieldset['message'] = [
            'type'     => 'textarea',
            'title'    => __('Message'),
            'required' => true,
            'value'    => isset($vars['message']) ? $vars['message'] : null,
            'bb'       => [
                ['link', __('BBCode'), __($this->c->config->p_message_bbcode == '1' ? 'on' : 'off')],
                ['link', __('url tag'), __($this->c->config->p_message_bbcode == '1' && $this->c->user->g_post_links == '1' ? 'on' : 'off')],
                ['link', __('img tag'), __($this->c->config->p_message_bbcode == '1' && $this->c->config->p_message_img_tag == '1' ? 'on' : 'off')],
                ['link', __('Smilies'), __($this->c->config->o_smilies == '1' ? 'on' : 'off')],
            ],
        ];
        $form['sets'][] = [
            'fields' => $fieldset,
        ];

        $fieldset = [];
        if ($this->c->user->isAdmin || $this->c->user->isModerator($model)) {
            $fieldset['stick_topic'] = [
                'type'    => 'checkbox',
                'label'   => __('Stick topic'),
                'value'   => '1',
                'checked' => ! empty($vars['stick_topic']),
            ];
            if ($editSubject) {
                $fieldset['stick_fp'] = [
                    'type'    => 'checkbox',
                    'label'   => __('Stick first post'),
                    'value'   => '1',
                    'checked' => ! empty($vars['stick_fp']),
                ];
            } else {
                $fieldset['merge_post'] = [
                    'type'    => 'checkbox',
                    'label'   => __('Merge posts'),
                    'value'   => '1',
                    'checked' => isset($vars['merge_post']) ? (bool) $vars['merge_post'] : 1,
                ];
            }
        }
        if ($this->c->config->o_smilies == '1') {
            $fieldset['hide_smilies'] = [
                'type'    => 'checkbox',
                'label'   => __('Hide smilies'),
                'value'   => '1',
                'checked' => ! empty($vars['hide_smilies']),
            ];
        }
        if ($fieldset) {
            $form['sets'][] = [
                'legend' => __('Options'),
                'fields' => $fieldset,
            ];
        }

        return $form;
    }
}
