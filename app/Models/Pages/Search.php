<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Core\Validator;
use InvalidArgumentException;

class Search extends Page
{
    use CrumbTrait;

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
                    'forums'   => 'integer',
                    'serch_in' => 'required|integer|in:0,1,2',
                    'sort_by'  => 'required|integer|in:0,1,2,3',
                    'sort_dir' => 'required|integer|in:0,1',
                    'show_as'  => 'required|integer|in:0,1',
                ]);
            }

            if ($v->validation($_POST)) {
                $this->c->search->execute([
                    'keywords' => $v->keywords,
                    'author'   => (string) $v->author,
                    'forums'   => $v->forums,
                    'serch_in' => ['all', 'posts', 'topics'][(int) $v->serch_in],
                    'sort_by'  => ['post', 'author', 'subject', 'forum'][(int) $v->sort_by],
                    'sort_dir' => ['desc', 'asc'][(int) $v->sort_dir],
                    'show_as'  => ['posts', 'topics'][(int) $v->show_as],
                ]);
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
                        'dl'        => 't2',
                        'type'      => 'text',
                        'maxlength' => 100,
                        'title'     => \ForkBB\__('Keyword search'),
                        'value'     => $v ? $v->keywords : '',
                        'required'  => true,
                        'autofocus' => true,
                    ],
                    'author' => [
                        'dl'        => 't1',
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
                        'type'    => 'multiselect',
                        'options' => [],
                        'value'   => $v ? $v->forums : null,
                        'title'   => \ForkBB\__('Forum search'),
                    ],
                    'serch_in' => [
                        'type'   => 'select',
                        'options' => [
                            0 => \ForkBB\__('Message and subject'),
                            1 => \ForkBB\__('Message only'),
                            2 => \ForkBB\__('Topic only'),
                        ],
                        'value'  => $v ? $v->serch_in : 0,
                        'title'  => \ForkBB\__('Search in'),
                    ],
                    [
                        'type'  => 'info',
                        'value' => \ForkBB\__('Search in info'),
                    ],
                    [
                        'type'  => 'info',
                        'value' => \ForkBB\__('Search multiple forums info'),
                    ],

                ],
            ];
            $form['sets'][] = [
                'legend' => \ForkBB\__('Search results legend'),
                'fields' => [
                    'sort_by' => [
                        'type'   => 'select',
                        'options' => [
                            0 => \ForkBB\__('Sort by post time'),
                            1 => \ForkBB\__('Sort by author'),
                            2 => \ForkBB\__('Sort by subject'),
                            3 => \ForkBB\__('Sort by forum'),
                        ],
                        'value'  => $v ? $v->sort_by : 0,
                        'title'  => \ForkBB\__('Sort by'),
                    ],
                    'sort_dir' => [
                        'type'   => 'radio',
                        'values' => [
                            0 => \ForkBB\__('Descending'),
                            1 => \ForkBB\__('Ascending'),
                        ],
                        'value'  => $v ? $v->sort_dir : 0,
                        'title'  => \ForkBB\__('Sort order'),
                    ],
                    'show_as' => [
                        'type'   => 'radio',
                        'values' => [
                            0 => \ForkBB\__('Show as posts'),
                            1 => \ForkBB\__('Show as topics'),
                        ],
                        'value'  => $v ? $v->show_as : 0,
                        'title'  => \ForkBB\__('Show as'),
                    ],
                    [
                        'type'  => 'info',
                        'value' => \ForkBB\__('Search results info'),
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
        $search = $this->c->search;

        if (! $search->prepare($query)) {
            $v->addError(\ForkBB\__($search->queryError, $search->queryText));
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
            $this->a['fIswev']['i'][] = \ForkBB\__('No hits');
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
