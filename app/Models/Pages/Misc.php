<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Topic\Topic;
use function \ForkBB\__;

class Misc extends Page
{
    public function opensearch(): Page
    {
        $this->nameTpl      = "opensearch";
        $this->onlinePos    = null;
        $this->onlineDetail = null;
        $this->imageLink    = \preg_replace('%^(.*://(?:[^/]++)).*$%', '$1', $this->c->BASE_URL) . '/favicon.ico'; // ???? костыль O_o
        $this->searchLink   = \strtr(
            $this->c->Router->link('Search', ['keywords' => 'SEARCHTERMS']),
            ['SEARCHTERMS' => '{searchTerms}']
        );

        $this->header('Content-type', 'application/xml; charset=utf-8');

        return $this;
    }

    /**
     * Пометка раздела прочитанным
     */
    public function markread(array $args): Page
    {
        $forum = $this->c->forums->loadTree($args['id']);

        if (! $forum instanceof Forum) {
            return $this->c->Message->message('Bad request');
        }

        if (! $this->c->Csrf->verify($args['token'], 'MarkRead', $args)) {
            return $this->c->Redirect->url($forum->link)->message($this->c->Csrf->getError(), FORK_MESS_ERR);
        }

        $this->c->forums->markread($forum, $this->user);

        $this->c->Lang->load('misc');

        $message = $forum->id ? 'Mark forum read redirect' : 'Mark read redirect';

        return $this->c->Redirect->url($forum->link)->message($message, FORK_MESS_SUCC);
    }

    /**
     * Подписка на форум и отписка от него
     */
    public function forumSubscription(array $args): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'ForumSubscription', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
        }

        $forum = $this->c->forums->get($args['fid']);

        if (! $forum instanceof Forum) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('misc');

        if ('subscribe' === $args['type']) {
            if (! $this->user->email_confirmed) {
                return $this->confirmMessage();
            }

            $this->c->subscriptions->subscribe($this->user, $forum);

            $message = 'Subscribe redirect';
        } else {
            $this->c->subscriptions->unsubscribe($this->user, $forum);

            $message = 'Unsubscribe redirect';
        }

        return $this->c->Redirect->url($forum->link)->message($message, FORK_MESS_SUCC);
    }

    /**
     * Подписка на топик и отписка от него
     */
    public function topicSubscription(array $args): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'TopicSubscription', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
        }

        $topic = $this->c->topics->load($args['tid']);

        if (! $topic instanceof Topic) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('misc');

        if ('subscribe' === $args['type']) {
            if (! $this->user->email_confirmed) {
                return $this->confirmMessage();
            }

            $this->c->subscriptions->subscribe($this->user, $topic);

            $message = 'Subscribe redirect';
        } else {
            $this->c->subscriptions->unsubscribe($this->user, $topic);

            $message = 'Unsubscribe redirect';
        }

        return $this->c->Redirect->url($topic->link)->message($message, FORK_MESS_SUCC);
    }

    protected function confirmMessage(): Page
    {
        $link = $this->c->Router->link(
            'EditUserEmail',
            [
                'id' => $this->user->id,
            ]
        );

        return $this->c->Message->message(['Confirm your email address', $link], true, 100);
    }

    public array $sitemap = [];

    /**
     * Вывод sitemap
     */
    public function sitemap(array $args): Page
    {
        $gGroup = $this->c->groups->get(FORK_GROUP_GUEST);
        $forums = $this->c->ForumManager->init($gGroup);
        $id     = null === $args['id'] ? null : (int) $args['id'];
        $max    = 50000;

        $this->nameTpl = 'sitemap';

        if (1 !== $gGroup->g_read_board) {

        } elseif (null === $id) {
            // sitemap.xml
            $available = false;

            foreach ($forums->loadTree(0)->descendants as $forum) {
                if ($forum->last_post > 0) {
                    $this->sitemap[$this->c->Router->link('Sitemap', ['id' => $forum->id])] = $forum->last_post;

                    $available = true;
                }
            }

            if (1 === $gGroup->g_view_users) {
                $this->sitemap[$this->c->Router->link('Sitemap', ['id' => 0])] = null;
            }

            if (true === $available) {
                $this->sitemap[$this->c->Router->link('Sitemap', ['id' => 2147483647])] = null;
            }

            $this->nameTpl = 'sitemap_index';

        } elseif (2147483647 === $id) {
            // sitemap2147483647.xml
            $this->sitemap[$this->c->Router->link('Index')] = null;

            --$max;

            if (
                1 === $this->c->config->b_rules
                && 1 === $this->c->config->b_regs_allow
            ) {
                $this->sitemap[$this->c->Router->link('Rules')] = null;

                --$max;
            }

            $dtd = $this->c->config->i_disp_topics_default;

            foreach ($forums->loadTree(0)->descendants as $forum) {
                if ($forum->last_post > 0) {
                    $pages = (int) \ceil(($forum->num_topics ?: 1) / $dtd);
                    $page  = 1;

                    for (; $max > 0 && $page <= $pages; --$max, ++$page) {
                        $this->sitemap[$this->c->Router->link(
                            'Forum',
                            [
                                'id'   => $forum->id,
                                'name' => $forum->friendly,
                                'page' => $page,
                            ]
                        )] = null;
                    }
                }
            }

        } elseif (0 === $id) {
            // sitemap0.xml
            if (1 !== $gGroup->g_view_users) {
                return $this->c->Message->message('Bad request');
            }

            $vars = [
                ':max' => $max,
            ];
            $query = 'SELECT u.id, u.username
                FROM ::users AS u
                WHERE u.last_post!=0
                ORDER BY u.id DESC
                LIMIT ?i:max';

            $stmt = $this->c->DB->query($query, $vars);

            while ($cur = $stmt->fetch()) {
                $name = $this->c->Func->friendly($cur['username']);

                $this->sitemap[$this->c->Router->link(
                    'User',
                    [
                        'id'   => $cur['id'],
                        'name' => $name,
                    ]
                )] = null;
            }

        } else {
            // sitemapN.xml
            $forum = $forums->get($id);

            if (! $forum instanceof Forum) {
                return $this->c->Message->message('Bad request');
            }

            $dpd = $this->c->config->i_disp_posts_default;

            $vars = [
                ':fid' => $forum->id,
            ];
            $query = 'SELECT t.id, t.subject, t.last_post, t.num_replies
                FROM ::topics AS t
                WHERE t.moved_to=0 AND t.forum_id=?i:fid
                ORDER BY t.last_post DESC';

            $stmt = $this->c->DB->query($query, $vars);

            while ($cur = $stmt->fetch()) {
                $name = $this->c->Func->friendly($cur['subject']);
                $page = (int) \ceil(($cur['num_replies'] + 1) / $dpd);
                $last = $cur['last_post'];

                for (; $max > 0 && $page > 0; --$max, --$page) {
                    $this->sitemap[$this->c->Router->link(
                        'Topic',
                        [
                            'id'   => $cur['id'],
                            'name' => $name,
                            'page' => $page,
                        ]
                    )] = $last;

                    $last = null;
                }
            }
        }

        $this->onlinePos    = 'sitemap';
        $this->onlineDetail = null;

        $this->header('Content-type', 'application/xml; charset=utf-8');

        $d = \number_format(\microtime(true) - $this->c->START, 3);

        $this->c->Log->debug("{$this->nameTpl} : {$id} : time = {$d}", [
            'user'    => $this->user->fLog(),
            'headers' => true,
        ]);

        if (empty($this->sitemap)) {
            return $this->c->Message->message('Bad request');
        } else {
            return $this;
        }
    }
}
