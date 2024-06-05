<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Forum;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Forum;

class LoadTree extends Action
{
    /**
     * Загружает данные в модели для указанного раздела и всех его потомков
     */
    public function loadTree(int $rootId): ?Forum
    {
        $root = $this->manager->get($rootId);

        if (null === $root) {
            return null;
        }

        $list = [];

        if (! $root->ready) {
            $list[$rootId] = $root;
        }

        foreach ($root->descendants as $id => $descendant) {
            if (! $descendant->ready) {
                $list[$id] = $descendant;
            }
        }

        $this->loadData($list);

        if (! $this->c->user->isGuest) {
            $this->checkForNew($root->descendants);
        }

        return $root;
    }

    /**
     * Загружает данные из БД по списку разделов
     */
    protected function loadData(array $list): void
    {
        if (empty($list)) {
            return;
        }

        $vars = [
            ':uid'    => $this->c->user->id,
            ':forums' => \array_keys($list),
        ];

        if ($this->c->user->isGuest) {
            $query = 'SELECT f.id, f.forum_desc, f.num_topics, f.num_posts,
                    f.last_post, f.last_post_id, f.last_poster, f.last_topic, f.custom_fields
                FROM ::forums AS f
                WHERE id IN (?ai:forums)';
        } elseif (1 === $this->c->config->b_forum_subscriptions) {
            $query = 'SELECT f.id, f.forum_desc, f.num_topics, f.num_posts,
                    f.last_post, f.last_post_id, f.last_poster, f.last_topic, f.custom_fields,
                    mof.mf_mark_all_read, s.user_id AS is_subscribed
                FROM ::forums AS f
                LEFT JOIN ::forum_subscriptions AS s ON (s.user_id=?i:uid AND s.forum_id=f.id)
                LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:uid AND mof.fid=f.id)
                WHERE f.id IN (?ai:forums)';
        } else {
            $query = 'SELECT f.id, f.forum_desc, f.num_topics, f.num_posts,
                    f.last_post, f.last_post_id, f.last_poster, f.last_topic, f.custom_fields,
                    mof.mf_mark_all_read
                FROM ::forums AS f
                LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:uid AND mof.fid=f.id)
                WHERE f.id IN (?ai:forums)';
        }

        $stmt = $this->c->DB->query($query, $vars);

        while ($cur = $stmt->fetch()) {
            $list[$cur['id']]->replAttrs($cur)->__ready = true;
        }
    }

    /**
     * Проверяет наличие новых сообщений в разделах по их списку
     */
    protected function checkForNew(array $list): void
    {
        if (
            empty($list)
            || $this->c->user->isGuest
        ) {
            return;
        }

        // предварительная проверка разделов
        $time = [];
        $max  = \max((int) $this->c->user->last_visit, (int) $this->c->user->u_mark_all_read);

        foreach ($list as $forum) {
            $t = \max($max, (int) $forum->mf_mark_all_read);

            if ($forum->last_post > $t) {
                $time[$forum->id] = $t;
            }
        }

        if (empty($time)) {
            return;
        }

        // проверка по темам
        $vars = [
            ':uid'    => $this->c->user->id,
            ':forums' => \array_keys($time),
            ':max'    => $max,
        ];
        $query = 'SELECT t.forum_id, t.last_post
            FROM ::topics AS t
            LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND mot.tid=t.id)
            WHERE t.forum_id IN (?ai:forums)
                AND t.moved_to=0
                AND t.last_post>?i:max
                AND (mot.mt_last_visit IS NULL OR t.last_post>mot.mt_last_visit)';

        $stmt = $this->c->DB->query($query, $vars);

        while ($cur = $stmt->fetch()) {
            if ($cur['last_post'] > $time[$cur['forum_id']]) {
                $list[$cur['forum_id']]->__newMessages = true; //????
            }
        }
    }
}
