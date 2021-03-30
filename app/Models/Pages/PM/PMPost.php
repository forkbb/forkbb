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
use ForkBB\Models\Pages\PM\AbstractPM;
use ForkBB\Models\Pages\PostFormTrait;
use ForkBB\Models\Pages\PostValidatorTrait;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\PM\PPost;
use ForkBB\Models\PM\PTopic;
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
            $topic = null;
            $hash  = $args['more2'];
            $temp  = $args;

            unset($temp['more2']);

            if (! $this->c->Csrf->verify($hash, 'PMAction', $temp)) {
                return $this->c->Message->message($this->c->Csrf->getError());
            }

            $this->targetUser = $this->c->users->load($args['more1']);
            $this->newTopic   = true;
            $this->formTitle  = 'New PT title';
            $this->pmCrumbs[] = [
                $this->c->Router->link('PMAction', $args),
                __('New dialogue'),
            ];
        } elseif ($this->pms->accessTopic($args['more1'])) {
            $topic = $this->pms->load(Cnst::PTOPIC, $args['more1']);

            if (isset($args['more2'])) {
                $quote = (int) $args['more2'];
            }

            $this->targetUser = $topic->ztUser;
            $this->pms->area  = $this->pms->inArea($topic);
            $this->newTopic   = false;
            $this->formTitle  = 'New PM title';
            $this->pmCrumbs[] = [
                $this->c->Router->link('PMAction', $args),
                __('New message'),
            ];
            $this->pmCrumbs[] = $topic;
        } else {
            return $this->c->Message->message('Not Found', true, 404);
        }

        $this->c->Lang->load('post');

        if ('POST' === $method) {
            $v = $this->messageValidatorPM(null, 'PMAction', $args, false, $this->newTopic);

            if (
                $v->validation($_POST)
                && null === $v->preview
                && (
                    null !== $v->submit
                    || null !== $v->archive
                )
            ) {
                return $this->endPost($topic, $v);
            }

            $this->fIswev  = $v->getErrors();
            $args['_vars'] = $v->getData();

            if (
                null !== $v->preview
                && empty($this->fIswev)
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

        $this->pmIndex    = $this->pms->area;
        $this->nameTpl    = 'pm/post';
        $this->form       = $this->messageFormPM(null, 'PMAction', $args, false, $this->newTopic, false);
        $this->posts      = $this->newTopic ? null : $topic->review();
        $this->postsTitle = 'Topic review';

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
        } elseif (Cnst::ACTION_ARCHIVE === $this->pmIndex) {
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
            ]);

        return $v;
    }

    /**
     * Создание приватной темы/сообщения
     */
    protected function endPost(?PTopic $topic, Validator $v): Page
    {
        $now = \time();

        if (! $topic instanceof PTopic) {
            $topic            = $this->pms->create(Cnst::PTOPIC);
            $topic->subject   = $v->subject;
            $topic->sender    = $this->user;
            $topic->recipient = $this->targetUser;
            $topic->status    = null !== $v->archive ? Cnst::PT_ARCHIVE : Cnst::PT_NORMAL;

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

        $this->user->u_pm_last_post = $now;

        $this->pms->update(Cnst::PTOPIC, $topic->calcStat());
        $this->pms->updateFromPTopics(true, $topic);

        if (null !== $v->archive) {
            $message = 'PM to archive Redirect';
        } elseif ($this->newTopic) {
            $message = 'PM created Redirect';
        } else {
            $message = 'PM sent Redirect';
        }

        return $this->c->Redirect->url($post->link)->message($message);
    }
}