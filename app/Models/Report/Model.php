<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Report;

use ForkBB\Models\DataModel;
use ForkBB\Models\Post\Model as Post;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class Model extends DataModel
{
    /**
     * Устанавливает автора
     */
    protected function setauthor(User $user): void
    {
        if ($user->isGuest) {
            throw new RuntimeException('Bad author');
        }

        $this->reported_by = $user->id;
    }

    /**
     * Автор сигнала
     */
    protected function getauthor(): User
    {
        if ($this->reported_by < 1) {
            throw new RuntimeException('No author data');
        }

        $user = $this->c->users->load($this->reported_by);

        if (
            ! $user instanceof User
            || $user->isGuest
        ) {
            $user = $this->c->users->create();

            $user->__id       = $this->reported_by;
            $user->__username = '{User #' . $this->reported_by .'}';
        }

        return $user;
    }

    /**
     * Устанавливает расмотревшего
     */
    protected function setmarker(User $user): void
    {
        if (! empty($this->zapped_by)) {
            throw new RuntimeException('Report already has a marker');
        } elseif ($user->isGuest) {
            throw new RuntimeException('Bad marker');
        }

        $this->zapped_by = $user->id;
        $this->zapped    = \time();
    }

    /**
     * Рвассмотревший
     */
    protected function getmarker(): User
    {
        if ($this->zapped_by < 1) {
            throw new RuntimeException('No marker data');
        }

        $user = $this->c->users->load($this->zapped_by);

        if (
            ! $user instanceof User
            || $user->isGuest
        ) {
            $user = $this->c->users->create();

            $user->__id       = $this->zapped_by;
            $user->__username = '{User #' . $this->zapped_by .'}';
        }

        return $user;
    }

    /**
     * Устанавливает пост
     */
    protected function setpost(Post $post): void
    {
        if ($post->id < 1) {
            throw new RuntimeException('Bad post');
        }

        $this->post_id = $post->id;
    }

    /**
     * Пост
     */
    protected function getpost(): ?Post
    {
        if ($this->post_id < 1) {
            throw new RuntimeException('No post data');
        }

        return $this->c->posts->load($this->post_id);
    }

    /**
     * Ссылка на рассмотрение
     */
    public function getlinkZap(): string
    {
        if (empty($this->zapped)) {
            return $this->c->Router->link(
                'AdminReportsZap',
                [
                    'id' => $this->id,
                ]
            );
        } else {
            return '';
        }
    }
}
