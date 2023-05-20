<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\Post\Post;
use ForkBB\Models\Report\Report;
use function \ForkBB\__;
use function \ForkBB\dt;

class Reports extends Admin
{
    protected array $userIds = [];
    protected array $postIds = [];

    /**
     * Выделяет данные из списка сигналов
     */
    protected function dataFromReports(array $reports): void
    {
        foreach ($reports as $report) {
            $this->userIds[$report->reported_by] = $report->reported_by;
            $this->userIds[$report->zapped_by]   = $report->zapped_by;
            $this->postIds[$report->post_id]     = $report->post_id;
        }

        unset($this->userIds[0]);
    }

    /**
     * Подготавливает данные для шаблона
     */
    public function view(): Page
    {
        if ($this->user->last_report_id < $this->c->reports->lastId()) {
            $this->user->last_report_id = $this->c->reports->lastId();

            $this->c->users->update($this->user);
        }

        $this->c->Lang->load('admin_reports');

        $listNew = $this->c->reports->loadList(true);
        $listOld = $this->c->reports->loadList(false);

        $this->dataFromReports($listNew);
        $this->dataFromReports($listOld);

        $this->c->users->loadByIds($this->userIds);
        $this->c->posts->loadByIds($this->postIds);

        $this->nameTpl  = 'admin/reports';
        $this->aIndex   = 'reports';
        $this->formNew  = $this->form(true, $listNew);
        $this->formOld  = $this->form(false, $listOld);

        return $this;
    }

    /**
     * Формирует данные для формы
     */
    protected function form(bool $noZapped, array $reports): array
    {
        $form = [
            'sets' => [],
        ];

        foreach ($reports as $report) {
            if ($noZapped) {
                $cur = [
                    'legend' => ['Reported %s', dt($report->created)],
                ];
            } else {
                $cur = [
                    'legend' => ['Marked as read %1$s by %2$s', dt($report->zapped), $report->marker->username],
                ];
            }

            $cur['fields'] = [];
            $author = $report->author;
            $cur['fields']['report_by' . $report->id] = [
                'type'    => $author->isGuest ? 'str' : 'link',
                'value'   => $author->username,
                'title'   => $author->username,
                'caption' => 'Reported by',
                'href'    => $author->link,
            ];
            $post = $report->post;

            if ($post instanceof Post) {
                $topic = $post->parent;
                $forum = $topic->parent;
                $cur['fields']['post' . $report->id] = [
                    'type'    => 'str',
                    'value'   => __(['Post #%s ', $post->id, $post->link, $topic->name, $topic->link, $forum->forum_name, $forum->link]),
                    'html'    => true,
                ];
            } else {
                $cur['fields']['post' . $report->id] = [
                    'type'    => 'str',
                    'value'   => __(['Post #%s', $report->post_id]),
                ];
            }

            $cur['fields']['reason' . $report->id] = [
                'class'   => ['reason'],
                'type'    => 'str',
                'value'   => $report->message,
                'caption' => 'Reason',
            ];

            if ($noZapped) {
                $cur['fields']['zap' . $report->id] = [
                    'type'    => 'btn',
                    'value'   => __('Zap'),
                    'title'   => __('Zap'),
                    'link'    => $report->linkZap,
                ];
            }

            $form['sets'][$report->id] = $cur;
        }

        if (empty($form['sets'])) {
            $form['sets'][] = [
                'inform' => [
                    [
                        'message' => $noZapped ? 'No new reports' : 'No zapped reports',
                    ],
                ],
            ];
        }

        return $form;
    }

    /**
     * Помечает сигнал обработанным
     */
    public function zap(array $args, string $method): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'AdminReportsZap', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
        }

        $this->c->Lang->load('admin_reports');

        $report = $this->c->reports->load($args['id']);

        if ($report instanceof Report) {
            $report->marker = $this->user;

            $this->c->reports->update($report);
            $this->c->reports->clear();
        }

        return $this->c->Redirect->page('AdminReports')->message('Report zapped redirect', FORK_MESS_SUCC);
    }
}
