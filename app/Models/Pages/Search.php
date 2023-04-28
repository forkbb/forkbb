<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\User\User;
use InvalidArgumentException;
use function \ForkBB\__;

class Search extends Page
{
    /**
     * Составление списка категорий/разделов для выбора
     */
    protected function calcList(): void
    {
        $cid     = null;
        $options = [];
        $idxs    = [];
        $root = $this->c->forums->get(0);
        if ($root instanceof Forum) {
            foreach ($this->c->forums->depthList($root, -1) as $f) {
                if ($cid !== $f->cat_id) {
                    $cid       = $f->cat_id;
                    $options[] = [__('Category prefix') . $f->cat_name];
                }

                $indent = \str_repeat(__('Forum indent'), $f->depth);

                if ($f->redirect_url) {
                    $options[] = [$f->id, $indent . __('Forum prefix') . $f->forum_name, true];
                } else {
                    $options[] = [$f->id, $indent . __('Forum prefix') . $f->forum_name];
                    $idxs[]    = $f->id;
                }
            }
        }
        $this->listOfIndexes  = $idxs;
        $this->listForOptions = $options;
    }

    /**
     * Расширенный поиск
     */
    public function viewAdvanced(array $args, string $method): Page
    {
        return $this->view($args, $method, true);
    }

