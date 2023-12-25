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
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Topic\Topic;
use ForkBB\Models\Post\Post;
use function \ForkBB\__;
use function \ForkBB\dt;

class Moderate extends Page
{
    const INFORUM = 1; // действие для форума
    const INTOPIC = 2; // действие для темы
    const TOTOPIC = 4; // список постов сменить на тему
    const IFTOTPC = 8; // список постов сменить на тему, если в нем только первый пост темы

    /**
     * Список действий
     */
    protected array $actions = [
        'open'    => self::INFORUM + self::INTOPIC + self::TOTOPIC,
        'close'   => self::INFORUM + self::INTOPIC + self::TOTOPIC,
        'delete'  => self::INFORUM + self::INTOPIC + self::IFTOTPC,
        'move'    => self::INFORUM + self::INTOPIC + self::IFTOTPC,
        'merge'   => self::INFORUM,
        'cancel'  => self::INFORUM + self::INTOPIC + self::TOTOPIC + self::IFTOTPC,
        'unstick' => self::INFORUM + self::INTOPIC + self::TOTOPIC,
        'stick'   => self::INFORUM + self::INTOPIC + self::TOTOPIC,
        'split'   => self::INTOPIC,
        'link'    => self::INFORUM,
    ];

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->identifier = 'moderate';
        $this->nameTpl    = 'moderate';
        $this->onlinePos  = 'moderate';
        $this->robots     = 'noindex, nofollow';
        $this->hhsLevel   = 'secure';

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
        $root    = $this->c->forums->get(0);

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
    public function vActionProcess(Validator $v, ?string $action): ?string
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
            // управление перенаправлениями
            } elseif (
                'link' === $action
                && \count($v->ids) > 1
            ) {
                $v->addError('Only one topic is permissible');
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
                    && ! \in_array($v->destination, $this->listOfIndexes, true)
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
                'page'        => 'integer|min:1|max:9999999999',
                'ids'         => 'required|array',
                'ids.*'       => 'required|integer|min:1|max:9999999999',
                'forums'      => 'array',
                'forums.*'    => 'integer|min:1|max:9999999999', // ????
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
                'link'        => 'string',
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
                    'name' => $this->c->Func->friendly($this->curTopic->name),
                    'page' => $v->page,
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
                    'name' => $this->curForum->friendly,
                    'page' => $v->page,
                ]
            );
        }

        $this->numObj = \count($objects);

        return $this->{'action' . \ucfirst($v->action)}($objects, $v);
    }

    protected function actionCancel(array $objects, Validator $v): Page
    {
        return $this->c->Redirect->url($this->backLink)->message('No confirm redirect', FORK_MESS_WARN);
    }

    protected function actionOpen(array $topics, Validator $v): Page
    {
        switch ($v->step) {
            case 1:
                $this->formTitle   = ['Open topic title', $this->numObj];
                $this->buttonValue = ['Open topic btn', $this->numObj];
                $this->crumbs      = $this->crumbs(
                    [null, $this->formTitle],
                    'Moderate',
                    $v->topic ? $this->curTopic : $this->curForum
                );
                $this->form        = $this->formConfirm($topics, $v);

                return $this;
            case 2:
                if (1 === $v->confirm) {
                    $this->c->topics->access(true, ...$topics);

                    return $this->c->Redirect->url($this->backLink)->message(['Open topic redirect', $this->numObj], FORK_MESS_SUCC);
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
                $this->formTitle   = ['Close topic title', $this->numObj];
                $this->buttonValue = ['Close topic btn', $this->numObj];
                $this->crumbs      = $this->crumbs(
                    [null, $this->formTitle],
                    'Moderate',
                    $v->topic ? $this->curTopic : $this->curForum
                );
                $this->form        = $this->formConfirm($topics, $v);

                return $this;
            case 2:
                if (1 === $v->confirm) {
                    $this->c->topics->access(false, ...$topics);

                    return $this->c->Redirect->url($this->backLink)->message(['Close topic redirect', $this->numObj], FORK_MESS_SUCC);
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
                $this->formTitle   = [
                    true === $this->processAsPosts
                        ? 'Delete post title'
                        : 'Delete topic title',
                    $this->numObj,
                ];
                $this->buttonValue = [
                    true === $this->processAsPosts
                        ? 'Delete post btn'
                        : 'Delete topic btn',
                    $this->numObj,
                ];
                $this->crumbs      = $this->crumbs(
                    [null, $this->formTitle],
                    'Moderate',
                    $v->topic ? $this->curTopic : $this->curForum
                );
                $this->form        = $this->formConfirm($objects, $v);

                return $this;
            case 2:
                if (1 === $v->confirm) {
                    if (true === $this->processAsPosts) {
                        $this->c->posts->delete(...$objects);

                        $message = 'Delete post redirect';
                    } else {
                        $this->c->topics->delete(...$objects);

                        $message = 'Delete topic redirect';
                    }

                    return $this->c->Redirect->url($this->curForum->link)->message([$message, $this->numObj], FORK_MESS_SUCC);
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
                $this->formTitle   = ['Move topic title', $this->numObj];
                $this->buttonValue = ['Move topic btn', $this->numObj];
                $this->crumbs      = $this->crumbs(
                    [null, $this->formTitle],
                    'Moderate',
                    $v->topic ? $this->curTopic : $this->curForum
                );
                $this->chkRedirect = true;
                $this->form        = $this->formConfirm($topics, $v);

                return $this;
            case 2:
                if (1 === $v->confirm) {
                    $forum = $this->c->forums->get($v->destination);

                    $this->c->topics->move(1 === $v->redirect, $forum, ...$topics);

                    return $this->c->Redirect->url($this->curForum->link)->message(['Move topic redirect', $this->numObj], FORK_MESS_SUCC);
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

        foreach ($topics as $topic) {
            if (
                $this->firstTopic !== $topic
                && $topic->poll_type > 0
            ) {
                return $this->c->Message->message('Poll cannot be attached');
            }
        }

        switch ($v->step) {
            case 1:
                $this->formTitle   = 'Merge topics title';
                $this->buttonValue = 'Merge btn';
                $this->crumbs      = $this->crumbs($this->formTitle, 'Moderate', $this->curForum);
                $this->chkRedirect = true;
                $this->form        = $this->formConfirm($topics, $v);

                return $this;
            case 2:
                if (1 === $v->confirm) {
                    $this->c->topics->merge(1 === $v->redirect, ...$topics);

                    return $this->c->Redirect->url($this->curForum->link)->message('Merge topics redirect', FORK_MESS_SUCC);
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
                $this->formTitle   = ['Unstick topic title', $this->numObj];
                $this->buttonValue = ['Unstick btn', $this->numObj];
                $this->crumbs      = $this->crumbs(
                    [null, $this->formTitle],
                    'Moderate',
                    $v->topic ? $this->curTopic : $this->curForum
                );
                $this->form        = $this->formConfirm($topics, $v);

                return $this;
            case 2:
                if (1 === $v->confirm) {
                    foreach ($topics as $topic) {
                        $topic->sticky = 0;

                        $this->c->topics->update($topic);
                    }

                    return $this->c->Redirect->url($this->backLink)->message(['Unstick topic redirect', $this->numObj], FORK_MESS_SUCC);
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
                $this->formTitle   = ['Stick topic title', $this->numObj];
                $this->buttonValue = ['Stick btn', $this->numObj];
                $this->crumbs      = $this->crumbs(
                    [null, $this->formTitle],
                    'Moderate',
                    $v->topic ? $this->curTopic : $this->curForum
                );
                $this->form        = $this->formConfirm($topics, $v);

                return $this;
            case 2:
                if (1 === $v->confirm) {
                    foreach ($topics as $topic) {
                        $topic->sticky = 1;

                        $this->c->topics->update($topic);
                    }

                    return $this->c->Redirect->url($this->backLink)->message(['Stick topic redirect', $this->numObj], FORK_MESS_SUCC);
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
                $this->formTitle   = 'Split posts title';
                $this->buttonValue = 'Split btn';
                $this->needSubject = true;
                $this->crumbs      = $this->crumbs($this->formTitle, 'Moderate', $this->curTopic);
                $this->form        = $this->formConfirm($posts, $v);

                return $this;
            case 2:
                if (1 === $v->confirm) {
                    $newTopic           = $this->c->topics->create();
                    $newTopic->subject  = $v->subject;
                    $newTopic->forum_id = $v->forum;

                    $this->c->topics->insert($newTopic);
                    $this->c->posts->move(false, $newTopic, ...$posts);

                    return $this->c->Redirect->url($this->curForum->link)->message('Split posts redirect', FORK_MESS_SUCC);
                } else {
                    return $this->actionCancel($posts, $v);
                }
            default:
                return $this->c->Message->message('Bad request');
        }
    }

    protected function actionLink(array $topics, Validator $v): Page
    {
        $topic = \array_pop($topics);

        if ($topic->moved_to) {
            return $this->c->Message->message('Need full topic for this operation');
        }

        $links = $this->c->topics->loadLinks($topic);
        $ft    = [];

        foreach ($links as $link) {
            $ft[$link->parent->id][] = $link;
        }

        switch ($v->step) {
            case 1:
                $this->formTitle   = 'Control of redirects title';
                $this->crumbs      = $this->crumbs($this->formTitle, 'Moderate', $this->curForum);
                $this->form        = $this->formLinks($topic, $ft, $v);

                return $this;
            case 2:
                $root = $this->c->forums->get(0);

                if ($root instanceof Forum) {
                    $selected = $v->forums ?: [];
                    $delLinks = [];

                    foreach ($this->c->forums->depthList($root, 0) as $forum) {
                        if ($forum->redirect_url) {
                            continue;
                        }

                        // создать тему-перенаправление
                        if (
                            empty($ft[$forum->id])
                            && \in_array($forum->id, $selected, true)
                        ) {
                            $rTopic            = $this->c->topics->create();
                            $rTopic->poster    = $topic->poster;
                            $rTopic->poster_id = $topic->poster_id;
                            $rTopic->subject   = $topic->subject;
                            $rTopic->posted    = $topic->posted;
                            $rTopic->last_post = $topic->last_post;
                            $rTopic->moved_to  = $topic->moved_to ?: $topic->id;
                            $rTopic->forum_id  = $forum->id;

                            $this->c->topics->insert($rTopic);
                            $this->c->forums->update($forum->calcStat());
                        // удалить тему(ы)-перенаправление
                        } elseif (
                            ! empty($ft[$forum->id])
                            && ! \in_array($forum->id, $selected, true)
                        ) {
                            foreach ($ft[$forum->id] as $link) {
                                $delLinks[] = $link;
                            }
                        }
                    }

                    if ($delLinks) {
                        $this->c->topics->delete(...$delLinks);
                    }
                }

                return $this->c->Redirect->url($topic->linkCrumbExt)->message('Redirects changed redirect', FORK_MESS_SUCC);
            default:
                return $this->c->Message->message('Bad request');
        }
    }

    /**
     * Подготавливает массив данных для формы управления переадресацией
     */
    protected function formLinks(Topic $topic, array $ft, Validator $v): array
    {
        $form = [
            'action' => $this->c->Router->link('Moderate'),
            'hidden' => [
                'token'  => $this->c->Csrf->create('Moderate'),
                'step'   => $v->step + 1,
                'forum'  => $v->forum,
                'ids'    => $v->ids,
            ],
            'sets' => [
                'info' => [
                    'inform' => [
                        [
                            'html' => __(['Topic «%s»', $topic->name]),
                        ],
                    ],
                ],
            ],
            'btns' => [
                'link' => [
                    'type'  => 'submit',
                    'value' => __('Change btn'),
                ],
                'cancel' => [
                    'type'  => 'submit',
                    'value' => __('Cancel'),
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
                    'for'     => "forums[{$forum->id}]",
                ];
                $fields["forums[{$forum->id}]"] = [
                    'class'    => ['modforum', 'moderator'],
                    'type'     => 'checkbox',
                    'value'    => $forum->id,
                    'checked'  => ! empty($ft[$forum->id]),
                    'disabled' => ! empty($forum->redirect_url),
                    'caption'  => 'Redir label',
                ];
                $form['sets']["forum{$forum->id}"] = [
                    'class'  => $topic->parent->id === $forum->id ? ['modforum', 'current'] : ['modforum'],
                    'legend' => $forum->cat_name . ' / ' . $forum->forum_name,
                    'fields' => $fields,
                ];
            }
        }

        return $form;
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
                'page'   => $v->page ?? 1,
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
                $headers[] = __(['Topic «%s»', $object->name]);
            } else {
                $headers[] = __(['Post «%1$s by %2$s»', dt($object->posted), $object->poster]);
            }
        }

        $form['sets']['info'] = [
            'inform' => [
                [
                    'html' => \implode('<br>', $headers),
                ],
            ],
        ];

        if ($this->firstTopic instanceof Topic) {
            $form['sets']['info']['inform'][] = [
                'message' => ['All posts will be posted in the «%s» topic', $this->firstTopic->name],
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
                'label'   => 'Leave redirect',
                'checked' => true,
            ];
        }

        $fields['confirm'] = [
            'type'    => 'checkbox',
            'label'   => 'Confirm action',
            'checked' => false,
        ];
        $form['sets']['moderate']['fields'] = $fields;
        $form['btns'][$v->action] = [
            'type'  => 'submit',
            'value' => __($this->buttonValue),
        ];
        $form['btns']['cancel'] = [
            'type'  => 'submit',
            'value' => __('Cancel'),
        ];

        return $form;
    }
}
