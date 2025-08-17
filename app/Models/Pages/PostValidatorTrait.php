<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Image;
use ForkBB\Core\Validator;
use ForkBB\Models\Model;
use function \ForkBB\__;

trait PostValidatorTrait
{
    protected int $censorCount = 0;

    /**
     * Дополнительная проверка subject
     */
    public function vCheckSubject(Validator $v, string $subject, $attr, bool $power): string
    {
        // после цензуры заголовок темы путой
        if ('' == $this->c->censorship->censor($subject, $this->censorCount)) {
            $v->addError('No subject after censoring');

        // лимит на слова подвергнутые цензуре
        } elseif (
            $this->c->config->i_censoring_count > 0
            && $this->censorCount > $this->c->config->i_censoring_count
        ) {
            $v->addError('Too many censored words');

        // заголовок темы только заглавными буквами
        } elseif (
            ! $power
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
    public function vCheckMessage(Validator $v, string $message, $attr, bool $power): string
    {
        if ('' === $message) {
            return '';
        }

        $prepare = null;

        // после цензуры текст сообщения пустой
        if ('' == $this->c->censorship->censor($message, $this->censorCount)) {
            $v->addError('No message after censoring');

        // лимит на слова подвергнутые цензуре
        } elseif (
            $this->c->config->i_censoring_count > 0
            && $this->censorCount > $this->c->config->i_censoring_count
        ) {
            $v->addError('Too many censored words');

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
            && ! $power
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
            $v->addError(['Flood message', $this->user->g_post_flood - $time], FORK_MESS_ERR);
        }

        return $submit;
    }

    /**
     * Подготовка валидатора к проверке данных из формы создания темы/сообщения
     */
    protected function messageValidator(?Model $model, string $marker, array $args, bool $edit, bool $first, bool $about = false): Validator
    {
        $this->c->Lang->load('validator');

        $this->attachmentsProc($marker, $args);

        $notPM  = $this->fIndex !== self::FI_PM;
        $preMod = $this->userRules->forPreModeration($model);

        if ($this->user->isGuest) {
            if (1 === $this->c->config->b_hide_guest_email_fld) {
                $ruleEmail    = 'absent';

            } else {
                $ruleEmail    = 1 === $this->c->config->b_force_guest_email ? 'required' : 'exist';
                $ruleEmail   .= '|string:trim,empty|email:noban';
            }

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
                && ! $about
                && ! $model->user->isGuest
                && ! $model->user->isAdmin
            ) {
                $ruleEditPost   = 'checkbox';

            } else {
                $ruleEditPost   = 'absent';
            }

            $power              = true;

        } else {
            $ruleStickTopic     = 'absent';
            $ruleStickFP        = 'absent';
            $ruleMergePost      = 'absent:1';
            $ruleEditPost       = 'absent';
            $power              = false;
        }

        if ($first) {
            $ruleSubject = 'required|string:trim,spaces|min:1|max:' . $this->c->MAX_SUBJ_LENGTH . '|' . ($power ? '' : 'noURL|') . 'check_subject';

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

        if (
            $notPM
            && ! $edit
            && ! $about
            && $this->userRules->useDraft
        ) {
            $ruleDraft = 'string|check_timeout';

        } else {
            $ruleDraft = 'absent';
        }

        if (
            $model instanceof Model
            && $preMod
        ) {
            $ruleSubmit    = 'absent';
            $rulePreMod    = 'string';
            $ruleSubscribe = 'absent';

        } else {
            $ruleSubmit    = 'string|check_timeout';
            $rulePreMod    = 'absent';
        }

        if (
            $power
            && 1 === $this->c->config->b_premoderation
            && ! $preMod
            && $notPM
            && $first
        ) {
            $rulePremoderation = 'required|integer|in:-1,0,1';

        } else {
            $rulePremoderation = 'absent';
        }


        $ruleMessage = ($about ? '' : 'required|') . 'string:trim|max:' . $this->c->MAX_POST_SIZE . ($power ? '' : '|noURL') . '|check_message';

        $v = $this->c->Validator->reset()
            ->addValidators([
                'check_subject'  => [$this, 'vCheckSubject'],
                'check_message'  => [$this, 'vCheckMessage'],
                'check_timeout'  => [$this, 'vCheckTimeout'],
            ])->addRules([
                'token'         => 'token:3600:' . $marker,
                'email'         => $ruleEmail,
                'username'      => $ruleUsername,
                'subject'       => $ruleSubject,
                'stick_topic'   => $ruleStickTopic,
                'stick_fp'      => $ruleStickFP,
                'merge_post'    => $ruleMergePost,
                'hide_smilies'  => $ruleHideSmilies,
                'edit_post'     => $ruleEditPost,
                'subscribe'     => $ruleSubscribe,
                'terms'         => 'absent',
                'premoderation' => $rulePremoderation,
                'preview'       => 'string',
                'submit'        => $ruleSubmit,
                'draft'         => $ruleDraft,
                'pre_mod'       => $rulePreMod,
                'message'       => $ruleMessage,
            ])->addAliases([
                'email'         => 'Email',
                'username'      => 'Username',
                'subject'       => 'Subject',
                'message'       => 'Message',
            ])->addArguments([
                'token'                 => $args,
                'subject.check_subject' => $power,
                'message.check_message' => $power,
                'email.email'           => $this->user,
            ])->addMessages([
                'username.login' => 'Login format',
            ]);

        if (
            $first
            && $notPM
            && $this->userRules->usePoll
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

        if (
            $this->user->isGuest
            || empty($this->user->last_post)
        ) {
            if (1 === $this->c->config->b_ant_use_js) {
                $v->addRules(['nekot' => 'exist|string|nekot']);
            }
        }

        return $v;
    }

    /**
     * Дополнительная проверка опроса
     */
    public function vCheckPoll(Validator $v, string|false $enable, $attr): string|false
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

    /**
     * Проверка вложений
     */
    public function vCheckAttach(Validator $v, array $files): array
    {
        $exts   = \array_flip(\explode(',', $this->user->g_up_ext));
        $result = [];

        foreach ($files as $file) {
            if (isset($exts[$file->ext()])) {
                $result[] = $file;

            } else {
                $v->addError(['The %s extension is not allowed', $file->ext()]);
            }
        }

        return $result;
    }

    /**
     * Обрабатывает загруженные файлы
     */
    protected function attachmentsProc(string $marker, array $args): void
    {
        if (! $this->userRules->useUpload) {
            return;
        }

        $v = $this->c->Validator->reset()
            ->addValidators([
                'check_attach' => [$this, 'vCheckAttach'],
            ])->addRules([
                'token'       => 'token:3600:' . $marker,
                'message'     => 'string:trim',
                'attachments' => "file:multiple|max:{$this->user->g_up_size_kb}|check_attach",
            ])->addAliases([
                'attachments' => 'Attachments',
            ])->addArguments([
                'token' => $args,
            ])->addMessages([
            ]);

        if (! $v->validation($_FILES + $_POST)) {
            $this->fIswev = $v->getErrors();

            return;
        }

        $calc = false;

        // костыль с конвертацией картинок из base64 в файлы
        $_POST['message'] = \preg_replace_callback(
            '%\[img\](data:[\w/.+-]*+;base64,[a-zA-Z0-9/+=]++)\[/img\]%',
            function ($matches) use ($calc) {
                $file = $this->c->Files->uploadFromLink($matches[1]);

                if (! $file instanceof Image) {
                    return $this->c->Files->error() ?? 'Bad image';
                }

                $data = $this->c->attachments->addFile($file);

                if (\is_array($data)) {
                    $calc = true;

                    return "[img]{$data['url']}[/img]";

                } else {
                    return 'Bad file';
                }
            },
            (string) $v->message
        );

        if (\is_array($v->attachments)) {
            $result = '';

            foreach ($v->attachments as $file) {
                $data = $this->c->attachments->addFile($file);

                if (\is_array($data)) {
                    $name = $file->name();
                    $calc = true;

                    if ($data['image']) {
                        $result .= "\n[img]{$data['url']}[/img]"; // ={$name}

                    } else {
                        $result .= "\n[url={$data['url']}]{$name}[/url]";
                    }
                }
            }

            // костыль с добавление вложений в сообщение на лету
            if ('' !== $result) {
                $_POST['message'] .= $result;
            }
        }

        if ($calc) {
            $this->c->attachments->recalculate($this->user);
        }
    }
}