    /**
     * Поиск
     */
    public function view(array $args, string $method, bool $advanced = false): Page
    {
        $this->c->Lang->load('validator');
        $this->c->Lang->load('search');
        $this->calcList();

        $marker = $advanced ? 'SearchAdvanced' : 'Search';
        $v      = null;

        if (
            'POST' === $method
            || isset($args['keywords'])
        ) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_query'  => [$this, 'vCheckQuery'],
                    'check_forums' => [$this, 'vCheckForums'],
                    'check_author' => [$this, 'vCheckAuthor'],
                ])->addRules([
                    'author'   => 'absent:*',
                    'forums'   => 'absent:*',
                    'serch_in' => 'absent:0|integer',
                    'sort_by'  => 'absent:0|integer',
                    'sort_dir' => 'absent:0|integer',
                    'show_as'  => 'absent:0|integer',
                ])->addArguments([
//                    'token' => $args,
                ])->addAliases([
                    'keywords' => 'Keyword search',
                    'author'   => 'Author search',
                    'forums'   => 'Forum search',
                    'serch_in' => 'Search in',
                    'sort_by'  => 'Sort by',
                    'sort_dir' => 'Sort order',
                    'show_as'  => 'Show as',
                ]);

            if ($advanced) {
                $v->addRules([
                    'author'   => 'required|string:trim|max:25|check_author',
                    'forums'   => 'check_forums',
                    'serch_in' => 'required|integer|in:0,1,2',
                    'sort_by'  => 'required|integer|in:0,1,2,3',
                    'sort_dir' => 'required|integer|in:0,1',
                    'show_as'  => 'required|integer|in:0,1',
                ]);
            }

            if ('POST' === $method) {
                $v->addRules([
                    'token'    => 'token:' . $marker,
                ]);
            }

            $v->addRules([
                'keywords'     => 'required|string:trim|max:100|check_query:' . $method,
            ]);

            if (
                'POST' === $method
                && $v->validation($_POST)
            ) {
                return $this->c->Redirect->page($marker, $v->getData());
            } elseif (
                'GET' === $method
                && $v->validation($args)
            ) {
                return $this->action(\array_merge($args, $v->getData(), ['action' => 'search']), $method, $advanced);
            }

            $this->fIswev = $v->getErrors();
        }

        if (! $this->c->config->insensitive()) {
            $this->fIswev = ['i', 'The search may be case sensitive'];
        }

        $this->fIndex       = 'search';
        $this->nameTpl      = 'search';
        $this->onlinePos    = 'search';
        $this->onlineDetail = null;
        $this->canonical    = $this->c->Router->link('Search');
        $this->robots       = 'noindex';
        $this->form         = $advanced ? $this->formSearchAdvanced($v) : $this->formSearch($v);
        $this->crumbs       = $this->crumbs();

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formSearch(Validator $v = null): array
    {
        return [
            'action' => $this->c->Router->link('Search'),
            'hidden' => [
                'token' => $this->c->Csrf->create('Search'),
            ],
            'sets'   => [
                'what' => [
                    'fields' => [
                        [
                            'type'  => 'info',
                            'value' => __(['<a href="%s">Advanced search</a>', $this->c->Router->link('SearchAdvanced')]),
                            'html'  => true,
                        ],
                        'keywords' => [
                            'class'     => ['w0'],
                            'type'      => 'text',
                            'maxlength' => '100',
                            'caption'   => 'Keyword search',
                            'value'     => $v->keywords ?? '',
                            'required'  => true,
                            'autofocus' => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'search' => [
                    'type'  => 'submit',
                    'value' => __('Search btn'),
                ],
            ],
        ];
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formSearchAdvanced(Validator $v = null): array
    {
        return [
            'action' => $this->c->Router->link('SearchAdvanced'),
            'hidden' => [
                'token' => $this->c->Csrf->create('SearchAdvanced'),
            ],
            'sets'   => [
                'what' => [
                    'fields' => [
                        [
                            'type'  => 'info',
                            'value' => __(['<a href="%s">Simple search</a>', $this->c->Router->link('Search')]),
                            'html'  => true,
                        ],
                        'keywords' => [
                            'class'     => ['w2'],
                            'type'      => 'text',
                            'maxlength' => '100',
                            'caption'   => 'Keyword search',
                            'value'     => $v->keywords ?? '',
                            'required'  => true,
                            'autofocus' => true,
                        ],
                        'author' => [
                            'class'     => ['w1'],
                            'type'      => 'text',
                            'maxlength' => '25',
                            'caption'   => 'Author search',
                            'value'     => $v->author ?? '*',
                            'required'  => true,
                        ],
                        [
                            'type'  => 'info',
                            'value' => __('Search info'),
                        ],
                    ],
                ],
                'where' => [
                    'legend' => 'Search in legend',
                    'fields' => [
                        'forums' => [
                            'class'   => ['w3'],
                            'type'    => 'multiselect',
                            'options' => $this->listForOptions,
                            'value'   => isset($v->forums) ? \explode('.', $v->forums) : null,
                            'caption' => 'Forum search',
                            'size'    => \min(\count($this->listForOptions), 10),
                        ],
                        'serch_in' => [
                            'class'   => ['w3'],
                            'type'    => 'select',
                            'options' => [
                                0 => __('Message and subject'),
                                1 => __('Message only'),
                                2 => __('Topic only'),
                            ],
                            'value'   => $v->serch_in ?? 0,
                            'caption' => 'Search in',
                        ],
                        [
                            'type'  => 'info',
                            'value' => __('Search in info'),
                        ],
                        [
                            'type'  => 'info',
                            'value' => __('Search multiple forums info'),
                        ],

                    ],
                ],
                'how' => [
                    'legend' => 'Search results legend',
                    'fields' => [
                        'sort_by' => [
                            'class'   => ['w4'],
                            'type'    => 'select',
                            'options' => [
                                0 => __('Sort by post time'),
                                1 => __('Sort by author'),
                                2 => __('Sort by subject'),
                                3 => __('Sort by forum'),
                            ],
                            'value'   => $v->sort_by ?? 0,
                            'caption' => 'Sort by',
                        ],
                        'sort_dir' => [
                            'class'   => ['w4'],
                            'type'    => 'radio',
                            'values'  => [
                                0 => __('Descending'),
                                1 => __('Ascending'),
                            ],
                            'value'   => $v->sort_dir ?? 0,
                            'caption' => 'Sort order',
                        ],
                        'show_as' => [
                            'class'   => ['w4'],
                            'type'    => 'radio',
                            'values'  => [
                                0 => __('Show as posts'),
                                1 => __('Show as topics'),
                            ],
                            'value'   => $v->show_as ?? 0,
                            'caption' => 'Show as',
                        ],
                        [
                            'type'  => 'info',
                            'value' => __('Search results info'),
                        ],
                    ],

                ],
            ],
            'btns'   => [
                'search' => [
                    'type'  => 'submit',
                    'value' => __('Search btn'),
                ],
            ],
        ];
    }

    /**
     * Дополнительная проверка строки запроса
     */
    public function vCheckQuery(Validator $v, string $query, string $method): string
    {
        if (empty($v->getErrors())) {
            $flood = $this->user->last_search && \time() - $this->user->last_search < $this->user->g_search_flood;

            if (
                'POST' !== $method
                || ! $flood
            ) {
                $search = $this->c->search;

                if (! $search->prepare($query)) {
                    $v->addError([$search->queryError, $search->queryText]);
                } else {

                    if ($this->c->search->execute($v, $this->listOfIndexes, $flood)) {
                        $flood = false;

                        if (empty($search->queryIds)) {
                            $v->addError('No hits', 'i');
                        }

                        if (
                            $search->queryNoCache
                            && $this->user->g_search_flood
                        ) {
                            $this->user->last_search = \time();
                            $this->c->users->update($this->user); //?????
                        }
                    }
                }
            }

            if ($flood) {
                $v->addError(['Flood message', $this->user->g_search_flood - \time() + $this->user->last_search]);
            }
        }

        return $query;
    }

    /**
     * Дополнительная проверка разделов
     */
    public function vCheckForums(Validator $v, mixed $forums): mixed
    {
        if ('*' !== $forums) {
            if (
                \is_string($forums)
                && \preg_match('%^\d+(?:\.\d+)*$%D', $forums)
            ) {
                $forums = \explode('.', $forums);
            } elseif (null === $forums) {
                $forums = '*';
            } elseif (! \is_array($forums)) {
                $v->addError('The :alias contains an invalid value');
                $forums = '*';
            }
        }

        if ('*' !== $forums) {
            if (! empty(\array_diff($forums, $this->listOfIndexes))) {
                $v->addError('The :alias contains an invalid value');
            }
            \sort($forums, SORT_NUMERIC);
            $forums = \implode('.', $forums);
        }

        return $forums;
    }

    /**
     * Дополнительная проверка автора
     */
    public function vCheckAuthor(Validator $v, string $name): string
    {
        $name = \preg_replace('%\*+%', '*', $name);

        if (
            '*' !== $name
            && ! \preg_match('%[\p{L}\p{N}]%', $name)
        ) {
            $v->addError('The :alias is not valid format');
        }

        return $name;
    }

    /**
     * Типовые действия
     */
    public function action(array $args, string $method, bool $advanced = false): Page
    {
        $this->c->Lang->load('search');

        $forum = $args['forum'] ?? 0;
        $forum = $this->c->forums->get($forum);
        if (! $forum instanceof Forum) {
            return $this->c->Message->message('Bad request');
        }

        $model        = $this->c->search;
        $model->page  = $args['page'] ?? 1;
        $action       = $args['action'];
        $asTopicsList = true;
        $list         = false;
        $uid          = $args['uid'] ?? null;
        $subIndex = [
            'topics_with_your_posts' => 'with-your-posts',
            'latest_active_topics'   => 'latest',
            'unanswered_topics'      => 'unanswered',
            'new'                    => 'new',
        ];
        switch ($action) {
            case 'search':
                if (1 === $model->showAs) {
                    $list          = $model->actionT($action, $forum);
                } else {
                    $list          = $model->actionP($action, $forum);
                    $asTopicsList  = false;
                }
                if ('*' === $args['author']) {
                    $model->name   = ['Search query: %s', $args['keywords']];
                } else {
                    $model->name   = ['Search query: %1$s and Author: %2$s', $args['keywords'], $args['author']];
                }
                $model->linkMarker = $advanced ? 'SearchAdvanced' : 'Search';
                $model->linkArgs   = $args;
                break;
            case 'new':
            case 'topics_with_your_posts':
                if ($this->user->isGuest) {
                    break;
                }
            case 'latest_active_topics':
            case 'unanswered_topics':
                if (isset($uid)) {
                    break;
                }
                $uid               = $this->user->id;
                $list              = $model->actionT($action, $forum, $uid);
                $model->name       = __('Quick search ' . $action);
                $model->linkMarker = 'SearchAction';
                if ($forum->id) {
                    $model->linkArgs = ['action' => $action, 'forum' => $forum->id];
                } else {
                    $model->linkArgs = ['action' => $action];
                }
                $this->fSubIndex   = $subIndex[$action];
                break;
            case 'posts':
                $asTopicsList      = false;
            case 'topics':
            case 'topics_subscriptions':
            case 'forums_subscriptions':
                if (! isset($uid)) {
                    break;
                }
                $user = $this->c->users->load($uid);
                if (
                    ! $user instanceof User
                    || $user->isGuest
                ) {
                    break;
                }
                if ('forums_subscriptions' == $action) {
                    $list = $model->actionF($action, $forum, $user->id);
                } elseif ($asTopicsList) {
                    $list = $model->actionT($action, $forum, $user->id);
                } else {
                    $list = $model->actionP($action, $forum, $user->id);
                }
                $model->name       = ['Quick search user ' . $action, $user->username];
                $model->linkMarker = 'SearchAction';
                if ($forum->id) {
                    $model->linkArgs = ['action' => $action, 'uid' => $user->id, 'forum' => $forum->id];
                } else {
                    $model->linkArgs = ['action' => $action, 'uid' => $user->id];
                }

                break;
#            default:
#                throw new InvalidArgumentException('Unknown action: ' . $action);
        }

        if (false === $list) {
            return $this->c->Message->message('Bad request');
        } elseif (empty($list)) {
            $this->fIswev = ['i', 'No hits'];

            return $this->view([], 'GET', true);
        }

        if ($asTopicsList) {
            $this->c->Lang->load('forum');

            $this->nameTpl = 'forum';

            if ('forums_subscriptions' == $action) {
                $this->c->Lang->load('subforums');

                $model->subforums = $list;
            } else {
                $this->topics = $list;
            }
        } else {
            $this->c->Lang->load('topic');

            $this->nameTpl = 'topic_in_search';
            $this->posts   = $list;
        }

        $this->fIndex        = self::FI_SRCH;
        $this->onlinePos     = 'search';
        $this->robots        = 'noindex';
        $this->model         = $model;
        $this->crumbs        = $this->crumbs($model);
        $this->searchMode    = true;

        return $this;
    }

    /**
     * Возвращает массив хлебных крошек
     * Заполняет массив титула страницы
     */
    protected function crumbs(mixed ...$crumbs): array
    {
        $crumbs[] = [$this->c->Router->link('Search'), 'Search'];

        return parent::crumbs(...$crumbs);
    }
}
