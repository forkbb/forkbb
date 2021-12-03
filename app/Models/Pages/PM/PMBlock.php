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
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\PM\PPost;
use ForkBB\Models\PM\PTopic;
use ForkBB\Models\User\User;
use InvalidArgumentException;
use function \ForkBB\__;

class PMBlock extends AbstractPM
{
    const BLOCK   = 'block';
    const UNBLOCK = 'unblock';

    /**
     * Подготовка данных для шаблона
     */
    protected function view(array $args, string $method): Page
    {
        $this->nameTpl    = 'pm/block';
        $this->blockList  = $this->c->pms->block->list;
        $this->pmCrumbs[] = [
            $this->c->Router->link('PMAction', $args),
            __('Blocked users'),
        ];

        return $this;
    }

    /**
     * (Раз)блокировка пользователя или отображение списка заблокированных
     */
    public function block(array $args, string $method): Page
    {
        $this->args    = $args;
        $this->pmIndex = Cnst::ACTION_BLOCK;

        if (! isset($args['more1'])) {
            return $this->view($args, $method);
        }

        if (isset($args['more2'])) {
            if ('' !== \trim($args['more2'], '1234567890')) {
                return $this->c->Message->message('Bad request');
            }

            $post = $this->pms->load(Cnst::PPOST, (int) $args['more2']);

            if (! $post instanceof PPost
                || $args['more1'] !== $post->poster_id
            ) {
                return $this->c->Message->message('Bad request');
            }
        } else {
            $post = null;
        }

        $blockUser = $this->c->users->load($args['more1']);

        if (! $blockUser instanceof User) {
            return $this->c->Message->message('Bad request');
        }

        $blockStatus = $this->pms->block->isBlock($blockUser);

        if (
            ! $blockStatus
            && (
                ! $post
                || ! $this->pms->block->canBlock($blockUser)
            )
        ) {
            return $this->c->Message->message('Invalid action');
        }

        $this->c->Lang->load('validator');

        $this->linkPMBlk = $this->c->Router->link('PMAction', ['action' => Cnst::ACTION_BLOCK]);
        $this->linkBack  = $post instanceof PPost ? $post->link : $this->linkPMBlk;

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'       => 'token:PMAction',
                    'confirm'     => 'checkbox',
                    self::BLOCK   => 'string',
                    self::UNBLOCK => 'string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (
                ! $v->validation($_POST)
                || '1' !== $v->confirm
            ) {
                return $this->c->Redirect->url($this->linkBack)->message('No confirm redirect');
            } elseif (
                (
                    ! $v->{self::BLOCK}
                    && ! $v->{self::UNBLOCK}
                )
                || (
                    $v->{self::BLOCK}
                    && $blockStatus
                )
                || (
                    $v->{self::UNBLOCK}
                    && ! $blockStatus
                )
            ) {
                return $this->c->Message->message('Invalid action');
            }

            if ($v->{self::BLOCK}) {
                $this->pms->block->add($blockUser);

                $message = 'User is blocked redirect';
            } else {
                $this->pms->block->remove($blockUser);

                $message = 'User is unblocked redirect';
            }

            return $this->c->Redirect->url($this->linkBack)->message($message);
        }

        $this->nameTpl    = 'pm/form';
        $this->formTitle  = $blockStatus ? 'Unblock user title' : 'Block user title';
        $this->formClass  = 'block';
        $this->form       = $this->formBlock($args, $blockStatus, $blockUser);
        $this->pmCrumbs[] = [
            $this->c->Router->link('PMAction', $args),
            __([$blockStatus ? 'Unblock user %s crumb' : 'Block user %s crumb', $blockUser->username]),
        ];
        $this->pmCrumbs[] = [
            $this->linkPMBlk,
            __('Blocked users'),
        ];

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formBlock(array $args, bool $status, User $user): array
    {
        $btn = $status ? self::UNBLOCK : self::BLOCK;

        return [
            'action' => $this->c->Router->link('PMAction', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('PMAction', $args),
            ],
            'sets'   => [
                'info' => [
                    'info' => [
                        [
                            'value' => __([$status ? 'Unblock user %s' : 'Block user %s', $user->username]),
                        ],
                    ],
                ],
                'confirm' => [
                    'fields' => [
                        'confirm' => [
                            'type'    => 'checkbox',
                            'label'   => 'Confirm action',
                            'checked' => false,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                $btn     => [
                    'type'  => 'submit',
                    'value' => __($status ? 'Unblock' : 'Block'),
                ],
                'cancel' => [
                    'type'  => 'btn',
                    'value' => __('Cancel'),
                    'link'  => $this->linkBack,
                ],
            ],
        ];
    }
}
