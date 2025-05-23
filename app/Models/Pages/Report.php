<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\Post\Post;
use ForkBB\Models\Report\Report as ReportModel;
use function \ForkBB\__;

class Report extends Page
{
    /**
     * Создание нового сигнала (репорта)
     */
    public function report(array $args, string $method): Page
    {
        $post = $this->c->posts->load($args['id']);

        if (! $post instanceof Post) {
            return $this->c->Message->message('Bad request');
        }

        $topic = $post->parent;

        $this->c->Lang->load('validator');
        $this->c->Lang->load('misc');

        $floodSize = \time() - (int) $this->user->last_report_sent;
        $floodSize = $floodSize < $this->user->g_report_flood ? $this->user->g_report_flood - $floodSize : 0;
        if ($floodSize > 0) {
            $this->fIswev = [FORK_MESS_ERR, ['Flood message', $floodSize]];
        }

        $data = [];

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
            ->addValidators([
            ])->addRules([
                'token'       => 'token:ReportPost',
                'reason'      => 'required|string:trim,linebreaks|max:65000 bytes',
                'report'      => 'required|string',
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

                $report->author  = $this->user;
                $report->post    = $post;
                $report->message = $v->reason;

                $result = true;

                switch ($this->c->config->i_report_method) {
                    case 2:
                        $this->c->reports->insert($report);
                    case 1:
                        try {
                            $result = $this->sendReport($report);
                        } catch (MailException $e) {
                            $result = false;

                            $this->c->Log->error('Report: MailException', [
                                'user'      => $this->user->fLog(),
                                'exception' => $e,
                                'headers'   => false,
                            ]);
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

                if (false === $result && 1 === $this->c->config->i_report_method) {
                    $this->fIswev = [FORK_MESS_ERR, ['Error mail', $this->c->config->o_admin_email]];

                } else {
                    return $this->c->Redirect->page('ViewPost', ['id' => $post->id])->message('Report redirect', FORK_MESS_SUCC);
                }
            }

            $this->fIswev = $v->getErrors();
            $data         = $v->getData();
        }

        $this->identifier = 'report';
        $this->nameTpl    = 'report';
//        $this->onlinePos = 'forum-' . $forum->id;
//        $this->canonical = $this->c->Router->link('NewTopic', ['id' => $forum->id]);
        $this->robots     = 'noindex';
        $this->crumbs     = $this->crumbs('Report post', $topic);
        $this->form       = $this->formReport($args, $data);

        return $this;
    }

    /**
     * Создает массив для формирование формы
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
                    'legend' => 'Reason desc',
                    'fields' => [
                        'reason' => [
                            'type'      => 'textarea',
                            'caption'   => 'Reason',
                            'required'  => true,
                            'value'     => $data['reason'] ?? null,
                            'autofocus' => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'report' => [
                    'type'  => 'submit',
                    'value' => __('Submit'),
                ],
                'back' => [
                    'type'  => 'btn',
                    'value' => __('Go back'),
                    'href'  => $this->c->Router->link('ViewPost', ['id' => $args['id']]),
                    'class' => ['f-opacity', 'f-go-back'],
                ],
            ],
        ];
    }

    /**
     * Рассылает email с сигналом (репортом)
     */
    protected function sendReport(ReportModel $report): bool
    {
        $tplData = [
            'fMailer'      => __(['Mailer', $this->c->config->o_board_title]),
            'username'     => $report->author->username,
            'postLink'     => $this->c->Router->link(
                'ViewPost',
                [
                    'id' => $report->post->id,
                ]
            ),
            'reason'       => $report->message,
            'forumId'      => $report->post->parent->parent->id,
            'topicSubject' => $report->post->parent->name,
        ];

        return $this->c->Mail
            ->reset()
            ->setMaxRecipients((int) $this->c->config->i_email_max_recipients)
            ->setFolder($this->c->DIR_LANG)
            ->setLanguage($this->c->config->o_default_lang) // ????
            ->setTo($this->c->config->o_mailing_list)
            ->setFrom($this->c->config->o_webmaster_email, $tplData['fMailer'])
            ->setTpl('new_report.tpl', $tplData)
            ->send(7);
    }
}
