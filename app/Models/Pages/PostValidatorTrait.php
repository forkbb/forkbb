<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Model;

trait PostValidatorTrait 
{
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
        } elseif (! $executive
            && $this->c->user->g_post_links != '1'
            && preg_match('%https?://|www\.%ui', $subject)
        ) {
            $v->addError('You can not post links in subject');
        }
        return $subject;
    }

    /**
     * Дополнительная проверка message
     * 
     * @param Validator $v
     * @param string $message
     * 
     * @return string
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
            $message = $this->c->Parser->prepare($message); //????

            foreach($this->c->Parser->getErrors() as $error) {
                $v->addError($error);
            } 
        }

        return $message;
    }

    /**
     * Проверка времени ограничения флуда
     * 
     * @param Validator $v
     * @param null|string $submit
     * 
     * @return null|string
     */
    public function vCheckTimeout(Validator $v, $submit)
    {
        if (null === $submit) {
            return null;
        }

        $user = $this->c->user;
        $time = time() - (int) $user->last_post;

        if ($time < $user->g_post_flood) {
            $v->addError(__('Flood start', $user->g_post_flood, $user->g_post_flood - $time), 'e');
        }

        return $submit;
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
            'check_timeout'  => [$this, 'vCheckTimeout'],
        ])->setRules([
            'token'        => 'token:' . $marker,
            'email'        => [$ruleEmail, __('Email')],
            'username'     => [$ruleUsername, __('Username')],
            'subject'      => [$ruleSubject, __('Subject')],
            'stick_topic'  => $ruleStickTopic,
            'stick_fp'     => $ruleStickFP,
            'merge_post'   => $ruleMergePost,
            'hide_smilies' => $ruleHideSmilies,
            'preview'      => 'string',
            'submit'       => 'string|check_timeout',
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
}
