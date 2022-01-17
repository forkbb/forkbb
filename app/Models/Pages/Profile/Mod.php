<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Core\Image;
use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Profile;
use ForkBB\Models\User\User;
use ForkBB\Models\Forum\Forum;
use function \ForkBB\__;

class Mod extends Profile
{
    /**
     * Подготавливает данные для шаблона конфигурации прав модератора
     */
    public function moderation(array $args, string $method): Page
    {
        if (
            false === $this->initProfile($args['id'])
            || ! $this->rules->confModer
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('validator');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'       => 'token:EditUserModeration',
                    'moderator.*' => 'integer|in:' . \implode(',', \array_keys($this->curForums)),
                    'save'        => 'required|string',
                ])->addAliases([
                ])->addArguments([
                    'token'       => $args,
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                foreach ($this->c->forums->get(0)->descendants as $forum) {
                    if (
                        isset($v->moderator[$forum->id])
                        && $v->moderator[$forum->id] === $forum->id
                    ) {
                        $forum->modAdd($this->curUser);
                    } else {
                        $forum->modDelete($this->curUser);
                    }
                    $this->c->forums->update($forum);
                }

                $this->c->forums->reset();

                return $this->c->Redirect->page('EditUserModeration', $args)->message('Update rights redirect');
            }

            $this->fIswev = $v->getErrors();
        }

        $this->crumbs     = $this->crumbs(
            [
                $this->c->Router->link('EditUserModeration', $args),
                'Moderator rights',
            ],
            [
                $this->c->Router->link('EditUserProfile', $args),
                'Editing profile',
            ]
        );
        $this->form       = $this->form($args);
        $this->actionBtns = $this->btns('edit');

        return $this;
    }

    /**
     * Возвращает список доступных разделов для пользователя текущего профиля
     */
    protected function getcurForums(): array
    {
        $root = $this->c->ForumManager->init($this->c->groups->get($this->curUser->group_id))->get(0);

        return $root instanceof Forum ? $root->descendants : [];
    }

    /**
     * Создает массив данных для формы
     */
    protected function form(array $args): array
    {
        $form = [
            'action' => $this->c->Router->link('EditUserModeration', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('EditUserModeration', $args),
            ],
            'sets'   => [],
            'btns'   => [
                'save' => [
                    'type'  => 'submit',
                    'value' => __('Save'),
                ],
            ],
        ];

        $root = $this->c->forums->get(0);

        if ($root instanceof Forum) {
            $list = $this->c->forums->depthList($root, 0);
            $cid  = null;

            foreach ($list as $forum) {
                if ($cid !== $forum->cat_id) {
                    $form['sets']["category{$forum->cat_id}-info"] = [
                        'info' => [
                            [
                                'value' => $forum->cat_name,
                            ],
                        ],
                    ];
                    $cid = $forum->cat_id;
                }

                $fields = [];
                $fields["name{$forum->id}"] = [
                    'class'   => ['modforum', 'name', 'depth' . $forum->depth],
                    'type'    => 'str',
                    'value'   => $forum->forum_name,
                    'caption' => 'Forum label',
                ];
                $fields["moderator[{$forum->id}]"] = [
                    'class'    => ['modforum', 'moderator'],
                    'type'     => 'checkbox',
                    'value'    => $forum->id,
                    'checked'  => isset($this->curForums[$forum->id]) && $this->curUser->isModerator($forum),
                    'disabled' => ! isset($this->curForums[$forum->id]) || '' != $this->curForums[$forum->id]->redirect_url,
                    'caption'  => 'Moderator label',
                ];
                $form['sets']["forum{$forum->id}"] = [
                    'class'  => ['modforum'],
                    'legend' => $forum->cat_name . ' / ' . $forum->forum_name,
                    'fields' => $fields,
                ];
            }
        }

        return $form;
    }
}
