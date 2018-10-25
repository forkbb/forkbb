<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Models\Post\Model as Post;

class Delete extends Page
{
    /**
     * Удаление сообщения/темы
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function delete(array $args, $method)
    {
        $post = $this->c->posts->load((int) $args['id']);

        if (empty($post) || ! $post->canDelete) {
            return $this->c->Message->message('Bad request');
        }

        $topic       = $post->parent;
        $deleteTopic = $post->id === $topic->first_post_id;

        $this->c->Lang->load('delete');

        if ($method === 'POST') {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'   => 'token:DeletePost',
                    'confirm' => 'integer', // ????
                    'delete'  => 'string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (! $v->validation($_POST) || $v->confirm !== 1) {
                return $this->c->Redirect->page('ViewPost', $args)->message('No confirm redirect');
            }

            if ($deleteTopic) {
                $redirect = $this->c->Redirect->page('Forum', ['id' => $topic->forum_id, 'name' => $topic->parent->forum_name])->message('Topic del redirect');
                $this->c->topics->delete($topic);
            } else {
                $redirect = $this->c->Redirect->page('ViewPost', ['id' => $this->c->posts->previousPost($post)])->message('Post del redirect');
                $this->c->posts->delete($post);
            }

            return $redirect;
        }

        $this->nameTpl    = 'post';
        $this->onlinePos  = 'topic-' . $topic->id;
        $this->canonical  = $post->linkDelete;
        $this->robots     = 'noindex';
        $this->formTitle  = \ForkBB\__($deleteTopic ? 'Delete topic' : 'Delete post');
        $this->crumbs     = $this->crumbs($this->formTitle, $topic);
        $this->posts      = [$post];
        $this->postsTitle = \ForkBB\__('Delete info');
        $this->form       = $this->formDelete($args, $post, $deleteTopic);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     *
     * @param array $args
     * @param Post $post
     * @param bool $deleteTopic
     *
     * @return array
     */
    protected function formDelete(array $args, Post $post, $deleteTopic)
    {
        return [
            'action' => $this->c->Router->link('DeletePost', ['id' => $post->id]),
            'hidden' => [
                'token' => $this->c->Csrf->create('DeletePost', ['id' => $post->id]),
            ],
            'sets'   => [
                'info' => [
                    'info' => [
                        'info1' => [
                            'type'    => '', //????
                            'value'   => \ForkBB\__('Topic') . ' «' . \ForkBB\cens($post->parent->subject) . '»',
                        ],
                        'info2' => [
                            'type'    => '', //????
                            'value'   => \ForkBB\__($deleteTopic ? 'Topic by' : 'Reply by', $post->poster, \ForkBB\dt($post->posted)),
                            'html'    => true,
                        ],
                    ],
                ],
                'confirm' => [
                    'fields' => [
                        'confirm' => [
                            'type'    => 'checkbox',
                            'label'   => \ForkBB\__($deleteTopic ? 'Confirm delete topic' : 'Confirm delete post'),
                            'value'   => '1',
                            'checked' => false,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'delete'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__($deleteTopic ? 'Delete  topic' : 'Delete  post'),
                    'accesskey' => 'd',
                ],
                'cancel'  => [
                    'type'      => 'btn',
                    'value'     => \ForkBB\__('Cancel'),
                    'link'      => $this->c->Router->link('ViewPost', $args),
                ],
            ],
        ];
    }
}
