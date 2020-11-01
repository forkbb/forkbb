<?php

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\User\Model as User;
use function \ForkBB\__;

class Email extends Page
{
    /**
     * Получатель
     * @var User
     */
    protected $curUser;

    /**
     * Подготовка данных для шаблона
     */
    public function email(array $args, string $method): Page
    {
        $this->curUser = $this->c->users->load((int) $args['id']);

        if (! $this->curUser instanceof User || $this->curUser->isGuest) {
            return $this->c->Message->message('Bad request');
        }

        $rules = $this->c->ProfileRules->setUser($this->curUser);

        if (! $rules->viewEmail || ! $rules->sendEmail) {
            $message = null === $rules->sendEmail ? 'Form email disabled' : 'Bad request';

            return $this->c->Message->message($message);
        }

        $this->c->Lang->load('validator');
        $this->c->Lang->load('misc');

        $floodSize = \time() - (int) $this->user->last_email_sent;
        $floodSize = $floodSize < $this->user->g_email_flood ? $this->user->g_email_flood - $floodSize : 0;
        if ($floodSize > 0) {
            $this->fIswev = ['e', __('Flood message', $floodSize)];
        }

        $data = [
            'redirect' => $this->c->Router->validate($_SERVER['HTTP_REFERER'] ?? '', 'Index'),
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

                        return $this->c->Redirect->url($v->redirect)->message('Email sent redirect');
                    }
                } catch (MailException $e) {
                }

                return $this->c->Message->message('When sending email there was an error');
            }

            $this->fIswev = $v->getErrors();
            $data         = $v->getData();
        }

        $this->nameTpl   = 'email';
        $this->robots    = 'noindex';
        $this->crumbs    = $this->crumbs(__('Send email to %s', $this->curUser->username));
        $this->formTitle = __('Send email');
        $this->form      = $this->formEmail($args, $data);

        return $this;
    }

    /**
     * Создает массив для формирование формы
     */
    protected function formEmail(array $args, array $data): array
    {
        return [
            'action' => $this->c->Router->link(
                'SendEmail',
                $args
            ),
            'hidden' => [
                'token' => $this->c->Csrf->create(
                    'SendEmail',
                    $args
                ),
                'redirect' => $data['redirect'] ?? '',
            ],
            'sets'   => [
                'send-email' => [
                    'legend' => __('Send email to %s', $this->curUser->username),
                    'fields' => [
                        'subject' => [
                            'type'      => 'text',
                            'maxlength' => '70',
                            'caption'   => __('Email subject'),
                            'required'  => true,
                            'value'     => $vars['subject'] ?? null,
                            'autofocus' => true,
                        ],
                        'message' => [
                            'type'      => 'textarea',
                            'caption'   => __('Email message'),
                            'required'  => true,
                            'value'     => $data['message'] ?? null,
                        ],
                    ],
                ],
                'email-info' => [
                    'info' => [
                        'info1' => [
                            'type'  => '', //????
                            'value' => __('Email disclosure note'),
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'send' => [
                    'type'      => 'submit',
                    'value'     => __('Send email'),
//                    'accesskey' => 's',
                ],
                'back' => [
                    'type'      => 'btn',
                    'value'     => __('Go back'),
                    'link'      => $data['redirect'], // 'javascript:history.go(-1)',
                    'class'     => 'f-opacity',
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
            'fMailer'     => __('Mailer', $this->c->config->o_board_title),
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
