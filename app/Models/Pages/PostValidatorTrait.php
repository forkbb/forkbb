<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Model;

trait PostValidatorTrait
{
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
        if ('' == \ForkBB\cens($subject)) {
            $v->addError('No subject after censoring');
        // заголовок темы только заглавными буквами
        } elseif (
            ! $executive
            && '0' == $this->c->config->p_subject_all_caps
            && \preg_match('%\p{Lu}%u', $subject)
            && ! \preg_match('%\p{Ll}%u', $subject)
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
     * @return string
     */
    public function vCheckMessage(Validator $v, $message, $attr, $executive)
    {
        // после цензуры текст сообщения пустой
        if ('' == \ForkBB\cens($message)) {
            $v->addError('No message after censoring');
        // текст сообщения только заглавными буквами
        } elseif (
            ! $executive
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
    protected function messageValidator(Model $model, string $marker, array $args, bool $editPost = false, bool $editSubject = false): Validator
    {
        if ($this->user->isGuest) {
            $ruleEmail    = ('1' == $this->c->config->p_force_guest_email ? 'required|' : '') . 'string:trim|email:noban';
            $ruleUsername = 'required|string:trim,spaces|username';
        } else {
            $ruleEmail    = 'absent';
            $ruleUsername = 'absent';
        }

        if (
            $this->user->isAdmin
            || $this->user->isModerator($model)
        ) {
            if ($editSubject) {
                $ruleStickTopic = 'checkbox';
                $ruleStickFP    = 'checkbox';
            } else {
                $ruleStickTopic = 'absent';
                $ruleStickFP    = 'absent';
            }
            if (
                ! $editSubject
                && ! $editPost
            ) {
                $ruleMergePost  = 'checkbox';
            } else {
                $ruleMergePost  = 'absent';
            }
            if (
                $editPost
                && ! $model->user->isGuest
                && ! $model->user->isAdmin
            ) {
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

        if ($editSubject) {
            $ruleSubject = 'required|string:trim,spaces|min:1|max:70|' . ($executive ? '' : 'noURL|') . 'check_subject';
        } else {
            $ruleSubject = 'absent';
        }

        if ('1' == $this->c->config->o_smilies) {
            $ruleHideSmilies = 'checkbox';
        } else {
            $ruleHideSmilies = 'absent';
        }

        $v = $this->c->Validator->reset()
            ->addValidators([
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
                'email.email'           => $this->user,
            ])->addMessages([
                'username.login' => 'Login format',
            ]);

        return $v;
    }
}
