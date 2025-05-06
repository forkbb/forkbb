<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\PostFormTrait;
use ForkBB\Models\Pages\PostValidatorTrait;
use ForkBB\Models\Poll\Poll;
use ForkBB\Models\Post\Post;
use ForkBB\Models\Topic\Topic;
use ForkBB\Models\User\User;
use function \ForkBB\__;

class Edit extends Page
{
    use PostFormTrait;
    use PostValidatorTrait;
    use PostCFTrait;

    const SILENT = 1200;

    /**
     * Редактирование сообщения
     */
    public function edit(array $args, string $method): Page
    {
        $post = $this->c->posts->load($args['id']);

        if (
            ! $post instanceof Post
            || ! $post->canEdit
        ) {
            return $this->c->Message->message('Bad request');
        }

        $topic                   = $post->parent;
        $firstPost               = $post->id === $topic->first_post_id;
        $this->customFieldsLevel = $firstPost ? $topic->customFieldsCurLevel : 0;

        $this->c->Lang->load('post');

        if (1 === $this->c->config->b_poll_enabled) {
            $this->c->Lang->load('poll');
        }

        if ('POST' === $method) {
            $v = $this->messageValidator($post, 'EditPost', $args, true, $firstPost);

            if ($this->customFieldsLevel > 0) {
                $this->addCFtoMessageValidator($topic->cf_data, $this->customFieldsLevel, $v);
            }

            if (
                $v->validation($_POST)
                && null === $v->preview
                && null !== $v->submit
            ) {
                return $this->endEdit($post, $v);
            }

            $this->fIswev  = $v->getErrors();
            $args['_vars'] = $v->getData();

            if (
                null !== $v->preview
                && ! $v->getErrors()
            ) {
                $this->previewHtml = $this->c->censorship->censor(
                    $this->c->Parser->parseMessage(null, (bool) $v->hide_smilies)
                );
                $this->useMediaJS  = true;

                if (
                    $firstPost
                    && $this->userRules->usePoll
                    && $v->poll_enable
                ) {
                    $this->poll = $this->c->polls->create($v->poll);

                    $this->c->polls->revision($this->poll, true);
                }
            }

        } else {
            $args['_vars'] = [
                'message'      => $post->message,
                'subject'      => $topic->subject,
                'hide_smilies' => $post->hide_smilies,
                'stick_topic'  => $topic->sticky,
                'stick_fp'     => $topic->stick_fp,
                'edit_post'    => $post->edit_post,
            ];
        }

        if (
            $firstPost
            && $this->userRules->usePoll
        ) {
            $poll = $topic->poll;

            if (
                $poll instanceof Poll
                && (
                    ! $poll->canEdit
                    || 'POST' !== $method
                )
            ) {
                $args['_vars'] = \array_merge($args['_vars'], [
                    'pollNoEdit'   => ! $poll->canEdit,
                    'poll_enable'  => $topic->poll_type > 0,
                    'poll'         => [
                        'duration'    => $topic->poll_type > 1000 ? $topic->poll_type - 1000 : 0, // ???? перенести в модель poll?
                        'hide_result' => $topic->poll_term > 0,
                        'question'    => $poll->question,
                        'type'        => $poll->type,
                        'answer'      => $poll->answer,
                    ],
                ]);
            }

            if (
                null !== $this->previewHtml
                && $args['_vars']['poll_enable']
            ) {
                $this->poll = $this->c->polls->create($args['_vars']['poll']);
                $this->c->polls->revision($this->poll, true);
            }
        }

        $this->identifier = 'edit';
        $this->nameTpl    = 'post';
        $this->onlinePos  = 'topic-' . $topic->id;
//        $this->canonical = $post->linkEdit;
        $this->robots     = 'noindex';
        $this->formTitle  = $firstPost ? 'Edit topic' : 'Edit post';
        $this->crumbs     = $this->crumbs($this->formTitle, $topic);
        $this->form       = $this->messageForm($post, 'EditPost', $args, true, $firstPost, false);

        if ($this->customFieldsLevel > 0) {
            $this->form = $this->addCFtoMessageForm($topic->cf_data, $this->customFieldsLevel, $this->form, $args);
        }

        return $this;
    }

