<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use Throwable;
use function \ForkBB\{__, e};

class Logs extends Admin
{
    /**
     * Подготавливает данные для шаблона
     */
    public function info(): Page
    {
        $this->c->Lang->load('admin_logs');

        $logsFiles = $this->c->LogViewer->files();
        $info      = $this->c->LogViewer->info($logsFiles);
        $i         = 0;

        foreach ($info as $hash => &$cur) {
            ++$i;

            $cur['linkView']     = $this->c->Router->link(
                'AdminLogsAction',
                [
                    'action' => 'view',
                    'hash'   => $hash,
                ]
            );
            $cur['linkDownload'] = $this->c->Router->link(
                'AdminLogsAction',
                [
                    'action' => 'download',
                    'hash'   => $hash,
                ]
            );

            if ($i < 15) {
                continue;
            }

            $cur['linkDelete']   = $this->c->Router->link(
                'AdminLogsAction',
                [
                    'action' => 'delete',
                    'hash'   => $hash,
                ]
            );
        }

        unset($cur);

        $this->nameTpl  = 'admin/logs';
        $this->aIndex   = 'logs';
        $this->logsInfo = $info;

        return $this;
    }

    public function action(array $args, string $method): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'AdminLogsAction', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
        }

        $path = $this->c->LogViewer->getPath($args['hash']);

        if (
            ! \is_string($path)
            || ! \is_file($path)
        ) {
            return $this->c->Message->message('Not Found', true, 404);
        }

        $this->c->Lang->load('admin_logs');

        $this->aIndex = 'logs';

        switch ($args['action']) {
            case 'view':
                return $this->view($path, $args, $method);
            case 'delete':
                return $this->delete($path, $args, $method);
            case 'download':
                return $this->download($path, $args, $method);
            default:
                return $this->c->Message->message('Not Found', true, 404);
        }
    }

    protected function download(string $path, array $args, string $method): Page
    {
        $this->c->DEBUG = 0;
        $this->nameTpl  = 'layouts/plain_raw';
        $this->plainRaw = \trim(\file_get_contents($path)); // ???? возможности XSendFile/Nginx использовать?

        $this->header('Content-type', 'application/octet-stream')
            ->header('Content-Transfer-Encoding', 'binary')
            ->header('Content-Disposition', 'attachment; filename=' . $this->c->LogViewer->getName($path));

        return $this;
    }

    protected function delete(string $path, array $args, string $method): Page
    {
        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'delete' => 'required|string',
                ])->addAliases([
                ])->addArguments([
                ]);

            try {
                if (
                    $v->validation($_POST)
                    && \unlink($path)
                ) {
                    return $this->c->Redirect->page('AdminLogs')->message('Log deleted redirect', FORK_MESS_SUCC);
                }
            } catch (Throwable $e) {
                $this->c->Log->error('Delete log: failed', [
                    'exception' => $e,
                    'headers'   => false,
                ]);
            }

            return $this->c->Redirect->page('AdminLogs')->message('Failed to delete log redirect', FORK_MESS_ERR);
        }

        $this->nameTpl   = 'admin/form';
        $this->titleForm = 'Delete log head';
        $this->classForm = ['logdel'];
        $this->form      = $this->formDelete($path, $args);
        $this->aCrumbs[] = [$this->c->Router->link('AdminLogsAction', $args), 'Delete log head'];

        return $this;
    }

    protected function formDelete(string $path, array $args): array
    {
        $form  = [
            'action' => $this->c->Router->link('AdminLogsAction', $args),
            'hidden' => [], // токен в url передается для всех действий с логами
            'sets'   => [],
            'btns'   => [
                'delete'  => [
                    'type'  => 'submit',
                    'value' => __(['Delete log %s', $this->c->LogViewer->getName($path)]),
                ],
                'cancel'  => [
                    'type'  => 'btn',
                    'value' => __('Cancel'),
                    'href'  => $this->c->Router->link('AdminLogs'),
                ],
            ],
        ];

        return $form;
    }

    protected function view(string $path, array $args, string $method): Page
    {
        $data = $this->c->LogViewer->parse($path);

        foreach ($data as &$cur) {
            $cur['context'] = \preg_replace('%^\s*Array\s*\(\n(.+)\n\)\s*$%s', '$1', \print_r($cur['context'], true));
        }

        unset($cur);

        $this->nameTpl   = 'admin/logs';
        $this->logData   = $data;
        $this->logName   = $this->c->LogViewer->getName($path);
        $this->aCrumbs[] = [$this->c->Router->link('AdminLogsAction', $args), ['Log %s', $this->logName]];

        return $this;
    }

    /**
     * Экранирует контент и формирует на ip из REMOTE_ADDR ссылку
     */
    public function parseIP(string $message): string
    {
        return \preg_replace_callback('%REMOTE_ADDR.+?\K[\da-f][\da-f.:]+%', function ($matches) {
            $ip = \filter_var($matches[0], \FILTER_VALIDATE_IP);

            if (false === $ip) {
                return $matches[0];
            } else {
                $url = e($this->c->Router->link('AdminHost', ['ip' => $ip,]));

                return "<a href=\"{$url}\">{$ip}</a>";
            }
        }, e($message));
    }
}
