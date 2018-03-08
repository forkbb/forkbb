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
        $user = $this->c->users->create();
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
        $user = $this->c->users->create();
        $user->username = $username;

        // username = Гость
        if (\preg_match('%^(guest|' . \preg_quote(\ForkBB\__('Guest'), '%') . ')$%iu', $username)) { // ???? а зачем?
            $v->addError('Username guest');
        // цензура
        } elseif (\ForkBB\cens($user->username) !== $username) {
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
        if (\ForkBB\cens($subject) == '') {
            $v->addError('No subject after censoring');
        // заголовок темы только заглавными буквами
        } elseif (! $executive
            && '0' == $this->c->config->p_subject_all_caps
            && \preg_match('%\p{Lu}%u', $subject)
            && ! \preg_match('%\p{Ll}%u', $subject)
        ) {
            $v->addError('All caps subject');
        } elseif (! $executive
            && '1' != $this->user->g_post_links
            && \preg_match('%https?://|www\.%ui', $subject)
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
        if (\ForkBB\cens($message) == '') {
            $v->addError('No message after censoring');
        // текст сообщения только заглавными буквами
        } elseif (! $executive
            && '0' == $this->c->config->p_message_all_caps
            && \preg_match('%\p{Lu}%u', $message)
            && ! \preg_match('%\p{Ll}%u', $message)
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

        $time = \time() - (int) $this->user->last_post;

        if ($time < $this->user->g_post_flood) {
            $v->addError(\ForkBB\__('Flood start', $this->user->g_post_flood, $this->user->g_post_flood - $time), 'e');
        }

        return $submit;
    }

    /**
     * Подготовка валидатора к проверке данных из формы создания темы/сообщения
     *
     * @param Model $model
     * @param string $marker
     * @param array $args
     * @param bool $editPost
     * @param bool $editSubject
     *
     * @return Validator
     */
    protected function messageValidator(Model $model, $marker, array $args, $editPost = false, $editSubject = false)
    {
        if ($this->user->isGuest) {
            $ruleEmail    = ('1' == $this->c->config->p_force_guest_email ? 'required|' : '') . 'string:trim,lower|email|check_email';
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

        if ($this->user->isAdmin || $this->user->isModerator($model)) {
            if ($editSubject) {
                $ruleStickTopic = 'checkbox';
                $ruleStickFP    = 'checkbox';
            } else {
                $ruleStickTopic = 'absent';
                $ruleStickFP    = 'absent';
            }
            if (! $editSubject && ! $editPost) {
                $ruleMergePost  = 'checkbox';
            } else {
                $ruleMergePost  = 'absent';
            }
            if ($editPost) {
                $ruleEditPost   = 'checkbox';
            } else {
                $ruleEditPost   = 'absent';
            }
            $executive          = true;
        } else {
            $ruleStickTopic     = 'absent';
            $ruleStickFP        = 'absent';
            $ruleMergePost      = 'absent:1';
            $ruleEditPost       = 'absent';
            $executive          = false;
        }

        if ('1' == $this->c->config->o_smilies) {
            $ruleHideSmilies = 'checkbox';
        } else {
            $ruleHideSmilies = 'absent';
        }

        $v = $this->c->Validator->reset()
            ->addValidators([
                'check_email'    => [$this, 'vCheckEmail'],
                'check_username' => [$this, 'vCheckUsername'],
                'check_subject'  => [$this, 'vCheckSubject'],
                'check_message'  => [$this, 'vCheckMessage'],
                'check_timeout'  => [$this, 'vCheckTimeout'],
            ])->addRules([
                'token'        => 'token:' . $marker,
                'email'        => $ruleEmail,
                'username'     => $ruleUsername,
                'subject'      => $ruleSubject,
                'stick_topic'  => $ruleStickTopic,
                'stick_fp'     => $ruleStickFP,
                'merge_post'   => $ruleMergePost,
                'hide_smilies' => $ruleHideSmilies,
                'edit_post'    => $ruleEditPost,
                'preview'      => 'string',
                'submit'       => 'string|check_timeout',
                'message'      => 'required|string:trim|max:' . $this->c->MAX_POST_SIZE . '|check_message',
            ])->addAliases([
                'email'        => 'Email',
                'username'     => 'Username',
                'subject'      => 'Subject',
            ])->addArguments([
                'token'                 => $args,
                'subject.check_subject' => $executive,
                'message.check_message' => $executive,
            ])->addMessages([
                'username.login' => 'Login format',
            ]);

        return $v;
    }
}