    /**
     * Сохранение сообщения
     */
    protected function endEdit(Post $post, Validator $v): Page
    {
        $now       = \time();
        $executive = $this->user->isAdmin || $this->user->isModerator($post);
        $topic     = $post->parent;
        $firstPost = $post->id === $topic->first_post_id;
        $calcPost  = false;
        $calcTopic = false;
        $calcForum = false;
        $calcAttch = false;

        // текст сообщения
        if ($post->message !== $v->message) {
            $post->message       = $v->message;
            $calcAttch           = true;

            if (
                $post->poster_id !== $this->user->id
                || $now - $post->posted > self::SILENT
                || (
                    $post->editor_id > 0
                    && $post->editor_id !== $this->user->id
                )
            ) {
                $post->edited    = $now;
                $post->editor    = $this->user->username;
                $post->editor_id = $this->user->id;
                $calcPost        = true;

                if ($post->id === $topic->last_post_id) {
                    $calcTopic   = true;
                    $calcForum   = true;
                }
            }
        }

        // показ смайлов
        if (
            1 === $this->c->config->b_smilies
            && (bool) $post->hide_smilies !== (bool) $v->hide_smilies
        ) {
            $post->hide_smilies  = $v->hide_smilies ? 1 : 0;
        }

        // редактирование без ограничений
        if (
            $executive
            && (bool) $post->edit_post !== (bool) $v->edit_post
        ) {
            $post->edit_post     = $v->edit_post ? 1 : 0;
        }

        if ($firstPost) {
            // заголовок темы
            if ($topic->subject !== $v->subject) {
                $topic->subject  = $v->subject;
                $post->edited    = $now;
                $post->editor    = $this->user->username;
                $post->editor_id = $this->user->id;
                $calcForum       = true;
            }

            // выделение темы
            if (
                $executive
                && (bool) $topic->sticky !== (bool) $v->stick_topic
            ) {
                $topic->sticky   = $v->stick_topic ? 1 : 0;
            }

            // закрепление первого сообшения
            if (
                $executive
                && (bool) $topic->stick_fp !== (bool) $v->stick_fp
            ) {
                $topic->stick_fp = $v->stick_fp ? 1 : 0;
            }

            // опрос
            if ($this->userRules->usePoll) {
                $this->changePoll($topic, $v);
            }

            if ($this->customFieldsLevel > 0) {
                $topic->cf_data  = $this->setCFData($topic->cf_data, $this->customFieldsLevel, $v->cf_data);
                $topic->cf_level = $this->setCFLevel($topic->cf_data);
            }
        }

        // обновление сообщения
        $this->c->posts->update($post);

        // изменить (возможно!?) оглавление темы
        $topic->addPostToToc($post);

        // обновление темы
        if ($calcTopic) {
            $topic->calcStat();
        }

        $this->c->topics->update($topic);

        // обновление раздела
        if ($calcForum) {
            $topic->parent->calcStat();
        }

        $this->c->forums->update($topic->parent);

        // синхронизация вложений
        if (
            $calcAttch
            && $this->userRules->useUpload
        ) {
            $this->c->attachments->syncWithPost($post, true);
        }

        // антифлуд
        if (
            $calcPost
            || $calcForum
        ) {
            $this->user->last_post = $now;

            $this->c->users->update($this->user);
        }

        $this->c->search->index($post, 'edit');

        return $this->c->Redirect->url($post->link)->message('Edit redirect', FORK_MESS_SUCC);
    }

    /**
     * Изменяет(удаляет/добавляет) данные опроса
     */
    protected function changePoll(Topic $topic, Validator $v): void
    {
        if ($topic->poll_type > 0 ) {
            $poll = $topic->poll;

            if (! $poll->canEdit) {
                return;
            }

            // редактирование
            if ($v->poll_enable) {
                $topic->poll_type = $v->poll['duration'] > 0 ? 1000 + $v->poll['duration'] : 1; // ???? перенести в модель poll?
//                $topic->poll_time  = 0;
                $topic->poll_term = $v->poll['hide_result']
                    ? ($topic->poll_term ?: $this->c->config->i_poll_term)
                    : 0;

                $poll->__question = $v->poll['question'];
                $poll->__answer   = $v->poll['answer'];
                $poll->__type     = $v->poll['type'];

                $this->c->polls->update($poll);

            // удаление
            } else {
                $topic->poll_type = 0;
                $topic->poll_time = 0;
                $topic->poll_term = 0;

                $this->c->polls->delete($poll);
            }

        // добавление
        } elseif ($v->poll_enable) {
            $topic->poll_type = $v->poll['duration'] > 0 ? 1000 + $v->poll['duration'] : 1; // ???? перенести в модель poll?
            $topic->poll_time = \time();
            $topic->poll_term = $v->poll['hide_result'] ? $this->c->config->i_poll_term : 0;

            $poll = $this->c->polls->create([
                'tid'      => $topic->id,
                'question' => $v->poll['question'],
                'answer'   => $v->poll['answer'],
                'type'     => $v->poll['type'],
            ]);

            $this->c->polls->insert($poll);
        }
    }

