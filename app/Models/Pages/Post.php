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
            $this->previewHtml = $this->c->Parser->getHtml();
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
        $this->canonical = $this->c->Router->link('NewReply', $args);
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
        $tid   = (int) $args['id'];
        $topic = $this->c->ModelTopic->load($tid);
        
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
            $this->previewHtml = $this->c->Parser->getHtml();
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
            $lastPost = $this->c->ModelPost->load($topic->last_post_id);

            if ($this->c->MAX_POST_SIZE > mb_strlen($lastPost->message . $v->message, 'UTF-8') + 100) { //????
                $lastPost->message = $lastPost->message . "\n[after=" . ($now - $topic->last_post) . "]\n" . $v->message; //????
                $lastPost->posted  = $lastPost->posted + 1; //???? прибаляем 1 секунду для появления в новых //????

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

    /**
     * Дополнительная проверка email
     * 
     * @param Validator $v
     * @param string $email
     * 
     * @return string
     */
    public function vCheckEmail(Validator $v, $email)
    {
        $user = $this->c->ModelUser;
        $user->email = $email;

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
     * @return string
     */
    public function vCheckUsername(Validator $v, $username)
    {
        $user = $this->c->ModelUser;
        $user->username = $username;

        // username = Гость
        if (preg_match('%^(guest|' . preg_quote(__('Guest'), '%') . ')$%iu', $username)) {
            $v->addError('Username guest');
        // цензура
        } elseif ($user->cens()->username !== $username) {
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
     * @return string
     */
    public function vCheckSubject(Validator $v, $subject, $attr, $executive)
    {
        // после цензуры заголовок темы путой
        if ($this->c->censorship->censor($subject) == '') {
            $v->addError('No subject after censoring');
        // заголовок темы только заглавными буквами
        } elseif (! $executive
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
    public function vCheckMessage(Validator $v, $message, $attr, $executive)
    {
        // после цензуры текст сообщения пустой
        if ($this->c->censorship->censor($message) == '') {
            $v->addError('No message after censoring');
        // текст сообщения только заглавными буквами
        } elseif (! $executive
            && $this->c->config->p_message_all_caps == '0'
            && preg_match('%\p{Lu}%u', $message)
            && ! preg_match('%\p{Ll}%u', $message)
        ) {
            $v->addError('All caps message');
        // проверка парсером
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

            foreach($this->c->Parser->getErrors() as $error) {
                $v->addError($error);
            } 
        }

        return $message;
    }

    /**
     * Подготовка валидатора к проверке данных из формы создания темы/сообщения
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
            if ($editSubject) {
                $ruleStickTopic = 'checkbox';
                $ruleStickFP    = 'checkbox';
                $ruleMergePost  = 'absent';
            } else {
                $ruleStickTopic = 'absent';
                $ruleStickFP    = 'absent';
                $ruleMergePost  = 'checkbox';
            }
            $executive          = true;
        } else {
            $ruleStickTopic     = 'absent';
            $ruleStickFP        = 'absent';
            $ruleMergePost      = 'absent:1';
            $executive          = false;
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
            'message'      => 'required|string:trim|max:' . $this->c->MAX_POST_SIZE . '|check_message',
        ])->setArguments([
            'token'                 => $args,
            'subject.check_subject' => $executive,
            'message.check_message' => $executive,
        ])->setMessages([
            'username.login' => __('Login format'),
        ]);

        return $v;
    }

    /**
     * Возвращает данные для построения формы создания темы/сообщения
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
            if ($editSubject) {
                $fieldset['stick_topic'] = [
                    'type'    => 'checkbox',
                    'label'   => __('Stick topic'),
                    'value'   => '1',
                    'checked' => isset($vars['stick_topic']) ? (bool) $vars['stick_topic'] : false,
                ];
                $fieldset['stick_fp'] = [
                    'type'    => 'checkbox',
                    'label'   => __('Stick first post'),
                    'value'   => '1',
                    'checked' => isset($vars['stick_fp']) ? (bool) $vars['stick_fp'] : false,
                ];
            } else {
                $fieldset['merge_post'] = [
                    'type'    => 'checkbox',
                    'label'   => __('Merge posts'),
                    'value'   => '1',
                    'checked' => isset($vars['merge_post']) ? (bool) $vars['merge_post'] : true,
                ];
            }
        }
        if ($this->c->config->o_smilies == '1') {
            $fieldset['hide_smilies'] = [
                'type'    => 'checkbox',
                'label'   => __('Hide smilies'),
                'value'   => '1',
                'checked' => isset($vars['hide_smilies']) ? (bool) $vars['hide_smilies'] : false,
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
