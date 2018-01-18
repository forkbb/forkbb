<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;

class Forum extends Page
{
    use CrumbTrait;

    /**
     * Подготовка данных для шаблона
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function view(array $args)
    {
        $this->c->Lang->load('forum');
        $this->c->Lang->load('subforums');

        $forum = $this->c->forums->loadTree($args['id']);
        if (null === $forum) {
            return $this->c->Message->message('Bad request');
        }

        // редирект, если раздел это ссылка
        if ($forum->redirect_url) {
            return $this->c->Redirect->url($forum->redirect_url);
        }

        $forum->page = isset($args['page']) ? (int) $args['page'] : 1;
        if (! $forum->hasPage()) {
            return $this->c->Message->message('Bad request');
        }

        $this->fIndex     = 'index';
        $this->nameTpl    = 'forum';
        $this->onlinePos  = 'forum-' . $args['id'];
        $this->canonical  = $this->c->Router->link('Forum', ['id' => $args['id'], 'name' => $forum->forum_name, 'page' => $forum->page]);
        $this->model      = $forum;
        $this->topics     = $forum->pageData();
        $this->crumbs     = $this->crumbs($forum);

        if (empty($this->topics)) {
            $this->a['fIswev']['i'][] = \ForkBB\__('Empty forum');
        }

        return $this;
    }
}
