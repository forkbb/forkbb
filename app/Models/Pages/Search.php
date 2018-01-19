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

                $indent = str_repeat(\ForkBB\__('Forum indent'), $f->depth);

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
     * Поиск
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function view(array $args, $method)
    {
        $this->c->Lang->load('search');
        $this->calcList();

        $v = null;
        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_query' => [$this, 'vCheckQuery'],
                ])->addRules([
                    'token'    => 'token:Search',
                    'keywords' => 'required|string:trim|max:100|check_query',
                    'author'   => 'absent',
                    'forums'   => 'absent',
                    'serch_in' => 'absent',
                    'sort_by'  => 'absent',
                    'sort_dir' => 'absent',
                    'show_as'  => 'absent',
                ])->addArguments([
                    'token' => $args,
                ])->addAliases([
                    'keywords' => 'Keyword search',
                    'author'   => 'Author search',
                    'forums'   => 'Forum search',
                    'serch_in' => 'Search in',
                    'sort_by'  => 'Sort by',
                    'sort_dir' => 'Sort order',
                    'show_as'  => 'Show as',
                ]);

            if (isset($args['advanced'])) {
                $v->addRules([
                    'author'   => 'string:trim|max:25',
                    'forums'   => 'array',
                    'forums.*' => 'integer|in:' . implode(',', $this->listOfIndexes),
                    'serch_in' => 'required|integer|in:0,1,2',
                    'sort_by'  => 'required|integer|in:0,1,2,3',
                    'sort_dir' => 'required|integer|in:0,1',
                    'show_as'  => 'required|integer|in:0,1',
                ]);
            }

            if ($v->validation($_POST)) {
                $forums = $v->forums;

                if (empty($forums) && ! $this->c->user->isAdmin) {
                    $forums = $this->listOfIndexes;
                }

                $options = [
                    'keywords' => $v->keywords,
                    'author'   => (string) $v->author,
                    'forums'   => $forums,
                    'serch_in' => ['all', 'posts', 'topics'][(int) $v->serch_in],
                    'sort_by'  => ['post', 'author', 'subject', 'forum'][(int) $v->sort_by],
                    'sort_dir' => ['desc', 'asc'][(int) $v->sort_dir],
                    'show_as'  => ['posts', 'topics'][(int) $v->show_as],
                ];

                $result = $this->c->search->execute($options);

                $user = $this->c->user;
                if ($user->g_search_flood) {
                    $user->last_search = time();
                    $this->c->users->update($user); //?????
                }

                if (empty($result)) {
                    $this->fIswev = ['i', \ForkBB\__('No hits')];
                }
            }

            $this->fIswev = $v->getErrors();
        }

        $form = [
            'action' => $this->c->Router->link('Search', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('Search', $args),
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

        if (isset($args['advanced'])) {
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
                        'value'     => $v ? $v->author : '',
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
                        'value'   => $v ? $v->forums : null,
                        'title'   => \ForkBB\__('Forum search'),
                        'size'    => min(count($this->listForOptions), 10),
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
                        'value'     => \ForkBB\__('<a href="%s">Advanced search</a>', $this->c->Router->link('Search', ['advanced' => 'advanced'])),
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
     *
     * @return string
     */
    public function vCheckQuery(Validator $v, $query)
    {
        $user = $this->c->user;

        if ($user->last_search && time() - $user->last_search < $user->g_search_flood) {
            $v->addError(\ForkBB\__('Search flood', $user->g_search_flood, $user->g_search_flood - time() + $user->last_search));
        } else {
            $search = $this->c->search;

            if (! $search->prepare($query)) {
                $v->addError(\ForkBB\__($search->queryError, $search->queryText));
            }
        }

        return $query;
    }

    /**
     * Типовые действия
     *
     * @param array $args
     * @param string $method
     *
     * @throws InvalidArgumentException
     *
     * @return Page
     */
    public function action(array $args, $method)
    {
        $this->c->Lang->load('search');

        $model       = $this->c->search;
        $model->page = isset($args['page']) ? (int) $args['page'] : 1;
        $action      = $args['action'];
        switch ($action) {
            case 'last':
            case 'unanswered':
                $list = $model->actionT($action);
                $model->name       = \ForkBB\__('Quick search show_' . $action);
                $model->linkMarker = 'SearchAction';
                $model->linkArgs   = ['action' => $action];
                break;
            default:
                throw new InvalidArgumentException('Unknown action: ' . $action);
        }

        if (false === $list) {
            return $this->c->Message->message('Bad request');
        } elseif (empty($list)) {
            $this->fIswev = ['i', \ForkBB\__('No hits')];
            return $this->view(['advanced' => 'advanced'], 'GET');
        }

        $this->fIndex       = 'search';
        $this->nameTpl      = 'forum';
        $this->onlinePos    = 'search';
        $this->robots       = 'noindex';
        $this->model        = $model;
        $this->topics       = $list;
        $this->crumbs       = $this->crumbs($model);
        $this->showForum    = true;

        return $this;
    }
}
