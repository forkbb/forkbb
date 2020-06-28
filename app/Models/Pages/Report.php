<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\Post\Model as Post;
use ForkBB\Models\Report\Model as ReportModel;

class Report extends Page
{
    /**
     * Создание нового сигнала (репорта)
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function report(array $args, string $method): Page
    {
        $post = $this->c->posts->load((int) $args['id']);

        if (! $post instanceof Post) {
            return $this->c->Message->message('Bad request');
        }

        $topic = $post->parent;

        $this->c->Lang->load('misc');

        $floodSize = \time() - (int) $this->user->last_report_sent;
        $floodSize = $floodSize < $this->user->g_report_flood ? $this->user->g_report_flood - $floodSize : 0;
        if ($floodSize > 0) {
            $this->fIswev = ['e', \ForkBB\__('Report flood', $this->user->g_report_flood, $floodSize)];
        }

        $data = [];

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
            ->addValidators([
            ])->addRules([
                'token'       => 'token:ReportPost',
                'reason'      => 'required|string:trim,linebreaks|max:65000 bytes',
            ])->addAliases([
                'reason'      => 'Reason',
            ])->addArguments([
                'token'       => $args,
            ])->addMessages([
            ]);

            if (
                $v->validation($_POST)
                && 0 === $floodSize
            ) {
                $report = $this->c->reports->create();

                $report->author = $this->user;
                $report->post = $post;
                $report->message = $v->reason;

                $result = true;

                switch ($this->c->config->o_report_method) {
                    case '2':
                        $this->c->reports->insert($report);
                    case '1':
                        try {
                            $result = $this->sendReport($report);
                        } catch (MailException $e) {
                            $result = false;
                        }
                        break;
                    default:
                        $this->c->reports->insert($report);
                        break;
                }

                if ($this->user->g_report_flood > 0) {
                    $this->user->last_report_sent = \time();
                    $this->c->users->update($this->user);
                }

                return $this->c->Redirect->page('ViewPost', ['id' => $post->id])->message('Report redirect');
            }

            $this->fIswev = $v->getErrors();
            $data         = $v->getData();
        }

        $this->nameTpl   = 'report';
//        $this->onlinePos = 'forum-' . $forum->id;
//        $this->canonical = $this->c->Router->link('NewTopic', ['id' => $forum->id]);
        $this->robots    = 'noindex';
        $this->crumbs    = $this->crumbs(\ForkBB\__('Report post'), $topic);
        $this->formTitle = \ForkBB\__('Report post');
        $this->form      = $this->formReport($args, $data);

        return $this;
    }

    /**
     * Создает массив для формирование формы
     *
     * @param array $args
     * @param array $data
     *
     * @return array
     */
    protected function formReport(array $args, array $data): array
    {
        return [
            'action' => $this->c->Router->link('ReportPost', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('ReportPost', $args),
            ],
            'sets'   => [
                'report' => [
                    'legend' => \ForkBB\__('Reason desc'),
                    'fields' => [
                        'reason' => [
                            'type'      => 'textarea',
                            'caption'   => \ForkBB\__('Reason'),
                            'required'  => true,
                            'value'     => $data['reason'] ?? null,
                            'autofocus' => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Submit'),
                    'accesskey' => 's',
                ],
                'back' => [
                    'type'      => 'btn',
                    'value'     => \ForkBB\__('Go back'),
                    'link'      => 'javascript:history.go(-1)',
                    'class'     => 'f-minor',
                ],
            ],
        ];
    }

    /**
     * Рассылает email с сигналом (репортом)
     *
     * @param ReportModel $report
     *
     * @return bool
     */
    protected function sendReport(ReportModel $report): bool
    {
        $tplData = [
            'fMailer' => \ForkBB\__('Mailer', $this->c->config->o_board_title),
            'username' => $report->author->username,
            'postLink' => $this->c->Router->link('ViewPost', ['id' => $report->post->id]),
            'reason' => $report->message,
            'forumId' => $report->post->parent->parent->id,
            'topicSubject' => $report->post->parent->subject,
        ];

        return $this->c->Mail
            ->reset()
            ->setFolder($this->c->DIR_LANG)
            ->setLanguage($this->c->config->o_default_lang) // ????
            ->setTo($this->c->config->o_mailing_list)
            ->setFrom($this->c->config->o_webmaster_email, \ForkBB\__('Mailer', $this->c->config->o_board_title))
            ->setTpl('new_report.tpl', $tplData)
            ->send();
    }
}