    /**
     * Изменение автора и даты
     */
    public function change(array $args, string $method): Page
    {
        $post = $this->c->posts->load($args['id']);

        if (
            ! $post instanceof Post
            || ! $post->canEdit
        ) {
            return $this->c->Message->message('Bad request');
        }

        $topic     = $post->parent;
        $firstPost = $post->id === $topic->first_post_id;
        $lastPost  = $post->id === $topic->last_post_id;

        $this->c->Lang->load('post');
        $this->c->Lang->load('validator');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
            ->addValidators([
                'username_check' => [$this, 'vUsernameCheck'],
            ])->addRules([
                'token'      => 'token:ChangeAnD',
                'username'   => 'required|string|username_check',
                'posted'     => 'required|date',
                'confirm'    => 'checkbox',
                'change_and' => 'required|string',
            ])->addAliases([
                'username' => 'Username',
                'posted'   => 'Posted',
            ])->addArguments([
                'token'                   => $args,
                'username.username_check' => $post->user,
            ]);

            if ($v->validation($_POST)) {
                if ('1' !== $v->confirm) {
                    return $this->c->Redirect->url($post->link)->message('No confirm redirect', FORK_MESS_WARN);
                }

                $ids     = [];
                $upPost  = false;

                // изменить имя автора
                if (
                    $this->newUser instanceof User
                    && $this->newUser->id !== $post->user->id
                ) {
                    if (! $post->user->isGuest) {
                        $ids[] = $post->user->id;
                    }

                    if (! $this->newUser->isGuest) {
                        $ids[] = $this->newUser->id;
                    }

                    $post->poster    = $this->newUser->username;
                    $post->poster_id = $this->newUser->id;
                    $upPost          = true;
                }

                $posted = $this->c->Func->dateToTime($v->posted);

                // изменит время создания
                if (\abs($post->posted - $posted) >= 60) {
                    $post->posted    = $posted;
                    $upPost          = true;
                }

                if ($upPost) {
                    $post->edited    = \time();
                    $post->editor    = $this->user->username;
                    $post->editor_id = $this->user->id;

                    $this->c->posts->update($post);

                    if (
                        $firstPost
                        || $lastPost
                    ) {
                        $topic->calcStat();
                        $this->c->topics->update($topic);

                        if ($lastPost) {
                            $topic->parent->calcStat();
                            $this->c->forums->update($topic->parent);
                        }
                    }
                }

                if ($ids) {
                    $this->c->users->updateCountPosts(...$ids);

                    if ($firstPost) {
                        $this->c->users->updateCountTopics(...$ids);
                    }
                }

                return $this->c->Redirect->url($post->link)->message('Change redirect', FORK_MESS_SUCC);
            }

            $this->fIswev = $v->getErrors();

            $data = [
                'username' => $v->username ?: $post->poster,
                'posted'   => $v->posted ?: $this->c->Func->timeToDate($post->posted),
            ];

        } else {
            $data = [
                'username' => $post->poster,
                'posted'   => $this->c->Func->timeToDate($post->posted),
            ];
        }

        $this->identifier = 'change-and';
        $this->nameTpl    = 'post';
        $this->onlinePos  = 'topic-' . $topic->id;
        $this->robots     = 'noindex';
        $this->formTitle  = $firstPost ? 'Change AnD topic' : 'Change AnD post';
        $this->crumbs     = $this->crumbs($this->formTitle, $topic);
        $this->form       = $this->formAuthorAndDate($data, $args);

        return $this;
    }

    public function vUsernameCheck(Validator $v, string $username, $attr, User $user): string
    {
        if ($username !== $user->username) {
            $newUser = $this->c->users->loadByName($username, true);

            if ($newUser instanceof User) {
                $username      = $newUser->username;
                $this->newUser = $newUser;

            } else {
                $v->addError(['User %s does not exist', $username]);
            }
        }

        return $username;
    }

    /**
     * Возвращает данные для построения формы изменения автора поста и времени создания
     */
    protected function formAuthorAndDate(array $data, array $args): ?array
    {
        if (! $this->user->isAdmin) {
            return null;
        }

        return [
            'action' => $this->c->Router->link('ChangeAnD', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('ChangeAnD', $args),
            ],
            'sets' => [
                'author-and-date' => [
                    'fields' => [
                        'username'=> [
                            'type'      => 'text',
                            'minlength' => $this->c->USERNAME['min'],
                            'maxlength' => $this->c->USERNAME['max'],
                            'caption'   => 'Username',
                            'required'  => true,
                            'pattern'   => $this->c->USERNAME['jsPattern'],
                            'value'     => $data['username'] ?? null,
                            'autofocus' => true,
                        ],
                        'posted'=> [
                            'type'      => 'datetime-local',
                            'caption'   => 'Posted',
                            'required'  => true,
                            'value'     => $data['posted'] ?? null,
                            'step'      => '1',
                        ],
                        'confirm' => [
                            'type'      => 'checkbox',
                            'label'     => 'Confirm action',
                            'checked'   => false,
                        ],
                    ],
                ],
            ],
            'btns' => [
                'change_and' => [
                    'type'  => 'submit',
                    'value' => __('Change'),
                ],
            ],
        ];
    }
}
