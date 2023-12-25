<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Models\Page;
use ForkBB\Models\Pages\Profile;
use function \ForkBB\__;

class Delete extends Profile
{
    /**
     * Подготавливает данные для шаблона удаления аккаунта
     */
    public function delete(array $args, string $method): Page
    {
        if (
            false === $this->initProfile($args['id'])
            || ! $this->rules->deleteMyProfile
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('validator');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_password' => [$this, 'vCheckPassword'],
                ])->addRules([
                    'token'    => 'token:DeleteUserProfile',
                    'password' => 'required|string:trim|max:100000|check_password',
                    'confirm'  => 'required|integer|in:0,1',
                    'delete'   => 'required|string',
                ])->addAliases([
                    'password' => 'Your passphrase',
                ])->addArguments([
                    'token'    => $args,
                ])->addMessages([
                ]);

            $valid = $v->validation($_POST);

            if (0 === $v->confirm) {
                return $this->c->Redirect->page('EditUserProfile', $args)->message('No confirm redirect', FORK_MESS_VLD);
            } elseif ($valid) {
                $this->c->Cookie->deleteUser();
                $this->c->users->delete($this->user);

                return $this->c->Redirect->page('Index')->message('Your deleted redirect', FORK_MESS_SUCC);
            }

            $this->fIswev = $v->getErrors();
        }

        $this->fIswev          = [FORK_MESS_ERR, 'You are trying to delete your profile'];
        $this->identifier      = ['profile', 'profile-delete'];
        $this->crumbs          = $this->crumbs(
            [
                $this->c->Router->link('DeleteUserProfile', $args),
                'Deleting profile',
            ],
            [
                $this->c->Router->link('EditUserProfile', $args),
                'Editing profile',
            ]
        );
        $this->form            = $this->form($args);
        $this->actionBtns      = $this->btns('edit');
        $this->profileIdSuffix = '-delete';

        return $this;
    }

    /**
     * Создает массив данных для формы
     */
    protected function form(array $args): array
    {
        $yn   = [1 => __('Yes'), 0 => __('No')];
        $form = [
            'action' => $this->c->Router->link('DeleteUserProfile', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('DeleteUserProfile', $args),
            ],
            'sets'   => [
                'delete' => [
                    'class'  => ['data-edit'],
                    'fields' => [
                        'confirm' => [
                            'type'    => 'radio',
                            'value'   => '0',
                            'values'  => $yn,
                            'caption' => 'Confirm delete label',
                            'help'    => 'Confirm delete info',
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
                'delete' => [
                    'type'  => 'submit',
                    'value' => __('Delete'),
                ],
            ],
        ];

        return $form;
    }
}
