<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Model;
use function \ForkBB\__;

trait PostValidatorTrait
{
    /**
     * Дополнительная проверка subject
     */
    public function vCheckSubject(Validator $v, $subject, $attr, $executive)
    {
        // после цензуры заголовок темы путой
        if ('' == $this->c->censorship->censor($subject)) {
            $v->addError('No subject after censoring');
        // заголовок темы только заглавными буквами
        } elseif (
            ! $executive
            && 1 !== $this->c->config->b_subject_all_caps
            && \preg_match('%\p{Lu}%u', $subject)
            && ! \preg_match('%\p{Ll}%u', $subject)
        ) {
            $v->addError('All caps subject');
        }

        return $subject;
    }

    /**
     * Дополнительная проверка message
     */
    public function vCheckMessage(Validator $v, $message, $attr, $executive)
    {
        $prepare = null;

        // после цензуры текст сообщения пустой
        if ('' == $this->c->censorship->censor($message)) {
            $v->addError('No message after censoring');
        // проверка парсером
        } else {
            $prepare = true;
            $message = $this->c->Parser->prepare($message); //????

            foreach ($this->c->Parser->getErrors([], [], true) as $error) {
                $prepare = false;
                $v->addError($error);
            }
        }

        // текст сообщения только заглавными буквами
        if (
            true === $prepare
            && ! $executive
            && 1 !== $this->c->config->b_message_all_caps
        ) {
            $text = $this->c->Parser->getText();

            if (
                \preg_match('%\p{Lu}%u', $text)
                && ! \preg_match('%\p{Ll}%u', $text)
            ) {
                $v->addError('All caps message');
            }
        }

        return $message;
    }

    /**
     * Проверка времени ограничения флуда
     */
    public function vCheckTimeout(Validator $v, $submit, $attr, ?int $last)
    {
        if ($v->noValue($submit)) {
            return null;
        }

        $last = $last > 0 ? $last : $this->user->last_post;
        $time = \time() - $last;

        if ($time < $this->user->g_post_flood) {
            $v->addError(['Flood message', $this->user->g_post_flood - $time], 'e');
        }

        return $submit;
    }

    /**
     * Подготовка валидатора к проверке данных из формы создания темы/сообщения
     */
    protected function messageValidator(?Model $model, string $marker, array $args, bool $edit, bool $first): Validator
    {
        $this->c->Lang->load('validator');

        $notPM = $this->fIndex !== self::FI_PM;

        if ($this->user->isGuest) {
            $ruleEmail    = (1 === $this->c->config->b_force_guest_email ? 'required|' : '') . 'string:trim|email:noban';
            $ruleUsername = 'required|string:trim|username';
        } else {
            $ruleEmail    = 'absent';
            $ruleUsername = 'absent';
        }

        if (
            $notPM
            && (
                $this->user->isAdmin
                || $this->user->isModerator($model)
            )
        ) {
            if ($first) {
                $ruleStickTopic = 'checkbox';
                $ruleStickFP    = 'checkbox';
            } else {
                $ruleStickTopic = 'absent';
                $ruleStickFP    = 'absent';
            }
            if (
                ! $first
                && ! $edit
            ) {
                $ruleMergePost  = 'checkbox';
            } else {
                $ruleMergePost  = 'absent';
            }
            if (
                $edit
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

        if ($first) {
            $ruleSubject = 'required|string:trim,spaces|min:1|max:70|' . ($executive ? '' : 'noURL|') . 'check_subject';
        } else {
            $ruleSubject = 'absent';
        }

        if (
            ! $edit
            && $notPM
            && 1 === $this->c->config->b_topic_subscriptions
            && $this->user->email_confirmed
        ) {
            $ruleSubscribe = 'checkbox';
        } else {
            $ruleSubscribe = 'absent';
        }

        if (1 === $this->c->config->b_smilies) {
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
                'subscribe'    => $ruleSubscribe,
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

        if (
            $first
            && $notPM
            && $this->user->usePoll
        ) {
            $v->addValidators([
                'check_poll'  => [$this, 'vCheckPoll'],
            ])->addRules([
                'poll_enable'      => 'checkbox|check_poll',
                'poll.duration'    => 'integer|min:0|max:366',
                'poll.hide_result' => 'checkbox',
                'poll.question.*'  => 'string:trim|max:240',
                'poll.type.*'      => 'integer|min:1|max:' . $this->c->config->i_poll_max_fields,
                'poll.answer.*.*'  => 'string:trim|max:240',
            ]);
        }

        return $v;
    }

    /**
     * Дополнительная проверка опроса
     */
    public function vCheckPoll(Validator $v, $enable, $attr)
    {
        if (
            false !== $enable
            && empty($v->getErrors())
        ) {
            $poll = $this->c->polls->create([
                'question' => $v->poll['question'],
                'answer'   => $v->poll['answer'],
                'type'     => $v->poll['type'],
            ]);

            $result = $this->c->polls->revision($poll);

            if (true !== $result) {
                $v->addError($result);
            }
        }

        return $enable;
    }
}
