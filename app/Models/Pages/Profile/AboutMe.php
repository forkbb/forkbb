<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\PostFormTrait;
use ForkBB\Models\Pages\PostValidatorTrait;
use ForkBB\Models\Pages\Profile;
use ForkBB\Models\Post\Post;
use function \ForkBB\__;

class AboutMe extends Profile
{
    use PostFormTrait;
    use PostValidatorTrait;

    /**
     * Подготавливает данные для шаблона Обо мне
     */
    public function about(array $args, string $method): Page
    {
        if (
            false === $this->initProfile($args['id'])
            || ! $this->rules->editAboutMe
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('post');
        $this->c->Lang->load('validator');

        $forum = $this->c->forums->create([
            'id'              => FORK_SFID,
            'parent_forum_id' => 0,
        ]);
        $this->c->forums->set(FORK_SFID, $forum);

        if ($this->curUser->about_me_id > 0) {
            $post = $this->c->posts->load($this->curUser->about_me_id);

        } else {
            $post = null;
        }

        if (! $post instanceof Post) {
            $post = $this->c->posts->create([
                'poster_id'    => $this->curUser->id,
                'poster'       => $this->curUser->username,
                'poster_email' => $this->curUser->email,
                'topic_id'     => $this->c->config->i_about_me_topic_id,
            ]);
        }

        if ('POST' === $method) {
            $v = $this->messageValidator($post, 'EditUserAboutMe', $args, true, false, true);

            if (
                $v->validation($_POST)
                && null === $v->preview
                && null !== $v->submit
            ) {
                return $this->endAbout($post, $v);
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
            }

        } else {
            $args['_vars'] = [
                'message'      => $post->message,
                'hide_smilies' => $post->hide_smilies,
                'edit_post'    => 1,
            ];
        }

        $this->hhsLevel    = 'common'; // для остальных страниц профиля уровень задан в initProfile()
        $this->nameTpl     = 'post';
        $this->formTitle   = 'About me';
        $this->form        = $this->messageForm($post, 'EditUserAboutMe', $args, true, false, false, true);
        $this->identifier  = ['profile', 'profile-search'];
        $this->crumbs      = $this->crumbs(
            [
                $this->c->Router->link('EditUserAboutMe', $args),
                'Change about me',
            ],
            [
                $this->c->Router->link('EditUserProfile', $args),
                'Editing profile',
            ]
        );

        return $this;
    }

    /**
     * Сохраняет изменения
     */
    protected function endAbout(Post $post, Validator $v): Page
    {
        $now   = \time();
        $topic = $post->parent;

        if ('' === $v->message) {
            if ($post->id > 0) {
                $this->c->posts->delete($post);
            }

            $this->curUser->about_me_id = 0;

            $this->c->users->update($this->curUser);

        } elseif (empty($post->id)) {
            $post->poster_ip    = $this->user->ip;
            $post->message      = $v->message;
            $post->hide_smilies = $v->hide_smilies ? 1 : 0;
            $post->edit_post    = 1;
            $post->posted       = $now;
            $post->edited       = $now;
            $post->editor       = $this->user->username;
            $post->editor_id    = $this->user->id;
            $post->user_agent   = \mb_substr($this->user->userAgent, 0, 255, 'UTF-8');

            $this->c->posts->insert($post);
            $this->c->topics->update($topic->calcStat());

            if ($this->c->userRules->useUpload) {
                $this->c->attachments->syncWithPost($post);
            }

            $this->curUser->about_me_id = $post->id;
            $this->user->last_post      = $now;

            $this->c->users->update($this->user);

            if ($this->user->id !== $this->curUser->id) {
                $this->c->users->update($this->curUser);
            }

        } else {
            $calcPost  = false;
            $calcTopic = false;

            // текст сообщения
            if ($post->message !== $v->message) {
                $calcPost        = true;
                $post->message   = $v->message;
                $post->edited    = $now;
                $post->editor    = $this->user->username;
                $post->editor_id = $this->user->id;
                $post->edit_post = 1;

                if ($post->id === $topic->last_post_id) {
                    $calcTopic = true;
                }
            }

            if (
                1 === $this->c->config->b_smilies
                && (bool) $post->hide_smilies !== (bool) $v->hide_smilies
            ) {
                $post->hide_smilies = $v->hide_smilies ? 1 : 0;
            }

            $this->c->posts->update($post);

            if ($calcTopic) {
                $topic->calcStat();
            }

            $this->c->topics->update($topic);

            if ($calcPost) {
                if ($this->c->userRules->useUpload) {
                    $this->c->attachments->syncWithPost($post, true);
                }

                $this->user->last_post = $now;

                $this->c->users->update($this->user);
            }
        }

        return $this->c->Redirect->url($this->curUser->link)->message('Edit redirect', FORK_MESS_SUCC);
    }
}
