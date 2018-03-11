<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Core\Validator;
use ForkBB\Models\Forum\Model as Forum;
use InvalidArgumentException;

class Search extends Page
{
    use CrumbTrait;

    /**
     * Составление списка категорий/разделов для выбора
     */
    protected function calcList()
    {
        $cid     = null;
        $options = [];
        $idxs    = [];
        $root = $this->c->forums->get(0);
        if ($root instanceof Forum) {
            foreach ($this->c->forums->depthList($root, -1) as $f) {
                if ($cid !== $f->cat_id) {
                    $cid       = $f->cat_id;
                    $options[] = [\ForkBB\__('Category prefix') . $f->cat_name];
                }

                $indent = \str_repeat(\ForkBB\__('Forum indent'), $f->depth);

                if ($f->redirect_url) {
                    $options[] = [$f->id, $indent . \ForkBB\__('Forum prefix') . $f->forum_name, true];
                } else {
                    $options[] = [$f->id, $indent . \ForkBB\__('Forum prefix') . $f->forum_name];
                    $idxs[]    = $f->id;
                }
            }
        }
        $this->listOfIndexes  = $idxs;
        $this->listForOptions = $options;
    }

    /**
     * Расширенный поиск
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function viewAdvanced(array $args, $method)
    {
        return $this->view($args, $method, true);
    }

    /**
     * Поиск
     *
     * @param array $args
     * @param string $method
     * @param bool $advanced
     *
     * @return Page
     */
    public function view(array $args, $method, $advanced = false)
    {
        $this->c->Lang->load('search');
        $this->calcList();

        $marker = $advanced ? 'SearchAdvanced' : 'Search';

        $v = null;
        if ('POST' === $method || isset($args['keywords'])) {
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

            if ('POST' === $method && $v->validation($_POST)) {
                return $this->c->Redirect->page($marker, $v->getData());
            } elseif ('GET' === $method && $v->validation($args)) {
                return $this->action(\array_merge($args, $v->getData(), ['action' => 'search']), $method, $advanced);
            }

            $this->fIswev = $v->getErrors();
        }

        $form = [
            'action' => $this->c->Router->link($marker),
            'hidden' => [
                'token' => $this->c->Csrf->create($marker),
            ],
            'sets' => [],
            'btns'   => [
                'search'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Search btn'),
                    'accesskey' => 's',
                ],
            ],
        ];

        if ($advanced) {
            $form['sets'][] = [
                'fields' => [
                    [
                        'type'      => 'info',
                        'value'     => \ForkBB\__('<a href="%s">Simple search</a>', $this->c->Router->link('Search')),
                        'html'      => true,
                    ],
                    'keywords' => [
                        'dl'        => 'w2',
                        'type'      => 'text',
                        'maxlength' => 100,
                        'title'     => \ForkBB\__('Keyword search'),
                        'value'     => $v ? $v->keywords : '',
                        'required'  => true,
                        'autofocus' => true,
                    ],
                    'author' => [
                        'dl'        => 'w1',
                        'type'      => 'text',
                        'maxlength' => 25,
                        'title'     => \ForkBB\__('Author search'),
                        'value'     => $v ? $v->author : '*',
                        'required'  => true,
                    ],
                    [
                        'type'      => 'info',
                        'value'     => \ForkBB\__('Search info'),
                    ],
                ],
            ];
            $form['sets'][] = [
                'legend' => \ForkBB\__('Search in legend'),
                'fields' => [
                    'forums' => [
                        'dl'      => 'w3',
                        'type'    => 'multiselect',
                        'options' => $this->listForOptions,
                        'value'   => $v ? \explode('.', $v->forums) : null,
                        'title'   => \ForkBB\__('Forum search'),
                        'size'    => \min(\count($this->listForOptions), 10),
                    ],
                    'serch_in' => [
                        'dl'      => 'w3',
                        'type'    => 'select',
                        'options' => [
                            0 => \ForkBB\__('Message and subject'),
                            1 => \ForkBB\__('Message only'),
                            2 => \ForkBB\__('Topic only'),
                        ],
                        'value'   => $v ? $v->serch_in : 0,
                        'title'   => \ForkBB\__('Search in'),
                    ],
                    [
                        'type'    => 'info',
                        'value'   => \ForkBB\__('Search in info'),
                    ],
                    [
                        'type'    => 'info',
                        'value'   => \ForkBB\__('Search multiple forums info'),
                    ],

                ],
            ];
            $form['sets'][] = [
                'legend' => \ForkBB\__('Search results legend'),
                'fields' => [
                    'sort_by' => [
                        'dl'      => 'w4',
                        'type'    => 'select',
                        'options' => [
                            0 => \ForkBB\__('Sort by post time'),
                            1 => \ForkBB\__('Sort by author'),
                            2 => \ForkBB\__('Sort by subject'),
                            3 => \ForkBB\__('Sort by forum'),
                        ],
                        'value'   => $v ? $v->sort_by : 0,
                        'title'   => \ForkBB\__('Sort by'),
                    ],
                    'sort_dir' => [
                        'dl'      => 'w4',
                        'type'    => 'radio',
                        'values'  => [
                            0 => \ForkBB\__('Descending'),
                            1 => \ForkBB\__('Ascending'),
                        ],
                        'value'   => $v ? $v->sort_dir : 0,
                        'title'   => \ForkBB\__('Sort order'),
                    ],
                    'show_as' => [
                        'dl'      => 'w4',
                        'type'    => 'radio',
                        'values'  => [
                            0 => \ForkBB\__('Show as posts'),
                            1 => \ForkBB\__('Show as topics'),
                        ],
                        'value'   => $v ? $v->show_as : 0,
                        'title'   => \ForkBB\__('Show as'),
                    ],
                    [
                        'type'    => 'info',
                        'value'   => \ForkBB\__('Search results info'),
                    ],
                ],

            ];
        } else {
            $form['sets'][] = [
                'fields' => [
                    [
                        'type'      => 'info',
                        'value'     => \ForkBB\__('<a href="%s">Advanced search</a>', $this->c->Router->link('SearchAdvanced')),
                        'html'      => true,
                    ],
                    'keywords' => [
                        'type'      => 'text',
                        'maxlength' => 100,
                        'title'     => \ForkBB\__('Keyword search'),
                        'value'     => $v ? $v->keywords : '',
                        'required'  => true,
                        'autofocus' => true,
                    ],
                ],
            ];
        }

