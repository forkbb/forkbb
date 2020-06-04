<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\Post\Model as Post;
use ForkBB\Models\Report\Model as Report;

class Reports extends Admin
{
    /**
     * Подготавливает данные для шаблона
     *
     * @return Page
     */
    public function view(): Page
    {
        if ($this->user->last_report_id < $this->c->reports->lastId()) {
            $this->user->last_report_id = $this->c->reports->lastId();

            $this->c->users->update($this->user);
        }

        $this->c->Lang->load('admin_reports');

        $this->nameTpl  = 'admin/reports';
        $this->aIndex   = 'reports';
        $this->formNew  = $this->form(true);
        $this->formOld  = $this->form(false);

        return $this;
    }

    /**
     * Формирует данные для формы
     *
     * @param bool $noZapped
     *
     * @return array
     */
    protected function form(bool $noZapped): array
    {
        $form = [
            'sets'   => [],
        ];

        foreach ($this->c->reports->loadList($noZapped) as $report) {
            if ($noZapped) {
                $cur = [
                    'legend' => \ForkBB\__('Reported %s', \ForkBB\dt($report->created)),
                ];
            } else {
                $cur = [
                    'legend' => \ForkBB\__('Marked as read %1$s by %2$s', \ForkBB\dt($report->zapped), $report->marker->username),
                ];
            }
            $cur['fields'] = [];
            $author = $report->author;
            $cur['fields']['report_by' . $report->id] = [
                'type'    => $author->isGuest ? 'str' : 'link',
                'value'   => $author->username,
                'title'   => $author->username,
                'caption' => \ForkBB\__('Reported by'),
                'href'    => $author->link,
            ];
            $post = $report->post;
            if ($post instanceof Post) {
                $topic = $post->parent;
                $forum = $topic->parent;
                $cur['fields']['post' . $report->id] = [
                    'type'    => 'str',
                    'value'   => \ForkBB\__('Post #%s ', $post->id, $post->link, $topic->subject, $topic->link, $forum->forum_name, $forum->link),
                    'html'    => true,
                ];
            } else {
                $cur['fields']['post' . $report->id] = [
                    'type'    => 'str',
                    'value'   => \ForkBB\__('Post #%s', $report->post_id),
                ];
            }
            $cur['fields']['reason' . $report->id] = [
                'type'    => 'str',
                'value'   => $report->message,
                'caption' => \ForkBB\__('Reason'),
            ];
            if ($noZapped) {
                $cur['fields']['zap' . $report->id] = [
                    'type'    => 'btn',
                    'value'   => \ForkBB\__('Zap'),
                    'title'   => \ForkBB\__('Zap'),
                    'link'    => $report->linkZap,
                ];
            }
            $form['sets'][$report->id] = $cur;
        }

        if (empty($form['sets'])) {
            $form['sets'][] = [
                'info' => [
                    'info1' => [
                        'type'  => '', //????
                        'value' => $noZapped ? \ForkBB\__('No new reports') : \ForkBB\__('No zapped reports'),
                    ],
                ],
            ];
        }

        return $form;
    }

    /**
     * Помечает сигнал обработанным
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function zap(array $args, string $method): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'AdminReportsZap', $args)) {
            return $this->c->Redirect->url($forum->link)->message('Bad token');
        }

        $this->c->Lang->load('admin_reports');

        $report = $this->c->reports->load((int) $args['id']);

        if ($report instanceof Report) {
            $report->zapped = \time(); // ???? перенести в модель?
            $report->marker = $this->user;

            $this->c->reports->update($report);
            $this->c->reports->clear();
        }

        return $this->c->Redirect->page('AdminReports')->message('Report zapped redirect');
    }
}
