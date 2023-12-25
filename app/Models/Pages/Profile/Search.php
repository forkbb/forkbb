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
use ForkBB\Models\Forum\Forum;
use function \ForkBB\__;

class Search extends Profile
{
    /**
     * Подготавливает данные для шаблона конфигурации поиска
     */
    public function config(array $args, string $method): Page
    {
        if (
            false === $this->initProfile($args['id'])
            || ! $this->rules->configureSearch
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('validator');

        if ($this->rules->my) {
            $this->forumManager = $this->c->forums;
        } else {
            $this->forumManager = $this->c->ForumManager->init($this->c->groups->get($this->curUser->group_id));
        }

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'    => 'token:EditUserSearch',
                    'follow.*' => 'integer|in:' . \implode(',', \array_keys($this->curForums)),
                    'save'     => 'required|string',
                ])->addAliases([
                ])->addArguments([
                    'token'    => $args,
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                if (! empty($v->follow)) {
                    $unfollow = [];

                    foreach ($this->curForums as $id => $forum) {
                        if (empty($forum->redirect_url)) {
                            $unfollow[$id] = $id;
                        }
                    }

                    $unfollow = \array_diff($unfollow, $v->follow);

                    \sort($unfollow, \SORT_NUMERIC);

                    $unfollow = \implode(',', $unfollow);

                    while (
                        \strlen($unfollow) > 255
                        && false !== ($pos = \strrpos($unfollow, ','))
                    ) {
                        $unfollow = \substr($unfollow, 0, $pos);
                    }

                    $this->curUser->unfollowed_f = $unfollow;

                    $this->c->users->update($this->curUser);
                }

                return $this->c->Redirect->page('EditUserSearch', $args)->message('Update search config redirect', FORK_MESS_SUCC);
            }

            $this->fIswev = $v->getErrors();
        }

        $this->identifier      = ['profile', 'profile-search'];
        $this->crumbs          = $this->crumbs(
            [
                $this->c->Router->link('EditUserSearch', $args),
                'Search config',
            ],
            [
                $this->c->Router->link('EditUserBoardConfig', $args),
                'Board configuration',
            ]
        );
        $this->form            = $this->form($args);
        $this->actionBtns      = $this->btns('config');
        $this->profileIdSuffix = '-search';

        return $this;
    }

    /**
     * Возвращает список доступных разделов для пользователя текущего профиля
     */
    protected function getcurForums(): array
    {
        $root = $this->forumManager->get(0);

        return $root instanceof Forum ? $root->descendants : [];
    }

    /**
     * Возвращает id неотслеживаемых форумов в виде массива
     */
    protected function getcurUnfollowed(): array
    {
        $raw = $this->curUser->unfollowed_f;

        if (empty($raw)) {
            return [];
        }

        $result = [];

        foreach (\explode(',', $raw) as $id) {
            $id = (int) $id;

            if (
                $id > 0
                && isset($this->curForums[$id])
            ) {
                $result[$id] = $id;
            }
        }

        return $result;
    }

    /**
     * Создает массив данных для формы
     */
    protected function form(array $args): array
    {
        $form = [
            'action' => $this->c->Router->link('EditUserSearch', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('EditUserSearch', $args),
            ],
            'sets'   => [],
            'btns'   => [
                'save' => [
                    'type'  => 'submit',
                    'value' => __('Save'),
                ],
            ],
        ];

        $root = $this->forumManager->get(0);

        if ($root instanceof Forum) {
            $list = $this->forumManager->depthList($root, 0);
            $cid  = null;

            $form['sets']['start']['inform'] = [
                [
                    'message' => ['Select followed forums for %s and %s:', __('Latest active topics'), __('Unanswered topics')],
                ],
            ];

            foreach ($list as $forum) {
                if ($cid !== $forum->cat_id) {
                    $form['sets']["category{$forum->cat_id}-info"] = [
                        'inform' => [
                            [
                                'message' => $forum->cat_name,
                            ],
                        ],
                    ];
                    $cid = $forum->cat_id;
                }

                $fields = [];
                $fields["name{$forum->id}"] = [
                    'class'   => ['modforum', 'name', 'depth' . $forum->depth],
                    'type'    => 'label',
                    'value'   => $forum->forum_name,
                    'caption' => 'Forum label',
                    'for'     => "follow[{$forum->id}]",
                ];
                $fields["follow[{$forum->id}]"] = [
                    'class'    => ['modforum', 'moderator'],
                    'type'     => 'checkbox',
                    'value'    => $forum->id,
                    'checked'  => ! isset($this->curUnfollowed[$forum->id]),
                    'disabled' => ! empty($this->curForums[$forum->id]->redirect_url),
                    'caption'  => 'Follow label',
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