        $this->fIndex       = 'search';
        $this->nameTpl      = 'search';
        $this->onlinePos    = 'search';
        $this->canonical    = $this->c->Router->link('Search');
        $this->robots       = 'noindex';
        $this->form         = $form;
        $this->crumbs       = $this->crumbs([$this->c->Router->link('Search'), \ForkBB\__('Search')]);

        return $this;
    }

    /**
     * Дополнительная проверка строки запроса
     *
     * @param Validator $v
     * @param string $query
     * @param string $method
     *
     * @return string
     */
    public function vCheckQuery(Validator $v, $query, $method)
    {
        if (empty($v->getErrors())) {
            $flood = $this->user->last_search && \time() - $this->user->last_search < $this->user->g_search_flood;

            if ('POST' !== $method || ! $flood) {
                $search = $this->c->search;

                if (! $search->prepare($query)) {
                    $v->addError(\ForkBB\__($search->queryError, $search->queryText));
                } else {

                    if ($this->c->search->execute($v, $this->listOfIndexes, $flood)) {
                        $flood = false;

                        if (empty($search->queryIds)) {
                            $v->addError('No hits', 'i');
                        }

                        if ($search->queryNoCache && $this->user->g_search_flood) {
                            $this->user->last_search = \time();
                            $this->c->users->update($this->user); //?????
                        }
                    }
                }
            }

            if ($flood) {
                $v->addError(\ForkBB\__('Search flood', $this->user->g_search_flood, $this->user->g_search_flood - \time() + $this->user->last_search));
            }
        }

        return $query;
    }

    /**
     * Дополнительная проверка разделов
     *
     * @param Validator $v
     * @param string|array $forums
     *
     * @return string
     */
    public function vCheckForums(Validator $v, $forums)
    {
        if ('*' !== $forums) {
            if (\is_string($forums) && \preg_match('%^\d+(?:\.\d+)*$%D', $forums)) {
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
     *
     * @param Validator $v
     * @param string|array $forums
     *
     * @return string
     */
    public function vCheckAuthor(Validator $v, $name)
    {
        $name = \preg_replace('%\*+%', '*', $name);

        if ('*' !== $name && ! \preg_match('%[\p{L}\p{N}]%', $name)) {
            $v->addError('The :alias is not valid format');
        }

        return $name;
    }

    /**
     * Типовые действия
     *
     * @param array $args
     * @param string $method
     * @param bool $advanced
     *
     * @throws InvalidArgumentException
     *
     * @return Page
     */
    public function action(array $args, $method, $advanced = false)
    {
        $this->c->Lang->load('search');

        $model        = $this->c->search;
        $model->page  = isset($args['page']) ? (int) $args['page'] : 1;
        $action       = $args['action'];
        $asTopicsList = true;
        $list         = false;
        $uid          = isset($args['uid']) ? (int) $args['uid'] : null;
        switch ($action) {
            case 'search':
                if (1 === $model->showAs) {
                    $list = $model->actionT($action);
                } else {
                    $list = $model->actionP($action);
                    $asTopicsList  = false;
                }
                if ('*' === $args['author']) {
                    $model->name   = \ForkBB\__('Search query: %s', $args['keywords']);
                } else {
                    $model->name   = \ForkBB\__('Search query: %1$s and Author: %2$s', $args['keywords'], $args['author']);
                }
                $model->linkMarker = $advanced ? 'SearchAdvanced' : 'Search';
                $model->linkArgs   = $args;
                break;
            case 'topics_with_your_posts':
                if ($this->user->isGuest) {
                    break;
                }
                $uid               = $this->user->id;
            case 'latest_active_topics':
            case 'unanswered_topics':
                $list              = $model->actionT($action, $uid);
                $model->name       = \ForkBB\__('Quick search ' . $action);
                $model->linkMarker = 'SearchAction';
                $model->linkArgs   = ['action' => $action];
                break;
#            default:
#                throw new InvalidArgumentException('Unknown action: ' . $action);
        }

        if (false === $list) {
            return $this->c->Message->message('Bad request');
        } elseif (empty($list)) {
            $this->fIswev = ['i', \ForkBB\__('No hits')];
            return $this->view(['advanced' => 'advanced'], 'GET');
        }

        if ($asTopicsList) {
            $this->c->Lang->load('forum');

            $this->nameTpl   = 'forum';
            $this->topics    = $list;
        } else {
            $this->c->Lang->load('topic');

            $this->nameTpl   = 'topic_in_search';
            $this->posts     = $list;
        }

        $this->fIndex        = 'search';
        $this->onlinePos     = 'search';
        $this->robots        = 'noindex';
        $this->model         = $model;
        $this->crumbs        = $this->crumbs($model);
        $this->searchMode    = true;

        return $this;
    }
}
