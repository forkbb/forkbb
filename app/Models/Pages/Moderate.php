<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Container;
use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Topic\Model as Topic;
use ForkBB\Models\Post\Model as Post;
use function \ForkBB\__;

class Moderate extends Page
{
    const INFORUM = 1; // действие для форума
    const INTOPIC = 2; // действие для темы
    const TOTOPIC = 4; // список постов сменить на тему
    const IFTOTPC = 8; // список постов сменить на тему, если в нем только первый пост темы

    /**
     * Список действий
     * @var array
     */
    protected $actions = [
        'open'    => self::INFORUM + self::INTOPIC + self::TOTOPIC,
        'close'   => self::INFORUM + self::INTOPIC + self::TOTOPIC,
        'delete'  => self::INFORUM + self::INTOPIC + self::IFTOTPC,
        'move'    => self::INFORUM + self::INTOPIC + self::IFTOTPC,
        'merge'   => self::INFORUM,
        'cancel'  => self::INFORUM + self::INTOPIC + self::TOTOPIC + self::IFTOTPC,
        'unstick' => self::INTOPIC + self::TOTOPIC,
        'stick'   => self::INTOPIC + self::TOTOPIC,
        'split'   => self::INTOPIC,
    ];

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->fIndex    = 'index';
        $this->nameTpl   = 'moderate';
        $this->onlinePos = 'moderate';
        $this->robots    = 'noindex, nofollow';
        $this->hhsLevel  = 'secure';

