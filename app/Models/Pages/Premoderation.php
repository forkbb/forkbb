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
use ForkBB\Models\Draft\Draft;
use ForkBB\Models\Page;
use function \ForkBB\__;

class Premoderation extends Page
{
    use PostCFTrait;

    /**
     * Отображает очередь премодерации
     */
    public function view(array $args, string $method): Page
    {
        $this->c->Lang->load('draft');

        $premod = $this->c->premod->init();

        if ($premod->count < 1) {
            return $this->c->Message->message('Pre-moderation queue is empty', false, 199);
        }

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'   => 'token:Premoderation',
                    'page'    => 'integer|min:1|max:9999999999',
                    'draft'   => 'required|array',
                    'draft.*' => 'required|integer|in:-1,0,1',
                    'confirm' => 'required|integer|in:1',
                    'execute' => 'string',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                    'confirm' => 'No confirm redirect',
                ]);

            if ($v->validation($_POST)) {
                $this->actions($v->draft);

                return $this->c->Redirect->page('Premoderation', ['page' => $v->page])->message('Selected posts processed redirect', FORK_MESS_SUCC);
            }

            $this->fIswev = $v->getErrors();
        }

        $this->numPage = $args['page'] ?? 1;
        $this->drafts  = $premod->view($this->numPage);

        if (empty($this->drafts)) {
            return $this->c->Message->message('Page missing', false, 404);
        }

        $this->numPages   = $premod->numPages();
        $this->pagination = $this->c->Func->paginate($this->numPages, $this->numPage, 'Premoderation');
        $this->useMediaJS = true;
        $this->nameTpl    = 'premod';
        $this->identifier = 'search-result';
        $this->fIndex     = self::FI_PREMOD;
        $this->onlinePos  = 'premod';
        $this->robots     = 'noindex';
        $this->crumbs     = $this->crumbs([$this->c->Router->link('Premoderation'), 'Pre-moderation']);
        $this->formAction = $this->formAction();

        $this->c->Parser; // предзагрузка

        $this->c->Lang->load('search');

        return $this;
    }


    /**
     * Создает массив данных для формы
     */
    protected function formAction(): array
    {
        return [
            'id'     => 'id-form-action',
            'action' => $this->c->Router->link('Premoderation'),
            'hidden' => [
                'token' => $this->c->Csrf->create('Premoderation'),
                'page'  => $this->numPage,
            ],
            'sets'   => [
                'confirm' => [
                    'fields' => [
                        'confirm' => [
                            'type'    => 'checkbox',
                            'label'   => 'Confirm action',
                            'checked' => false,
                        ],
                    ],
                ],

            ],
            'btns'   => [
                'execute' => [
                    'type'  => 'submit',
                    'value' => __('Execute'),
                ],
            ],
        ];
    }

    protected function actions(array $list): void
    {
        $forDelete  = [];
        $forPublish = [];
        $available  = \array_flip($this->c->premod->idList);

        foreach ($list as $id => $action) {
            if (
                empty($action)
                || ! isset($available[$id])
            ) {
                continue;
            }

            switch ($action) {
                case 1:
                    $forPublish[$id] = $id;
                                             // публикуемые черновики тоже удаляем
                case -1:
                    $forDelete[$id] = $id;

                    break;
            }
        }

        if (! empty($forDelete)) {
            $drafts = $this->c->drafts->loadByIds($forDelete);

            if (! empty($forPublish)) {
                $this->c->Online->calc($this); // для подписок
                $this->c->forums->loadTree(0); // актуальные данные по разделам

                foreach ($this->c->drafts->loadByIds($forPublish) as $draft) {
                    $this->publish($draft);
                }
            }

            $this->c->drafts->delete(...$drafts);
        }
    }


    /**
     * Создает тему/сообщение
     */
    protected function publish(Draft $draft): void
    {
        $now   = \time();
        $form  = $draft->form_data;
        $user  = $draft->user;
        $topic = $draft->parent;
        $forum = $topic->parent;
        $merge = false;

        // подготовка к объединению/сохранению сообщения
        if ($draft->topic_id > 0) {
            $createTopic = false;

            if (
                ! $user->isGuest
                && $topic->last_poster_id === $user->id
                && (
                    $topic->first_post_id !== $topic->last_post_id
                    || 0 === $topic->poll_type
                )
            ) {
                $merge = true;
            }

        // создание темы
        } else {
            $createTopic = true;

            $topic->id             = null;
            $topic->poster         = $user->username;
            $topic->poster_id      = $user->id;
            $topic->last_poster    = $user->username;
            $topic->last_poster_id = $user->id;
            $topic->posted         = $now;
            $topic->last_post      = $now;
            $topic->sticky         = empty($form['stick_topic']) ? 0 : 1;
            $topic->stick_fp       = empty($form['stick_fp']) ? 0 : 1;

            if (! empty($form['cf_data'])) {
                $topic->cf_data  = $this->setCFData($forum->custom_fields, 4, $form['cf_data']);
                $topic->cf_level = $this->setCFLevel($topic->cf_data);
            }

            $this->c->topics->insert($topic);
        }

        // попытка объеденить новое сообщение с крайним в теме
        if ($merge) {
            $lastPost  = $this->c->posts->load($topic->last_post_id, $topic->id);
            $newLength = \mb_strlen($lastPost->message . $draft->message, 'UTF-8');

            if ($newLength < $this->c->MAX_POST_SIZE - 100) {
                $lastPost->message   = $lastPost->message . "\n[after=" . ($now - $topic->last_post) . "]\n" . $draft->message; //????
                $lastPost->edited    = $now;
                $lastPost->editor    = $this->user->username;
                $lastPost->editor_id = $this->user->id;

                $this->c->posts->update($lastPost);

            } else {
                $merge = false;
            }
        }

        // создание нового сообщения
        if (! $merge) {
            $post = $this->c->posts->create();

            $post->poster       = $user->username;
            $post->poster_id    = $user->id;
            $post->poster_ip    = $draft->poster_ip;
            $post->poster_email = $user->isGuest ? $user->email : '';
            $post->message      = $draft->message;
            $post->hide_smilies = $draft->hide_smilies ? 1 : 0;
            $post->posted       = $now;
            $post->user_agent   = $draft->user_agent;
            $post->topic_id     = $topic->id;

            $this->c->posts->insert($post);
        }

        if ($createTopic) {
            $topic->forum_id      = $forum->id;
            $topic->first_post_id = $post->id;

            if (! empty($form['poll_enable'])) {
                $topic->poll_type  = $form['poll']['duration'] > 0 ? 1000 + $form['poll']['duration'] : 1; // ???? перенести в модель poll?
                $topic->poll_time  = $now;
                $topic->poll_term  = $form['poll']['hide_result'] ? $this->c->config->i_poll_term : 0;

                $poll = $this->c->polls->create([
                    'tid'      => $topic->id,
                    'question' => $form['poll']['question'],
                    'answer'   => $form['poll']['answer'],
                    'type'     => $form['poll']['type'],
                ]);

                $this->c->polls->insert($poll);
            }
        }

        // изменить (возможно!?) оглавление темы
        $this->c->Parser->prepare($draft->message);
        $topic->addPostToToc($merge ? $lastPost : $post, $merge);

        // обновление данных в теме и разделе
        $this->c->topics->update($topic->calcStat());
        $this->c->forums->update($forum->calcStat());

        // синхронизация вложений
        if ($this->userRules->useUpload) {
            $this->c->attachments->syncWithPost($merge ? $lastPost : $post);
        }

        // обновление данных текущего пользователя
        if (
            ! $merge
            && ! $user->isGuest
        ) {
            if (0 == $forum->no_sum_mess) {
                $user->num_posts = $user->num_posts + 1;

                if (
                    $user->g_promote_next_group > 0
                    && $user->num_posts >= $user->g_promote_min_posts
                ) {
                    $user->group_id = $user->g_promote_next_group;
                }
            }

            if ($createTopic) {
                $user->num_topics = $user->num_topics + 1;
            }
        }

        $user->last_post = $now;

        $this->c->users->update($user);

        if ($merge) {
            $this->c->search->index($lastPost, 'merge');

        } else {
            $this->c->search->index($post);

            if ($createTopic) {
                if (1 === $this->c->config->b_forum_subscriptions) { // ????
                    $this->c->subscriptions->send($post, $topic);
                }

            } else {
                if (1 === $this->c->config->b_topic_subscriptions) { // ????
                    $this->c->subscriptions->send($post);
                }
            }
        }
    }
}
