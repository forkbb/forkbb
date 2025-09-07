<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\User\User;
use function \ForkBB\__;

class Email extends Page
{
    protected User $curUser;

    /**
     * Подготовка данных для шаблона
     */
    public function email(array $args, string $method): Page
    {
        if (! $this->c->Csrf->verify($args['hash'], 'SendEmail', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
        }

        $this->curUser = $this->c->users->load($args['id']);

        if (
            ! $this->curUser instanceof User
            || $this->curUser->isGuest
        ) {
            return $this->c->Message->message('Bad request');
        }

        if (empty($this->curUser->linkEmail)) {
            $message = null === $this->curUser->linkEmail ? 'Form email disabled' : 'Bad request';

            return $this->c->Message->message($message);
        }

        $this->c->Lang->load('validator');
        $this->c->Lang->load('misc');

        $floodSize = \time() - (int) $this->user->last_email_sent;
        $floodSize = $floodSize < $this->user->g_email_flood ? $this->user->g_email_flood - $floodSize : 0;

        if ($floodSize > 0) {
            $this->fIswev = [FORK_MESS_ERR, ['Flood message', $floodSize]];
        }

        $data = [
            'redirect' => $this->c->Router->validate(
                $this->c->Secury->replInvalidChars(FORK_REF),
                'Index'
            ),
        ];

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
            ->addValidators([
            ])->addRules([
                'token'       => 'token:SendEmail',
                'redirect'    => 'required|referer:Index',
                'subject'     => 'required|string:trim|max:70',
                'message'     => 'required|string:trim,linebreaks|max:65000 bytes',
                'send'        => 'required|string',
            ])->addAliases([
                'subject'     => 'Email subject',
                'message'     => 'Email message',
            ])->addArguments([
                'token'       => $args,
            ])->addMessages([
            ]);

            if (
                $v->validation($_POST)
                && 0 === $floodSize
            ) {
                try {
                    if ($this->sendEmail($v->getData())) {
                        if ($this->user->g_email_flood > 0) {
                            $this->user->last_email_sent = \time();
                            $this->c->users->update($this->user);
                        }

                        $this->c->Log->info('Email send: ok', [
                            'user'      => $this->user->fLog(),
                            'recipient' => $this->curUser->fLog(),
                        ]);

                        return $this->c->Redirect->url($v->redirect)->message('Email sent redirect', FORK_MESS_SUCC);
                    }
                } catch (MailException $e) {
                    $this->c->Log->error('Email send: MailException', [
                        'user'      => $this->user->fLog(),
                        'exception' => $e,
                        'headers'   => false,
                    ]);
                }

                return $this->c->Message->message('When sending email there was an error', FORK_MESS_ERR);
            }

            $this->fIswev = $v->getErrors();
            $data         = $v->getData();

            $this->c->Log->warning('Email send: form, fail', [
                'user'      => $this->user->fLog(),
                'recipient' => $this->curUser->fLog(),
            ]);
        }

        $this->identifier = 'email';
        $this->nameTpl    = 'email';
        $this->robots     = 'noindex';
        $this->legend     = ['Send email to %s', $this->curUser->username];
        $this->crumbs     = $this->crumbs([null, $this->legend]);
        $this->form       = $this->formEmail($args, $data);

        return $this;
    }

    /**
     * Создает массив для формирование формы
     */
    protected function formEmail(array $args, array $data): array
    {
        return [
            'action' => $this->c->Router->link('SendEmail', $args),
            'hidden' => [
                'token'    => $this->c->Csrf->create('SendEmail', $args),
                'redirect' => $data['redirect'] ?? '',
            ],
            'sets'   => [
                'send-email' => [
                    'legend' => $this->legend,
                    'fields' => [
                        'subject' => [
                            'type'      => 'text',
                            'maxlength' => '70',
                            'caption'   => 'Email subject',
                            'required'  => true,
                            'value'     => $vars['subject'] ?? null,
                            'autofocus' => true,
                        ],
                        'message' => [
                            'type'      => 'textarea',
                            'caption'   => 'Email message',
                            'required'  => true,
                            'value'     => $data['message'] ?? null,
                        ],
                    ],
                ],
                'email-info' => [
                    'inform' => [
                        [
                            'message' => 'Email disclosure note',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'send' => [
                    'type'  => 'submit',
                    'value' => __('Send email'),
                ],
                'back' => [
                    'type'  => 'btn',
                    'value' => __('Go back'),
                    'href'  => $data['redirect'],
                    'class' => ['f-opacity', 'f-go-back'],
                ],
            ],
        ];
    }

    /**
     * Отправляет email
     */
    protected function sendEmail(array $data): bool
    {
        $tplData = [
            'fTitle'      => $this->c->config->o_board_title,
            'fMailer'     => __(['Mailer', $this->c->config->o_board_title]),
            'username'    => $this->curUser->username,
            'sender'      => $this->user->username,
            'mailSubject' => $data['subject'],
            'mailMessage' => $data['message'],
        ];

        return $this->c->Mail
            ->reset()
            ->setMaxRecipients(1)
            ->setFolder($this->c->DIR_LANG)
            ->setLanguage($this->curUser->language)
            ->setTo($this->curUser->email)
            ->setFrom($this->c->config->o_webmaster_email, $tplData['fMailer'])
            ->setReplyTo($this->user->email, $this->user->username)
            ->setTpl('form_email.tpl', $tplData)
            ->send();
    }
}
