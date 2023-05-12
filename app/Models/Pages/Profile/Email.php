<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Profile;
use ForkBB\Models\User\User;
use function \ForkBB\__;

class Email extends Profile
{
    /**
     * Изменяет почтовый адрес пользователя по ссылке активации
     */
    public function setEmail(array $args, string $method): Page
    {
        if (
            $this->user->id !== $args['id']
            || ! $this->c->Csrf->verify($args['hash'], 'SetNewEmail', $args)
            || ! \hash_equals($this->user->activate_string, $args['key'])
        ) {
            return $this->c->Message->message('Bad request', false);
        }

        $this->user->email           = $args['email'];
        $this->user->email_confirmed = 1;
        $this->user->activate_string = '';

        $change = $this->user->isModified('email');

        $this->c->users->update($this->user);

        return $this->c->Redirect
            ->url($this->user->link)
            ->message($change ? 'Email changed redirect' : 'Email confirmed redirect');
    }

    /**
     * Подготавливает данные для шаблона смены почтового адреса
     */
    public function email(array $args, string $method): Page
    {
        if (
            false === $this->initProfile($args['id'])
            || ! $this->rules->editEmail
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('validator');

        if ('POST' === $method) {
            $isSent = null;
            $key    = null;

            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_password' => [$this, 'vCheckPassword'],
                ])->addRules([
                    'token'     => 'token:EditUserEmail',
                    'password'  => 'required|string:trim|max:100000|check_password',
                    'new_email' => 'required|string:trim|email',
                    'submit'    => 'required|string',
                ])->addAliases([
                    'new_email' => 'New email',
                    'password'  => 'Your passphrase',
                ])->addArguments([
                    'token'           => $args,
                ])->addMessages([
                ]);

            $isValid = $v->validation($_POST);

            if ($isValid) {
                if (
                    $v->new_email === $this->curUser->email
                    && ! $this->rules->confirmEmail
                ) {
                    return $this->c->Redirect
                        ->page('EditUserProfile', $args)
                        ->message('Email is old redirect');
                }

                $v = $v->reset()
                    ->addRules([
                        'new_email' => 'required|string:trim|email:noban,flood',
                    ])->addAliases([
                        'new_email' => 'New email',
                    ])->addArguments([
                        'new_email.email' => $this->curUser,
                    ]);

                $isValid = $v->validation($_POST);
            }

            if ($isValid) {
                $v = $this->c->Validator->reset()
                    ->addRules([
                        'new_email' => 'required|string:trim|email:unique',
                    ])->addAliases([
                        'new_email' => 'New email',
                    ])->addArguments([
                        'new_email.email' => $this->curUser,
                    ]);

                $isValid = $v->validation($_POST);

                if ($isValid) {
                    if (
                        ! $this->rules->my
                        && $this->rules->admin
                    ) {
                        $this->curUser->email           = $v->new_email;
                        $this->curUser->email_confirmed = 0;

                        $this->c->users->update($this->curUser);

                        return $this->c->Redirect
                            ->page('EditUserProfile', $args)
                            ->message('Email changed redirect');
                    } else {
                        $this->c->Csrf->setHashExpiration(259200); // ???? хэш действует 72 часа

                        $key  = $this->c->Secury->randomPass(33);
                        $link = $this->c->Router->link(
                            'SetNewEmail',
                            [
                                'id'    => $this->curUser->id,
                                'email' => $v->new_email,
                                'key'   => $key,
                            ]
                        );
                        $tplData = [
                            'fRootLink' => $this->c->Router->link('Index'),
                            'fMailer'   => __(['Mailer', $this->c->config->o_board_title]),
                            'username'  => $this->curUser->username,
                            'link'      => $link,
                        ];

                        try {
                            $isSent = $this->c->Mail
                                ->reset()
                                ->setMaxRecipients(1)
                                ->setFolder($this->c->DIR_LANG)
                                ->setLanguage($this->curUser->language)
                                ->setTo($v->new_email, $this->curUser->username)
                                ->setFrom($this->c->config->o_webmaster_email, $tplData['fMailer'])
                                ->setTpl('activate_email.tpl', $tplData)
                                ->send();
                        } catch (MailException $e) {
                            $isSent = false;

                            $this->c->Log->error('Email activation: MailException', [
                                'exception' => $e,
                                'headers'   => false,
                            ]);
                        }
                    }
                } elseif (! $this->user->isAdmin) {
                    // обманка
                    $isSent = true;
                }

                if (null !== $isSent) {
                    if ($isSent) {
                        if (\is_string($key)) {
                            $this->curUser->activate_string = $key;
                        }

                        $this->curUser->last_email_sent = \time();

                        $this->c->users->update($this->curUser);

                        return $this->c->Message
                            ->message(['Activate email sent', $this->c->config->o_admin_email], false, 200);
                    } else {
                        return $this->c->Message
                            ->message(['Error mail', $this->c->config->o_admin_email], true, 200);
                    }
                }
            }

            $this->curUser->__email = $v->new_email;
            $this->fIswev           = $v->getErrors();
        }

        $this->crumbs          = $this->crumbs(
            [
                $this->c->Router->link('EditUserEmail', $args),
                'Change email',
            ],
            [
                $this->c->Router->link('EditUserProfile', $args),
                'Editing profile',
            ]
        );
        $this->form            = $this->form($args);
        $this->actionBtns      = $this->btns('edit');
        $this->profileIdSuffix = '-email';

        return $this;
    }

    /**
     * Создает массив данных для формы
     */
    protected function form(array $args): array
    {
        $form = [
            'action' => $this->c->Router->link('EditUserEmail', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('EditUserEmail', $args),
            ],
            'sets'   => [
                'new-email' => [
                    'class'  => ['data-edit'],
                    'fields' => [
                        'new_email' => [
                            'autofocus' => true,
                            'type'      => 'text',
                            'maxlength' => '80',
                            'caption'   => $this->rules->confirmEmail ? 'New or old email' : 'New email',
                            'required'  => true,
                            'pattern'   => '.+@.+',
                            'value'     => $this->curUser->email,
                            'help'      => $this->rules->my ? 'Email instructions' : null,
                        ],
                        'password' => [
                            'type'      => 'password',
                            'caption'   => 'Your passphrase',
                            'required'  => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'  => 'submit',
                    'value' => __('Submit'),
                ],
            ],
        ];

        return $form;
    }
}
