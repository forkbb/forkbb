<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;

class Delete extends Page
{
    use CrumbTrait;

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
            $v = $this->c->Validator->setRules([
                'token'   => 'token:DeletePost',
                'confirm' => 'integer',
                'delete'  => 'string',
                'cancel'  => 'string',
            ])->setAliases([
            ])->setArguments([
                'token' => $args,
            ]);
    
            if (! $v->validation($_POST) || null === $v->delete) {
                return $this->c->Redirect->page('ViewPost', $args)->message('Cancel redirect');
            } elseif ($v->confirm !== 1) {
                return $this->c->Redirect->page('ViewPost', $args)->message('No confirm redirect');
            }
    
            $this->c->DB->beginTransaction();
    
            if ($deleteTopic) {
                $redirect = $this->c->Redirect->page('Forum', ['id' => $topic->forum_id])->message('Topic del redirect');
                $this->c->topics->delete($topic);
            } else {
                $redirect = $this->c->Redirect->page('ViewPost', ['id' => $this->c->posts->previousPost($post)])->message('Post del redirect');
                $this->c->posts->delete($post);
            }
    
            $this->c->DB->commit();
    
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
        $this->form       = [
            'action' => $this->c->Router->link('DeletePost', ['id' => $post->id]),
            'hidden' => [
                'token' => $this->c->Csrf->create('DeletePost', ['id' => $post->id]),
            ],
            'sets'   => [
                [
                    'info' => [
                        'info1' => [
                            'type'    => '', //????
                            'value'   => \ForkBB\__('Topic') . ' «' . \ForkBB\cens($topic->subject) . '»',
                        ],
                        'info2' => [
                            'type'    => '', //????
                            'value'   => \ForkBB\__($deleteTopic ? 'Topic by' : 'Reply by', $post->poster, \ForkBB\dt($post->posted)),
                            'html'    => true,
                        ],
                    ],
                ],
                [
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
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Cancel'),
                ],
            ],
        ];

        return $this;
    }
}
