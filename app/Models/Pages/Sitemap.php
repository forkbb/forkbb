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
use ForkBB\Models\Forum\Forums;
use ForkBB\Models\Group\Group;

class Sitemap extends Page
{

    public array $sitemap = [];

    /**
     * Вывод sitemap
     */
    public function view(array $args): Page
    {
        $this->nameTpl      = 'sitemap';
        $this->onlinePos    = 'sitemap';
        $this->onlineDetail = null;

        $gGroup = $this->c->groups->get(FORK_GROUP_GUEST);
        $forums = $this->c->ForumManager->init($gGroup);
        $max    = 50000;

        if (1 === $gGroup->g_read_board) {
            $result = match ($args['id']) {
                null    => $this->sitemap($forums, $gGroup, $max),
                '0'     => $this->sitemap0($forums, $gGroup, $max),
                '00'    => $this->sitemap00($forums, $gGroup, $max),
                default => $this->sitemapN($forums, $gGroup, $max, $args['id']),
            };
        }

        $d = \number_format(\microtime(true) - $this->c->START, 3);

        $this->c->Log->debug("{$this->nameTpl} : {$args['id']} : time = {$d}", [
            'user'    => $this->user->fLog(),
            'headers' => true,
        ]);

        if (empty($this->sitemap)) {
            return $this->c->Message->message('Bad request');
        } else {
            $this->header('Content-type', 'application/xml; charset=utf-8');

            return $this;
        }
    }

    protected function sitemap(Forums $forums, Group $gGroup, int $max): bool
    {
        foreach ($forums->loadTree(0)->descendants as $forum) {
            if ($forum->last_post > 0) {
                $this->sitemap[$this->c->Router->link('Sitemap', ['id' => $forum->id])] = $forum->last_post;
            }
        }

        if (1 === $gGroup->g_view_users) {
            $this->sitemap[$this->c->Router->link('Sitemap', ['id' => '0'])] = null;
        }

        $this->sitemap[$this->c->Router->link('Sitemap', ['id' => '00'])] = null;

        $this->nameTpl = 'sitemap_index';

        return true;
    }

    protected function sitemap00(Forums $forums, Group $gGroup, int $max): bool
    {
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

        return true;
    }

    protected function sitemap0(Forums $forums, Group $gGroup, int $max): bool
    {
        if (1 !== $gGroup->g_view_users) {
            return false;
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

        return true;
    }

    protected function sitemapN(Forums $forums, Group $gGroup, int $max, string $raw): bool
    {
        if (! \preg_match('%^[1-9]\d*$%', $raw)) {
            return false;
        }

        $id    = (int) $raw;
        $forum = $forums->get($id);

        if (! $forum instanceof Forum) {
            return false;
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

        return false;
    }
}
