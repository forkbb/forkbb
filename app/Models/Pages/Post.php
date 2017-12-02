<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
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
        if (empty($forum) || $forum->redirect_url) {
            return $this->c->Message->message('Bad request');
        }

        $user = $this->c->user;

        if (! $user->isAdmin
            && (null === $forum->post_topics && $user->g_post_topics == '0' || $forum->post_topics == '0')
            && ! $user->isModerator($forum)
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('post');

        $this->nameTpl   = 'post';
        $this->onlinePos = 'forum-' . $forum->id;
        $this->canonical = $this->c->Router->link('NewTopic', $args);
        $this->robots    = 'noindex';
        $this->crumbs    = $this->crumbs(__('Post new topic'), $forum);
        $this->form      = $this->messageForm($forum, 'NewTopic', $args, true);
        
        return $this;
    }

    public function newTopicPost(array $args)
    {
        $this->c->Lang->load('post');

        if ($this->c->user->isGuest) {
            $ruleEmail    = ($this->c->config->p_force_guest_email == '1' ? 'required|' : '') . 'string:trim,lower|email|check_email';
            $ruleUsername = 'required|string:trim,spaces|min:2|max:25|login|check_username';
        } else {
            $ruleEmail    = 'absent';
            $ruleUsername = 'absent';
        }
            
        $v = $this->c->Validator->addValidators([
            'check_email'    => [$this, 'vCheckEmail'],
            'check_username' => [$this, 'vCheckUsername'],
            'check_subject'  => [$this, 'vCheckSubject'],
        ])->setRules([
            'token'    => 'token:NewTopic',
            'message'  => 'required|string:trim|max:65536',
            'email'    => [$ruleEmail, __('Email')],
            'username' => [$ruleUsername, __('Username')],
            'subject'  => ['required|string:trim,spaces|min:1|max:70|check_subject', __('Subject')],
        ])->setArguments([
            'token' => $args,
        ])->setMessages([
            'username.login'    => __('Login format'),
        ]);

        if (! $v->validation($_POST)) {
            $this->fIswev = $v->getErrors();
            $args['_vars'] = $v->getData();
            return $this->newTopic($args);
        }


        exit('ok');
    }

    public function newReply(array $args)
    {
        $topic = $this->c->ModelTopic->load($args['id']); //????

        if (empty($topic->id) || $topic->moved_to || ! $topic->canReply) { //????
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('post');
        
        $this->nameTpl   = 'post';
        $this->onlinePos = 'topic-' . $topic->id;
        $this->canonical = $this->c->Router->link('NewReply', $args);
        $this->robots    = 'noindex';
        $this->crumbs    = $this->crumbs(__('Post a reply'), $topic);
        $this->form      = $this->messageForm($topic, 'NewReply', $args);
                
        return $this;
    }

    public function newReplyPost(array $args)
    {
        
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
        $error = false;
        $user = $this->c->ModelUser;
        $user->__email = $email;

        // email забанен
        if ($this->c->bans->isBanned($user) > 0) {
            $error = __('Banned email');
        }
        return [$email, $error];
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
        $error = false;
        $user = $this->c->ModelUser;
        $user->__username = $username;

        // username = Гость
        if (preg_match('%^(guest|' . preg_quote(__('Guest'), '%') . ')$%iu', $username)) {
            $error = __('Username guest');
        // цензура
        } elseif ($user->cens()->$username !== $username) {
            $error = __('Username censor');
        // username забанен
        } elseif ($this->c->bans->isBanned($user) > 0) {
            $error = __('Banned username');
        }
        return [$username, $error];
    }

    /**
     * Дополнительная проверка subject
     * 
     * @param Validator $v
     * @param string $username
     * 
     * @return array
     */
    public function vCheckSubject(Validator $v, $subject)
    {
        $error = false;
        if ($this->c->censorship->censor($subject) == '') {
            $error = __('No subject after censoring');
        } elseif ($this->c->config->p_subject_all_caps == '0' 
            && mb_strtolower($subject, 'UTF-8') !== $subject
            && mb_strtoupper($subject, 'UTF-8') === $subject
        ) {
            $error = __('All caps subject');
        }
        return [$subject, $error];
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
    protected function messageForm($model, $marker, array $args, $editSubject = false)
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
