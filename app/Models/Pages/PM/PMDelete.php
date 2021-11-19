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

class PMDelete extends AbstractPM
{
    /**
     * Удаление сообщения/темы
     */
    public function delete(array $args, string $method): Page
    {
        switch ($args['more2']) {
            case Cnst::ACTION_TOPIC:
                $deleteTopic = true;
                $topic       = $this->pms->load(Cnst::PTOPIC, $args['more1']);

                if (! $topic instanceof PTopic) {
                    return $this->c->Message->message('Bad request');
                }

                $post        = $this->pms->load(Cnst::PPOST, $topic->first_post_id);

                break;
            case Cnst::ACTION_POST:
                $deleteTopic = false;
                $post        = $this->pms->load(Cnst::PPOST, $args['more1']);

                if (
                    ! $post instanceof PPost
                    || ! $post->canDelete
                ) {
                    return $this->c->Message->message('Bad request');
                }

                $topic       = $post->parent;

                break;
            default:
                return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('validator');

        $this->pms->area = $this->pms->inArea($topic);

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'   => 'token:PMAction',
                    'confirm' => 'checkbox',
                    'delete'  => 'required|string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (
                ! $v->validation($_POST)
                || '1' !== $v->confirm
            ) {
                return $this->c->Redirect->url($post->link)->message('No confirm redirect');
            }

            if ($deleteTopic) {
                if ($this->pms->numCurrent + $this->pms->numArchive > 1) {
                    $second = $this->pms->second;
                } else {
                    $second = null;
                }

                $redirect = $this->c->Redirect
                    ->page('PMAction', ['second' => $second, 'action' => $this->pms->area])
                    ->message('Dialogue del redirect');

                $topic->status = Cnst::PT_DELETED;

                $this->pms->delete($topic);
            } else {
                $redirect = $this->c->Redirect
                    ->url($post->linkPrevious)
                    ->message('Message del redirect');

                $this->pms->delete($post);
            }

            return $redirect;
        }

        $this->targetUser = $topic->ztUser;
        $this->pmIndex    = $this->pms->area;
        $this->nameTpl    = 'pm/post';
        $this->formTitle  = $deleteTopic ? 'Delete PT title' : 'Delete PM title';
        $this->form       = $this->formDelete($args, $post, $deleteTopic);
        $this->postsTitle = $deleteTopic ? 'Delete dialogue info' : 'Delete info';
        $this->posts      = [$post];
        $this->pmCrumbs[] = [
            $this->c->Router->link('PMAction', $args),
            __($deleteTopic ? 'Delete  dialogue' : 'Delete  message'),
        ];
        $this->pmCrumbs[] = $topic;

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formDelete(array $args, PPost $post, bool $deleteTopic): array
    {
        return [
            'action' => $this->c->Router->link('PMAction', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('PMAction', $args),
            ],
            'sets'   => [
                'info' => [
                    'info' => [
                        [
                            'value'   => __(['Dialogue %s', $post->parent->name]),
                        ],
                        [
                            'value'   => __([
                                $deleteTopic ? 'Dialogue by %1$s (%2$s)' : 'Message by %1$s (%2$s)',
                                $post->poster,
                                \ForkBB\dt($post->posted)
                            ]),
                            'html'    => true,
                        ],
                    ],
                ],
                'confirm' => [
                    'fields' => [
                        'confirm' => [
                            'type'    => 'checkbox',
                            'label'   => 'Confirm action',
                            'value'   => '1',
                            'checked' => false,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'delete'  => [
                    'type'  => 'submit',
                    'value' => __($deleteTopic ? 'Delete dialogue' : 'Delete message'),
                ],
                'cancel'  => [
                    'type'  => 'btn',
                    'value' => __('Cancel'),
                    'link'  => $post->link,
                ],
            ],
        ];
    }
}