        $container->Lang->load('validator');
        $container->Lang->load('misc');
    }

    /**
     * Составление списка категорий/разделов для выбора
     */
    protected function calcList(int $curForum, bool $noUseCurForum = true): void
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

                if (
                    $f->redirect_url
                    || (
                        $noUseCurForum
                        && $f->id === $curForum
                    )
                ) {
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
     * Определяет действие
     */
    public function vActionProcess(Validator $v, $action)
    {
        if (empty($v->getErrors())) {
            $type = $v->topic ? self::INTOPIC : self::INFORUM;
            $sum  = 0;
            foreach ($this->actions as $key => $val) {
                if (isset($v->{$key})) {
                    $action = $key;
                    ++$sum;
                }
            }
            // нажата не одна кнопка или недоступная кнопка
            if (
                1 !== $sum
                || ! ($type & $this->actions[$action])
            ) {
                $v->addError('Action not available');
            // не выбрано ни одного сообщения для действий прямо этого требующих
            } elseif (
                $v->topic
                && 1 === \count($v->ids)
                && ! ((self::TOTOPIC + self::IFTOTPC) & $this->actions[$action])
            ) {
                $v->addError('No object selected');
            }

            // объединение тем
            if (
                'merge' === $action
                && \count($v->ids) < 2
            ) {
                $v->addError('Not enough topics selected');
            // перенос тем или разделение постов
            } elseif (
                'move' === $action
                || 'split' === $action
            ) {
                $this->calcList($v->forum, 'move' === $action);

                if (empty($this->listOfIndexes)) {
                    $v->addError('Nowhere to move');
                } elseif (
                    1 === $v->confirm
                    && ! \in_array($v->destination, $this->listOfIndexes)
                ) {
                    $v->addError('Invalid destination');
                } elseif (
                    'split' === $action
                    && 1 === $v->confirm
                    && '' == $v->subject
                ) {
                    $v->addError('No subject');
                }
            }
        }

        return $action;
    }

    /**
     * Обрабатывает модерирование разделов
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
                'subject'     => 'string:trim,spaces|min:1|max:70',
                'destination' => 'integer',
                'open'        => 'string',
                'close'       => 'string',
                'delete'      => 'string',
                'move'        => 'string',
                'merge'       => 'string',
                'cancel'      => 'string',
                'unstick'     => 'string',
                'stick'       => 'string',
                'split'       => 'string',
                'action'      => 'action_process',
            ])->addAliases([
            ])->addArguments([
            ])->addMessages([
                'ids' => 'No object selected',
            ]);

        if (! $v->validation($_POST)) {
            $message         = $this->c->Message;
            $message->fIswev = $v->getErrors();

            return $message->message('');
        }

        $this->curForum = $this->c->forums->loadTree($v->forum);
        if (! $this->curForum instanceof Forum) {
            return $this->c->Message->message('Bad request');
        } elseif (
            ! $this->user->isAdmin
            && ! $this->user->isModerator($this->curForum)
        ) {
            return $this->c->Message->message('No permission', true, 403);
        }

        $page = $v->page ?? 1;

        if ($v->topic) {
            $this->curTopic = $this->c->topics->load($v->topic);
            if (
                ! $this->curTopic instanceof Topic
                || $this->curTopic->parent !== $this->curForum
            ) {
                return $this->c->Message->message('Bad request');
            }

            $objects = null;
            $curType = $this->actions[$v->action];
            $ids     = $v->ids;
            $firstId = $this->curTopic->first_post_id;
            if (self::TOTOPIC & $curType) {
                $objects = [$this->curTopic];
            } elseif (self::IFTOTPC & $curType) {
                if (
                    1 === \count($ids)
                    && \reset($ids) === $firstId
                ) {
                    $objects = [$this->curTopic];
                }
            }
            if (null === $objects) {
                $objects = $this->c->posts->loadByIds(\array_diff($ids, [$firstId]), false);
                foreach ($objects as $post) {
                    if (
                        ! $post instanceof Post
                        || $post->parent !== $this->curTopic
                    ) {
                        return $this->c->Message->message('Bad request');
                    }
                }
                $this->processAsPosts = true;
            }

            $this->backLink = $this->c->Router->link(
                'Topic',
                [
                    'id'   => $this->curTopic->id,
                    'name' => $this->curTopic->censorSubject,
                    'page' => $page,
                ]
            );
        } else {
            $objects = $this->c->topics->loadByIds($v->ids, false);
            foreach ($objects as $topic) {
                if (
                    ! $topic instanceof Topic
                    || $topic->parent !== $this->curForum
                ) {
                    return $this->c->Message->message('Bad request');
                }
            }

            $this->backLink = $this->c->Router->link(
                'Forum',
                [
                    'id'   => $this->curForum->id,
                    'name' => $this->curForum->forum_name,
                    'page' => $page,
                ]
            );
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
                $this->formTitle   = __('Open topics');
                $this->buttonValue = __('Open');
                $this->crumbs      = $this->crumbs($this->formTitle, __('Moderate'), $v->topic ? $this->curTopic : $this->curForum);
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
                $this->formTitle   = __('Close topics');
                $this->buttonValue = __('Close');
                $this->crumbs      = $this->crumbs($this->formTitle, __('Moderate'), $v->topic ? $this->curTopic : $this->curForum);
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

    protected function actionDelete(array $objects, Validator $v): Page
    {
        if (! $this->user->isAdmin) { //???? разобраться с правами на удаление
            foreach ($objects as $object) {
                if (
                    (
                        $object instanceof Topic
                        && isset($this->c->admins->list[$object->poster_id])
                    )
                    || (
                        $object instanceof Post
                        && ! $object->canDelete
                    )
                ) {
                    return $this->c->Message->message('No permission', true, 403); //???? причина
                }
            }
        }

        switch ($v->step) {
            case 1:
                $this->formTitle   = __(true === $this->processAsPosts ? 'Delete posts' : 'Delete topics');
                $this->buttonValue = __('Delete');
                $this->crumbs      = $this->crumbs($this->formTitle, __('Moderate'), $v->topic ? $this->curTopic : $this->curForum);
                $this->form        = $this->formConfirm($objects, $v);

                return $this;
            case 2:
                if (1 === $v->confirm) {
                    if (true === $this->processAsPosts) {
                        $this->c->posts->delete(...$objects);
                        $message = 'Delete posts redirect';
                    } else {
                        $this->c->topics->delete(...$objects);
                        $message = 'Delete topics redirect';
                    }

                    return $this->c->Redirect->url($this->curForum->link)->message($message);
                } else {
                    return $this->actionCancel($objects, $v);
                }
            default:
                return $this->c->Message->message('Bad request');
        }
    }

    protected function actionMove(array $topics, Validator $v): Page
    {
        switch ($v->step) {
            case 1:
                $this->formTitle   = __('Move topics');
                $this->buttonValue = __('Move');
                $this->crumbs      = $this->crumbs($this->formTitle, __('Moderate'), $v->topic ? $this->curTopic : $this->curForum);
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
            if (
                ! $this->firstTopic instanceof Topic
                || $topic->first_post_id < $this->firstTopic->first_post_id
            ) {
                $this->firstTopic = $topic;
            }
        }

        switch ($v->step) {
            case 1:
                $this->formTitle   = __('Merge topics');
                $this->buttonValue = __('Merge');
                $this->crumbs      = $this->crumbs($this->formTitle, __('Moderate'), $this->curForum);
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

    protected function actionUnstick(array $topics, Validator $v): Page
    {
        switch ($v->step) {
            case 1:
                $this->formTitle   = __('Unstick topics');
                $this->buttonValue = __('Unstick');
                $this->crumbs      = $this->crumbs($this->formTitle, __('Moderate'), $v->topic ? $this->curTopic : $this->curForum);
                $this->form        = $this->formConfirm($topics, $v);

                return $this;
            case 2:
                if (1 === $v->confirm) {
                    foreach ($topics as $topic) {
                        $topic->sticky = 0;
                        $this->c->topics->update($topic);
                    }

                    $message = 1 === \count($topics) ? 'Unstick topic redirect' : 'Unstick topics redirect';

                    return $this->c->Redirect->url($this->backLink)->message($message);
                } else {
                    return $this->actionCancel($topics, $v);
                }
            default:
                return $this->c->Message->message('Bad request');
        }
    }

    protected function actionStick(array $topics, Validator $v): Page
    {
        switch ($v->step) {
            case 1:
                $this->formTitle   = __('Stick topics');
                $this->buttonValue = __('Stick');
                $this->crumbs      = $this->crumbs($this->formTitle, __('Moderate'), $v->topic ? $this->curTopic : $this->curForum);
                $this->form        = $this->formConfirm($topics, $v);

                return $this;
            case 2:
                if (1 === $v->confirm) {
                    foreach ($topics as $topic) {
                        $topic->sticky = 1;
                        $this->c->topics->update($topic);
                    }

                    $message = 1 === \count($topics) ? 'Stick topic redirect' : 'Stick topics redirect';

                    return $this->c->Redirect->url($this->backLink)->message($message);
                } else {
                    return $this->actionCancel($topics, $v);
                }
            default:
                return $this->c->Message->message('Bad request');
        }
    }

    protected function actionSplit(array $posts, Validator $v): Page
    {
        switch ($v->step) {
            case 1:
                $this->formTitle   = __('Split posts');
                $this->buttonValue = __('Split');
                $this->needSubject = true;
                $this->crumbs      = $this->crumbs($this->formTitle, __('Moderate'), $this->curTopic);
                $this->form        = $this->formConfirm($posts, $v);

                return $this;
            case 2:
                if (1 === $v->confirm) {
                    $newTopic           = $this->c->topics->create();
                    $newTopic->subject  = $v->subject;
                    $newTopic->forum_id = $v->forum;
                    $this->c->topics->insert($newTopic);

                    $this->c->posts->move(false, $newTopic, ...$posts);

                    return $this->c->Redirect->url($this->curForum->link)->message('Split posts redirect');
                } else {
                    return $this->actionCancel($posts, $v);
                }
            default:
                return $this->c->Message->message('Bad request');
        }
    }

    /**
     * Подготавливает массив данных для формы подтверждения
     */
    protected function formConfirm(array $objects, Validator $v): array
    {
        $form = [
            'action' => $this->c->Router->link('Moderate'),
            'hidden' => [
                'token'  => $this->c->Csrf->create('Moderate'),
                'step'   => $v->step + 1,
                'forum'  => $v->forum,
                'ids'    => $v->ids,
            ],
            'sets' => [],
            'btns' => [],
        ];
        $autofocus = true;

        if ($v->topic) {
            $form['hidden']['topic'] = $v->topic;
        }

        $headers = [];
        foreach ($objects as $object) {
            if ($object instanceof Topic) {
                $headers[] = __(['Topic «%s»', $object->censorSubject]);
            } else {
                $headers[] = __(['Post «%1$s by %2$s»', \ForkBB\dt($object->posted), $object->poster]);
            }
        }

        $form['sets']['info'] = [
            'info' => [
                [
                    'value' => \implode('<br>', $headers),
                    'html'  => true,
                ],
            ],
        ];

        if ($this->firstTopic instanceof Topic) {
            $form['sets']['info']['info'][] = [
                'value' => __(['All posts will be posted in the «%s» topic', $this->firstTopic->censorSubject]),
            ];
        }

        $fields = [];

        if ($this->needSubject) {
            $fields['subject'] = [
                'type'      => 'text',
                'maxlength' => '70',
                'caption'   => 'New subject',
                'required'  => true,
                'value'     => '' == $v->subject ? $this->curTopic->subject : $v->subject,
                'autofocus' => $autofocus,
            ];
            $autofocus = null;
        }

        if ($this->listForOptions) {
            $fields['destination'] = [
                'type'      => 'select',
                'options'   => $this->listForOptions,
                'value'     => null,
                'caption'   => 'Move to',
                'autofocus' => $autofocus,
            ];
            $autofocus = null;
        }

        if (true === $this->chkRedirect) {
            $fields['redirect'] = [
                'type'    => 'checkbox',
                'label'   => __('Leave redirect'),
                'value'   => '1',
                'checked' => true,
            ];
        }

        $fields['confirm'] = [
            'type'    => 'checkbox',
            'label'   => __('Confirm action'),
            'value'   => '1',
            'checked' => false,
        ];
        $form['sets']['moderate']['fields'] = $fields;
        $form['btns'][$v->action] = [
            'type'  => 'submit',
            'value' => $this->buttonValue,
        ];
        $form['btns']['cancel'] = [
            'type'  => 'submit',
            'value' => __('Cancel'),
        ];

        return $form;
    }
}
