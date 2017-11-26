<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;

class Forum extends Page
{
    use CrumbTrait;

    /**
     * Подготовка данных для шаблона
     * @param array $args
     * @return Page
     */
    public function view(array $args)
    {
        $this->c->Lang->load('forum');
        $this->c->Lang->load('subforums');

        $forum = $this->c->forums->loadTree($args['id']);
        if (empty($forum)) {
            return $this->c->Message->message('Bad request');
        }

        // редирект, если раздел это ссылка
        if (! empty($forum->redirect_url)) {
            return $this->c->Redirect->url($forum->redirect_url);
        }

        $page = isset($args['page']) ? (int) $args['page'] : 1;
        if (! $forum->hasPage($page)) {
            return $this->c->Message->message('Bad request');
        }

        $topics = $forum->topics();
        $user = $this->c->user;

        if (! $user->isGuest) {
            $lower = max((int) $user->u_mark_all_read, (int) $forum->mf_mark_all_read);
            $upper = max($lower, (int) $user->last_visit);
        }

        if (empty($topics)) {
            $this->a['fIswev']['i'][] = __('Empty forum');
        }
        $newOn = $forum->post_topics == 1
            || (null === $forum->post_topics && $user->g_post_topics == 1)
            || $user->isAdmin
            || ($user->isAdmMod && isset($forum->moderators[$user->id]));

        $this->fIndex     = 'index';
        $this->nameTpl    = 'forum';
        $this->onlinePos  = 'forum-' . $args['id'];
        $this->canonical  = $this->c->Router->link('Forum', ['id' => $args['id'], 'name' => $forum->forum_name, 'page' => $forum->page]);
        $this->forum      = $forum;
        $this->forums     = $forum->subforums;
        $this->topics     = $topics;
        $this->crumbs     = $this->crumbs($forum);
        $this->newTopic   = $newOn ? $this->c->Router->link('NewTopic', ['id' => $args['id']]) : null;
        $this->pages      = $this->c->Func->paginate($forum->pages, $forum->page, 'Forum', ['id' => $args['id'], 'name' => $forum->forum_name]);

        return $this;
    }
}
