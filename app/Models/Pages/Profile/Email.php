<?php

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Profile;
use ForkBB\Models\User\Model as User;
use function \ForkBB\__;

class Email extends Profile
{
    /**
     * Изменяет почтовый адрес пользователя по ссылке активации
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function setEmail(array $args, string $method): Page
    {
        if (
            $this->user->id !== (int) $args['id']
            || ! \hash_equals($args['hash'], $this->c->Secury->hash($args['id'] . $args['email'] . $args['key']))
            || empty($this->user->activate_string)
            || ! \hash_equals($this->user->activate_string, $args['key'])
        ) {
            return $this->c->Message->message('Bad request', false);
        }

        $this->c->Lang->load('profile');

        $this->user->email           = $args['email'];
        $this->user->email_confirmed = 1;
        $this->user->activate_string = '';

        $this->c->users->update($this->user);

        return $this->c->Redirect->url($this->user->link)->message('Email changed redirect');
    }

    /**
     * Подготавливает данные для шаблона смены почтового адреса
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function email(array $args, string $method): Page
    {
        if (
            false === $this->initProfile($args['id'])
            || ! $this->rules->editEmail
        ) {
            return $this->c->Message->message('Bad request');
        }

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_password' => [$this, 'vCheckPassword'],
                ])->addRules([
                    'token'     => 'token:EditUserEmail',
                    'password'  => 'required|string:trim|check_password',
                    'new_email' => 'required|string:trim|email:noban,unique,flood',
                ])->addAliases([
                    'new_email' => 'New email',
                    'password'  => 'Your passphrase',
                ])->addArguments([
                    'token'           => ['id' => $this->curUser->id],
                    'new_email.email' => $this->curUser,
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                if ($v->new_email === $this->curUser->email) {
                    return $this->c->Redirect->page('EditUserProfile', ['id' => $this->curUser->id])->message('Email is old redirect');
                }

                if (
                    $this->user->isAdmin
                    || '1' != $this->c->config->o_regs_verify
                ) {
                    $this->curUser->email           = $v->new_email;
                    $this->curUser->email_confirmed = 0;

                    $this->c->users->update($this->curUser);

                    return $this->c->Redirect->page('EditUserProfile', ['id' => $this->curUser->id])->message('Email changed redirect');
                } else {
                    $key  = $this->c->Secury->randomPass(33);
                    $hash = $this->c->Secury->hash($this->curUser->id . $v->new_email . $key);
                    $link = $this->c->Router->link('SetNewEmail', ['id' => $this->curUser->id, 'email' => $v->new_email, 'key' => $key, 'hash' => $hash]);
                    $tplData = [
                        'fRootLink' => $this->c->Router->link('Index'),
                        'fMailer'   => __('Mailer', $this->c->config->o_board_title),
                        'username'  => $this->curUser->username,
                        'link'      => $link,
                    ];

                    try {
                        $isSent = $this->c->Mail
                            ->reset()
                            ->setFolder($this->c->DIR_LANG)
                            ->setLanguage($this->curUser->language)
                            ->setTo($v->new_email, $this->curUser->username)
                            ->setFrom($this->c->config->o_webmaster_email, __('Mailer', $this->c->config->o_board_title))
                            ->setTpl('activate_email.tpl', $tplData)
                            ->send();
                    } catch (MailException $e) {
                        $isSent = false;
                    }

                    if ($isSent) {
                        $this->curUser->activate_string = $key;
                        $this->curUser->last_email_sent = \time();

                        $this->c->users->update($this->curUser);

                        return $this->c->Message->message(__('Activate email sent', $this->c->config->o_admin_email), false, 200);
                    } else {
                        return $this->c->Message->message(__('Error mail', $this->c->config->o_admin_email), true, 200);
                    }
                }
            } else {
                $this->curUser->__email = $v->new_email;
            }

            $this->fIswev = $v->getErrors();
        }


        $this->crumbs     = $this->crumbs(
            [$this->c->Router->link('EditUserEmail', ['id' => $this->curUser->id]), __('Change email')],
            [$this->c->Router->link('EditUserProfile', ['id' => $this->curUser->id]), __('Editing profile')]
        );
        $this->form       = $this->form();
        $this->actionBtns = $this->btns('edit');

        return $this;
    }

    /**
     * Создает массив данных для формы
     *
     * @return array
     */
    protected function form(): array
    {
        $form = [
            'action' => $this->c->Router->link('EditUserEmail', ['id' => $this->curUser->id]),
            'hidden' => [
                'token' => $this->c->Csrf->create('EditUserEmail', ['id' => $this->curUser->id]),
            ],
            'sets'   => [
                'new-email' => [
                    'class'  => 'data-edit',
                    'fields' => [
                        'new_email' => [
                            'type'      => 'text',
                            'maxlength' => 80,
                            'caption'   => __('New email'),
                            'required'  => true,
                            'pattern'   => '.+@.+',
                            'value'     => $this->curUser->email,
                            'info'      => ! $this->user->isAdmin && '1' == $this->c->config->o_regs_verify ? __('Email instructions') : null,
                        ],
                        'password' => [
                            'type'      => 'password',
                            'caption'   => __('Your passphrase'),
                            'required'  => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'      => 'submit',
                    'value'     => __('Submit'),
                    'accesskey' => 's',
                ],
            ],
        ];

        return $form;
    }
}
