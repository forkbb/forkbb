<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\PM;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Model;
use ForkBB\Models\Pages\PM\AbstractPM;
use ForkBB\Models\Pages\PostFormTrait;
use ForkBB\Models\Pages\PostValidatorTrait;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\PM\PPost;
use ForkBB\Models\PM\PTopic;
use ForkBB\Core\Exceptions\MailException;
use InvalidArgumentException;
use function \ForkBB\__;

class PMPost extends AbstractPM
{
    use PostFormTrait;
    use PostValidatorTrait;

    /**
     * Создание новой приватной темы или сообщения
     */
    public function post(array $args, string $method): Page
    {
        $this->args = $args;
        $quote      = null;

        if (
            isset($args['more2'])
            && '' !== \trim($args['more2'], '1234567890')
        ) {
            $hash  = $args['more2'];
            $temp  = $args;

            unset($temp['more2']);

            if (1 !== $this->user->u_pm) {
                return $this->c->Message->message('PM off', true, 403);
            } elseif (! $this->c->Csrf->verify($hash, 'PMAction', $temp)) {
                return $this->c->Message->message($this->c->Csrf->getError());
            }

            $this->targetUser = $this->c->users->load($args['more1']);
            $this->newTopic   = true;
            $this->formTitle  = 'New PT title';
            $this->pmCrumbs[] = [$this->c->Router->link('PMAction', $args), 'New dialogue'];
            $topic            = $this->pms->create(Cnst::PTOPIC);
            $topic->sender    = $this->user;
            $topic->recipient = $this->targetUser;

        } elseif ($this->pms->accessTopic($args['more1'])) {
            $topic = $this->pms->load(Cnst::PTOPIC, $args['more1']);

            if (isset($args['more2'])) {
                $quote = (int) $args['more2'];
            }

            $this->targetUser = $topic->ztUser;
            $this->pms->area  = $this->pms->inArea($topic);
            $this->newTopic   = false;
            $this->formTitle  = Cnst::ACTION_ARCHIVE === $this->pms->area ? 'New PM title archive' : 'New PM title';
            $this->pmCrumbs[] = [$this->c->Router->link('PMAction', $args), 'New message'];
            $this->pmCrumbs[] = $topic;
        } else {
            return $this->c->Message->message('Not Found', true, 404);
        }

        if ($topic->closed) {
            $this->fIswev = [FORK_MESS_ERR, 'Dialogue is closed'];
        } elseif (2 === $topic->blockStatus) {
            $this->fIswev = [FORK_MESS_ERR, 'You block addr'];
        } elseif (1 === $topic->blockStatus) {
            $this->fIswev = [FORK_MESS_ERR, 'Addr block you'];
        } elseif (! $topic->actionsAllowed) {
            $this->fIswev = [FORK_MESS_ERR, 'Target group pm off'];
        } elseif (! $topic->canReply) {
            $this->fIswev = [FORK_MESS_ERR, 'Target pm off'];
        }

        $this->c->Lang->load('post');

        if ('POST' === $method) {
            $v       = $this->messageValidatorPM(null, 'PMAction', $args, false, $this->newTopic);
            $isValid = $v->validation($_POST);

            if (
                $this->newTopic
                && ! $this->user->isAdmin
            ) {
                if (null !== $v->submit) {
                    if (
                        $this->targetUser->g_pm_limit > 0
                        && $this->targetUser->u_pm_num_all >= $this->targetUser->g_pm_limit
                    ) {
                        $this->fIswev = [FORK_MESS_ERR, 'Target is full'];
                    } elseif (
                        $this->user->g_pm_limit > 0
                        && $this->user->u_pm_num_all >= $this->user->g_pm_limit
                    ) {
                        $this->fIswev = [FORK_MESS_ERR, 'Active is full'];
                    }
                } elseif (null !== $v->archive) {
                    if (
                        $this->user->g_pm_limit > 0
                        && $this->pms->totalArchive >= $this->user->g_pm_limit
                    ) {
                        $this->fIswev = [FORK_MESS_ERR, 'Archive is full'];
                    }
                }
            }

            $this->fIswev  = $v->getErrors();
            $args['_vars'] = $v->getData();

            if (
                empty($this->fIswev[FORK_MESS_ERR])
                && $isValid
                && null === $v->preview
                && (
                    null !== $v->submit
                    || null !== $v->archive
                )
            ) {
                return $this->endPost($topic, $v);
            }

            if (
                null !== $v->preview
                && $isValid
            ) {
                $this->previewHtml = $this->c->censorship->censor(
                    $this->c->Parser->parseMessage(null, (bool) $v->hide_smilies)
                );
            }
        } elseif ($quote) {
            $quote = $this->pms->load(Cnst::PPOST, $quote);

            if (
                ! $quote instanceof PPost
                || $topic !== $quote->parent
            ) {
                return $this->c->Message->message('Bad request');
            }

            $args['_vars'] = [
                'message' => "[quote=\"{$quote->poster}\"]{$quote->message}[/quote]",
            ];

            unset($args['more2']);
        }

        $this->identifier = ['pm', 'pm-post'];
        $this->pmIndex    = $this->pms->area;
        $this->nameTpl    = 'pm/post';
        $this->form       = $this->messageFormPM($this->newTopic ? $this->targetUser : $topic, 'PMAction', $args, false, $this->newTopic, false);
        $this->posts      = $this->newTopic ? null : $topic->review();
        $this->postsTitle = 'Topic review';

        if (! empty($this->posts)) {
            $this->c->Parser; // предзагрузка
        }

        return $this;
    }

