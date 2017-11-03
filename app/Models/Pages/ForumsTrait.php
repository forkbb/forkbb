<?php

namespace ForkBB\Models\Pages;

trait ForumsTrait 
{
    /**
     * Получение данных по разделам
     * 
     * @param int $rootId
     * 
     * @return array
     */
    protected function forumsData($rootId = 0)
    {
        $root = $this->c->forums->loadTree($rootId);
        if (empty($root)) {
            return [];
        }

        $r = $this->c->Router;

        // формированием таблицы разделов
        $result = [];
        foreach ($root->subforums as $forumId => $forum) {
            // модераторы
            $moderators = [];
            if (! empty($forum->moderators)) {
                $mods = unserialize($forum->moderators);
                foreach ($mods as $name => $id) {
                    if ($this->c->user->g_view_users == '1') {
                        $moderators[] = [
                            $r->link('User', [
                                'id' => $id,
                                'name' => $name,
                            ]),
                            $name
                        ];
                    } else {
                        $moderators[] = $name;
                    }
                }
            }

            // список подразделов
            $subForums = [];
            foreach ($forum->subforums as $subId => $subforum) {
                $subForums[] = [
                    $r->link('Forum', [
                        'id'   => $subId,
                        'name' => $subforum->forum_name,
                    ]),
                    $subforum->forum_name,
                ];
            }

            // статистика по разделам
            $numT   = (int) $forum->num_topics;
            $numP   = (int) $forum->num_posts;
            $time   = (int) $forum->last_post;
            $postId = (int) $forum->last_post_id;
            $poster = $forum->last_poster;
            $topic  = $forum->last_topic;
            $fnew   = $forum->newMessages;
            foreach ($forum->descendants as $chId => $children) {
                $fnew  = $fnew || $children->newMessages;
                $numT += $children->num_topics;
                $numP += $children->num_posts;
                if ($children->last_post > $time) {
                    $time   = $children->last_post;
                    $postId = $children->last_post_id;
                    $poster = $children->last_poster;
                    $topic  = $children->last_topic;
                }
            }

            $result[$forum->cid]['name'] = $forum->cat_name;
            $result[$forum->cid]['forums'][] = [
                'fid'          => $forumId,
                'forum_name'   => $forum->forum_name,
                'forum_desc'   => $forum->forum_desc,
                'forum_link'   => $r->link('Forum', [
                    'id'   => $forumId,
                    'name' => $forum->forum_name,
                ]),
                'redirect_url' => $forum->redirect_url,
                'subforums'    => $subForums,
                'moderators'   => $moderators,
                'num_topics'   => $numT,
                'num_posts'    => $numP,
                'topics'       => $this->number($numT),
                'posts'        => $this->number($numP),
                'last_post'    => $this->time($time),
                'last_post_id' => $postId > 0 ? $r->link('ViewPost', ['id' => $postId]) : null,
                'last_poster'  => $poster,
                'last_topic'   => $this->c->censorship->censor($topic),
                'new'          => $fnew,
            ];
        }
        return $result;
    }
}
