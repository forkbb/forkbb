<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Models\Post\Post;
use function \ForkBB\__;
use function \ForkBB\dt;

class Delete extends Page
{
    /**
     * Удаление сообщения/темы
     */
    public function delete(array $args, string $method): Page
    {
        $post = $this->c->posts->load($args['id']);

        if (
            empty($post)
            || ! $post->canDelete
        ) {
            return $this->c->Message->message('Bad request');
        }

        $topic       = $post->parent;
        $deleteTopic = $post->id === $topic->first_post_id;

        $this->c->Lang->load('validator');
        $this->c->Lang->load('delete');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'   => 'token:DeletePost',
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
                return $this->c->Redirect->page('ViewPost', $args)->message('No confirm redirect', FORK_MESS_WARN);
            }

            if ($deleteTopic) {
                $redirect = $this->c->Redirect
                    ->url($topic->parent->link)
                    ->message('Topic del redirect', FORK_MESS_SUCC);

                $this->c->topics->delete($topic);

            } else {
                $redirect = $this->c->Redirect
                    ->page('ViewPost', ['id' => $this->c->posts->previousPost($post)])
                    ->message('Post del redirect', FORK_MESS_SUCC);

                $this->c->posts->delete($post);
            }

            return $redirect;
        }

        $this->c->Parser; // предзагрузка
        $post->user; // предзагрузка

        $this->identifier = 'delete';
        $this->nameTpl    = 'post';
        $this->onlinePos  = 'topic-' . $topic->id;
//        $this->canonical  = $post->linkDelete;
        $this->robots     = 'noindex';
        $this->formTitle  = $deleteTopic ? 'Delete topic' : 'Delete post';
        $this->crumbs     = $this->crumbs($this->formTitle, $topic);
        $this->posts      = [$post];
        $this->postsTitle = $deleteTopic ? 'Delete topic info' : 'Delete info';
        $this->form       = $this->formDelete($args, $post, $deleteTopic);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formDelete(array $args, Post $post, bool $deleteTopic): array
    {
        return [
            'action' => $this->c->Router->link(
                'DeletePost',
                [
                    'id' => $post->id,
                ]
            ),
            'hidden' => [
                'token' => $this->c->Csrf->create(
                    'DeletePost',
                    [
                        'id' => $post->id,
                    ]
                ),
            ],
            'sets'   => [
                'info' => [
                    'inform' => [
                        [
                            'message' => ['Topic %s', $post->parent->name],
                        ],
                        [
                            'message' => [$deleteTopic ? 'Topic by' : 'Reply by', $post->poster, dt($post->posted)],
                        ],
                    ],
                ],
                'confirm' => [
                    'fields' => [
                        'confirm' => [
                            'type'    => 'checkbox',
                            'label'   => $deleteTopic ? 'Confirm delete topic' : 'Confirm delete post',
                            'checked' => false,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'delete'  => [
                    'type'  => 'submit',
                    'value' => __($deleteTopic ? 'Delete  topic' : 'Delete  post'),
                ],
                'cancel'  => [
                    'type'  => 'btn',
                    'value' => __('Cancel'),
                    'href'  => $this->c->Router->link('ViewPost', $args),
                ],
            ],
        ];
    }
}