    protected function messageFormPM(?Model $model, string $marker, array $args, bool $edit, bool $first, bool $quick): array
    {
        $form = $this->messageForm($model, $marker, $args, $edit, $first, $quick);

        if ($this->newTopic) {
            $form['btns']['archive'] = [
                'type'  => 'submit',
                'value' => __('Archive Send later'),
            ];
        } elseif (Cnst::ACTION_ARCHIVE === $this->pms->area) {
            $form['btns']['submit']['value'] = __('Save');
        }

        return $form;
    }

    protected function messageValidatorPM(?Model $model, string $marker, array $args, bool $edit, bool $first): Validator
    {
        $v = $this->messageValidator($model, $marker, $args, $edit, $first)
            ->addRules([
                'archive' => $this->newTopic ? 'string' : 'absent',
                'message' => 'required|string:trim|max:65535 bytes|check_message',
            ])->addArguments([
                'submit.check_timeout' => $this->user->u_pm_last_post,
            ]);

        return $v;
    }

    /**
     * Создание приватной темы/сообщения
     */
    protected function endPost(PTopic $topic, Validator $v): Page
    {
        $now = \time();

        if ($this->newTopic) {
            $topic->subject = $v->subject;
            $topic->status  = null !== $v->archive ? Cnst::PT_ARCHIVE : Cnst::PT_NORMAL;

            $this->pms->insert(Cnst::PTOPIC, $topic);
        }

        $post               = $this->pms->create(Cnst::PPOST);
        $post->user         = $this->user;
        $post->poster_ip    = $this->user->ip;
        $post->message      = $v->message;
        $post->hide_smilies = $v->hide_smilies ? 1 : 0;
        $post->posted       = $now;
        $post->topic_id     = $topic->id;

        $this->pms->insert(Cnst::PPOST, $post);

        if ($this->newTopic) {
            $topic->first_post_id = $post->id;
        }

        $this->pms->update(Cnst::PTOPIC, $topic->calcStat());

        // синхронизация вложений
        if ($this->userRules->useUpload) {
            $this->c->attachments->syncWithPost($post);
        }

        // новый диалог в архив
        if (
            $this->newTopic
            && Cnst::PT_ARCHIVE === $topic->poster_status
        ) {
            $message = 'PM to archive Redirect';
        // новый диалог в активные
        } elseif ($this->newTopic) {
            $message = 'PM created Redirect';

            $this->user->u_pm_num_all += 1; // ???? может recalculate() ниже u_pm_last_post = $now?

            $this->targetUser->u_pm_flash = 1;

            $this->pms->recalculate($this->targetUser);
        // сообщение в архивный диалог
        } elseif (Cnst::PT_ARCHIVE === $topic->poster_status) {
            $message = 'PM to archive Redirect';
        // сообщение в активный диалог
        } else {
            $message = 'PM sent Redirect';

            $this->targetUser->u_pm_flash = 1;

            $this->pms->recalculate($this->targetUser);
        }

        $this->user->u_pm_last_post = $now;

        $this->c->users->update($this->user);

        // отправка уведомления
        $this->c->Online->calc($this);

        if ( // ????
            Cnst::PT_NORMAL === $topic->poster_status
            && 1 === $this->targetUser->u_pm_notify
            && 1 === $this->targetUser->email_confirmed
            && 0 === $this->c->bans->banFromName($this->targetUser->username)
            && ! $this->c->Online->isOnline($this->targetUser)
        ) {
            try {
                $this->c->Lang->load('common', $this->targetUser->language);

                $tplData = [
                    'fTitle'     => $this->c->config->o_board_title,
                    'fMailer'    => __(['Mailer', $this->c->config->o_board_title]),
                    'pmSubject'  => $topic->subject,
                    'username'   => $this->targetUser->username,
                    'sender'     => $this->user->username,
                    'messageUrl' => $this->newTopic ? $topic->link : $post->link,
                ];

                $this->c->Mail
                    ->reset()
                    ->setMaxRecipients(1)
                    ->setFolder($this->c->DIR_LANG)
                    ->setLanguage($this->targetUser->language)
                    ->setTo($this->targetUser->email, $this->targetUser->username)
                    ->setFrom($this->c->config->o_webmaster_email, $tplData['fMailer'])
                    ->setTpl('new_pm.tpl', $tplData)
                    ->send();

                $this->c->Lang->load('common', $this->user->language); // ???? вынести?
            } catch (MailException $e) {
                $this->c->Log->error('PM: MailException', [
                    'exception' => $e,
                    'headers'   => false,
                ]);
            }
        }
        // отправка уведомления

        return $this->c->Redirect->url($post->link)->message($message, FORK_MESS_SUCC);
    }
}
