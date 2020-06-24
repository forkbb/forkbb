<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Container;
use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Topic\Model as Topic;

class Moderate extends Page
{
    /**
     * Список действий
     * @var array
     */
    protected $actions = [
        'open'   => true,
        'close'  => true,
        'delete' => true,
        'move'   => true,
        'merge'  => true,
        'cancel' => true,
    ];

    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->fIndex    = 'index';
        $this->nameTpl   = 'moderate';
        $this->onlinePos = 'moderate';
        $this->robots    = 'noindex, nofollow';

        $container->Lang->load('misc');
    }

    /**
     * Составление списка категорий/разделов для выбора
     */
    protected function calcList(int $curForum): void
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

                if ($f->redirect_url || $f->id === $curForum) {
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
     * Определяет действие
     *
     * @param Validator $v
     * @param null|string $action
     *
     * @return string
     */
    public function vActionProcess(Validator $v, $action)
    {
        if (empty($v->getErrors())) {
            $sum = 0;
            foreach ($this->actions as $key => $val) {
                if (isset($v->{$key})) {
                    $action = $key;
                    ++$sum;
                }
            }
            // нажата только одна кнопка из доступных
            if (1 !== $sum) {
                $v->addError('Action not available');
            }
            // объединение тем
            if ('merge' === $action && \count($v->ids) < 2) {
                $v->addError('Not enough topics selected');
            // перенос тем
            } elseif ('move' === $action) {
                $this->calcList($v->forum);

                if (empty($this->listOfIndexes)) {
                    $v->addError('Nowhere to move');
                } elseif (1 === $v->confirm && ! \in_array($v->destination, $this->listOfIndexes)) {
                    $v->addError('Invalid destination');
                }
            }
        }

        return $action;
    }

    /**
     * Обрабатывает модерирование разделов
     *
     * @param array $args
     *
     * @return Page
     */
    public function action(array $args): Page
    {
        $v = $this->c->Validator->reset()
            ->addValidators([
                'action_process' => [$this, 'vActionProcess'],
            ])->addRules([
                'token'       => 'token:Moderate',
                'step'        => 'required|integer|min:1',
                'forum'       => 'required|integer|min:1|max:9999999999',
                'topic'       => 'integer|min:1|max:9999999999',
                'page'        => 'integer|min:1',
                'ids'         => 'required|array',
                'ids.*'       => 'required|integer|min:1|max:9999999999',
                'confirm'     => 'integer',
                'redirect'    => 'integer',
                'destination' => 'integer',
                'open'        => 'string',
                'close'       => 'string',
                'delete'      => 'string',
                'move'        => 'string',
                'merge'       => 'string',
                'cancel'      => 'string',
                'action'      => 'action_process',
            ])->addAliases([
            ])->addArguments([
            ])->addMessages([
                'ids' => 'No object selected',
            ]);

        if (! $v->validation($_POST)) {
            $message = $this->c->Message->message('Bad request');
            $message->fIswev = $v->getErrors();
            return $message;
        }

        $this->curForum = $this->c->forums->loadTree($v->forum);
        if (! $this->curForum instanceof Forum) {
            return $this->c->Message->message('Bad request');
        } elseif (! $this->user->isAdmin && ! $this->user->isModerator($this->curForum)) {
            return $this->c->Message->message('No permission', true, 403);
        }

        $page = $v->page ?? 1;

        if ($v->topic) {
            $this->curTopic = $this->c->topics->load($v->topic);
            if (! $this->curTopic instanceof Topic || $this->curTopic->parent !== $this->curForum) {
                return $this->c->Message->message('Bad request');
            }
            // посты

            $this->backLink = $this->c->Router->link('Topic', [
                'id'   => $this->curTopic->id,
                'name' => $this->curTopic->subject,
                'page' => $page
            ]);
        } else {
            $objects = $this->c->topics->loadByIds($v->ids, false);
            foreach ($objects as $topic) {
                if (! $topic instanceof Topic || $topic->parent !== $this->curForum) {
                    return $this->c->Message->message('Bad request');
                }
            }
            $this->backLink = $this->c->Router->link('Forum', [
                'id'   => $this->curForum->id,
                'name' => $this->curForum->forum_name,
                'page' => $page
            ]);
        }

        return $this->{'action' . \ucfirst($v->action)}($objects, $v);
    }

    protected function actionCancel(array $objects, Validator $v): Page
    {
        return $this->c->Redirect->url($this->backLink)->message('No confirm redirect');
    }

    protected function actionOpen(array $topics, Validator $v): Page
    {
        switch ($v->step) {
            case 1:
                $this->formTitle   = \ForkBB\__('Open topics');
                $this->buttonValue = \ForkBB\__('Open');
                $this->crumbs      = $this->crumbs($this->formTitle, \ForkBB\__('Moderate'), $this->curForum);
                $this->form        = $this->formConfirm($topics, $v);
                return $this;
            case 2:
                if (1 === $v->confirm) {
                    $this->c->topics->access(true, ...$topics);

                    $message = 1 === \count($topics) ? 'Open topic redirect' : 'Open topics redirect';
                    return $this->c->Redirect->url($this->backLink)->message($message);
                } else {
                    return $this->actionCancel($topics, $v);
                }
            default:
                return $this->c->Message->message('Bad request');
        }
    }

    protected function actionClose(array $topics, Validator $v): Page
    {
        switch ($v->step) {
            case 1:
                $this->formTitle   = \ForkBB\__('Close topics');
                $this->buttonValue = \ForkBB\__('Close');
                $this->crumbs      = $this->crumbs($this->formTitle, \ForkBB\__('Moderate'), $this->curForum);
                $this->form        = $this->formConfirm($topics, $v);
                return $this;
            case 2:
                if (1 === $v->confirm) {
                    $this->c->topics->access(false, ...$topics);

                    $message = 1 === \count($topics) ? 'Close topic redirect' : 'Close topics redirect';
                    return $this->c->Redirect->url($this->backLink)->message($message);
                } else {
                    return $this->actionCancel($topics, $v);
                }
            default:
                return $this->c->Message->message('Bad request');
        }
    }

    protected function actionDelete(array $topics, Validator $v): Page
    {
        if (! $this->user->isAdmin) { //???? разобраться с правами на удаление
            foreach ($topics as $topic) {
                if (isset($this->c->admins->list[$topic->poster_id])) {
                    return $this->c->Message->message('No permission', true, 403); //???? причина
                }
            }
        }

        switch ($v->step) {
            case 1:
                $this->formTitle   = \ForkBB\__('Delete topics');
                $this->buttonValue = \ForkBB\__('Delete');
                $this->crumbs      = $this->crumbs($this->formTitle, \ForkBB\__('Moderate'), $this->curForum);
                $this->form        = $this->formConfirm($topics, $v);
                return $this;
            case 2:
                if (1 === $v->confirm) {
                    $this->c->topics->delete(...$topics);

                    return $this->c->Redirect->url($this->curForum->link)->message('Delete topics redirect');
                } else {
                    return $this->actionCancel($topics, $v);
                }
            default:
                return $this->c->Message->message('Bad request');
        }
    }

    protected function actionMove(array $topics, Validator $v): Page
    {
        switch ($v->step) {
            case 1:
                $this->formTitle   = \ForkBB\__('Move topics');
                $this->buttonValue = \ForkBB\__('Move');
                $this->crumbs      = $this->crumbs($this->formTitle, \ForkBB\__('Moderate'), $this->curForum);
                $this->chkRedirect = true;
                $this->form        = $this->formConfirm($topics, $v);
                return $this;
            case 2:
                if (1 === $v->confirm) {
                    $forum = $this->c->forums->get($v->destination);
                    $this->c->topics->move(1 === $v->redirect, $forum, ...$topics);

                    $message = 1 === \count($topics) ? 'Move topic redirect' : 'Move topics redirect';
                    return $this->c->Redirect->url($this->curForum->link)->message($message);
                } else {
                    return $this->actionCancel($topics, $v);
                }
            default:
                return $this->c->Message->message('Bad request');
        }
    }

    protected function actionMerge(array $topics, Validator $v): Page
    {
        foreach ($topics as $topic) {
            if ($topic->moved_to) {
                return $this->c->Message->message('Topic links cannot be merged');
            }
            if (! $this->firstTopic instanceof Topic
                || $topic->first_post_id < $this->firstTopic->first_post_id
            ) {
                $this->firstTopic = $topic;
            }
        }

        switch ($v->step) {
            case 1:
                $this->formTitle   = \ForkBB\__('Merge topics');
                $this->buttonValue = \ForkBB\__('Merge');
                $this->crumbs      = $this->crumbs($this->formTitle, \ForkBB\__('Moderate'), $this->curForum);
                $this->chkRedirect = true;
                $this->form        = $this->formConfirm($topics, $v);
                return $this;
            case 2:
                if (1 === $v->confirm) {
                    $this->c->topics->merge(1 === $v->redirect, ...$topics);

                    return $this->c->Redirect->url($this->curForum->link)->message('Merge topics redirect');
                } else {
                    return $this->actionCancel($topics, $v);
                }
            default:
                return $this->c->Message->message('Bad request');
        }
    }

    /**
     * Подготавливает массив данных для формы подтверждения
     *
     * @param array $objects
     * @param Validator $v
     *
     * @return array
     */
    protected function formConfirm(array $objects, Validator $v): array
    {
        $form = [
            'action' => $this->c->Router->link('Moderate'),
            'hidden' => [
                'token'  => $this->c->Csrf->create('Moderate'),
                'step'   => $v->step + 1,
                'forum'  => $v->forum,
            ],
            'sets' => [],
            'btns' => [],
        ];

        if ($v->topic) {
            $form['hidden']['topic'] = $v->topic;
        }

        $headers = [];
        $ids     = [];
        foreach ($objects as $object) {
            if ($object instanceof Topic) {
                $headers[] = \ForkBB\__('Topic «%s»', \ForkBB\cens(($object->subject)));
                $ids[]     = $object->id;
            }
        }

        $form['hidden']['ids'] = $ids;
        $form['sets']['info'] = [
            'info' => [
                'info1' => [
                    'type'    => '', //????
                    'value'   => \implode('<br>', $headers),
                    'html'    => true,
                ],
            ],
        ];

        if ($this->firstTopic instanceof Topic) {
            $form['sets']['info']['info']['info2'] = [
                'type'    => '', //????
                'value'   => \ForkBB\__('All posts will be posted in the «%s» topic', $this->firstTopic->subject),
//                'html'    => true,
            ];
        }

        if ($this->listForOptions) {
            $form['sets']['destination'] = [
                'fields' => [
                    'destination' => [
                        'type'     => 'select',
                        'options'  => $this->listForOptions,
                        'value'    => null,
                        'caption'  => \ForkBB\__('Move to'),
                        'required' => true,
                    ],
                ],
            ];
        }

        if (true === $this->chkRedirect) {
            $form['sets']['redirect'] = [
                'fields' => [
                    'redirect' => [
                        'type'    => 'checkbox',
                        'label'   => \ForkBB\__('Leave redirect'),
                        'value'   => '1',
                        'checked' => true,
                    ],
                ],
            ];
        }

        $form['sets']['confirm'] = [
            'fields' => [
                'confirm' => [
                    'type'    => 'checkbox',
                    'label'   => \ForkBB\__('Confirm action'),
                    'value'   => '1',
                    'checked' => false,
                ],
            ],
        ];

        $form['btns'][$v->action] = [
            'type'      => 'submit',
            'value'     => $this->buttonValue,
//            'accesskey' => 's',
        ];
        $form['btns']['cancel'] = [
            'type'      => 'submit',
            'value'     => \ForkBB\__('Cancel'),
        ];

        return $form;
    }
}
