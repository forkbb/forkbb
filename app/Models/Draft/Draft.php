<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Draft;

use ForkBB\Models\DataModel;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Topic\Topic;
use ForkBB\Models\User\User;
use RuntimeException;

class Draft extends DataModel
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Draft';

    /**
     * Получение родительской темы
     */
    protected function getparent(): ?Topic
    {
        $topic = $this->topic_id > 0
            ? $this->c->topics->load($this->topic_id)
            : $this->c->topics->create([
                'id'       => 0,
                'subject'  => $this->subject,
                'forum_id' => $this->forum_id,
            ]);

        if (
            ! $topic instanceof Topic
            || $topic->moved_to
            || ! $topic->parent instanceof Forum
        ) {
            return null;
        } else {
            return $topic;
        }
    }

    /**
     * Ссылка на черновик
     */
    protected function getlink(): string
    {
        return $this->c->Router->link(
            'Draft',
            [
                'did' => $this->id,
            ]
        );
    }

    /**
     * Автор черновика
     */
    protected function getuser(): User
    {
        $user = $this->c->users->load($this->poster_id);

        if (! $user instanceof User) {
            throw new RuntimeException("No user data in post number {$this->id}");
        }

        return $user;
    }

    /**
     * Ссылка на страницу удаления
     */
    protected function getlinkDelete(): string
    {
        return $this->c->Router->link(
            'DeleteDraft',
            [
                'did' => $this->id,
            ]
        );
    }

    /**
     * Ссылка на страницу данных об ip
     */
    protected function getlinkGetHost(): string
    {
        return $this->c->Router->link(
            'AdminHost',
            [
                'ip' => $this->poster_ip,
            ]
        );
    }

    /**
     * HTML код сообщения
     */
    public function html(): string
    {
        return $this->c->censorship->censor($this->c->Parser->parseMessage($this->message, (bool) $this->hide_smilies));
    }

    protected function getform_data(): array
    {
        $value = $this->getModelAttr('form_data');

        return empty($value) ? [] : \json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
    }

    protected function setform_data(array $value): void
    {
        $this->setModelAttr('form_data', empty($value) ? '' : \json_encode($value, FORK_JSON_ENCODE));
    }
}
