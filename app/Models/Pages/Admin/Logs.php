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
use Throwable;
use function \ForkBB\__;

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

        foreach ($info as $hash => &$cur) {
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
            null === $path
            || ! \is_file($path)
        ) {
            return $this->c->Message->message('Not Found', true, 404);
        }

        $this->c->Lang->load('admin_logs');

        $this->aIndex   = 'logs';

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
                    'delete' => 'string',
                ])->addAliases([
                ])->addArguments([
                ]);

            try {
                if (
                    $v->validation($_POST)
                    && \unlink($path)
                ) {
                    return $this->c->Redirect->page('AdminLogs')->message('Log deleted redirect');
                }
            } catch (Throwable $e) { // ???? будет работать или нет?
                $this->c->Log->error('Delete log: failed', [
                    'exception' => $e,
                    'headers'   => false,
                ]);
            }

            return $this->c->Redirect->page('AdminLogs')->message('Failed to delete log redirect');
        }

        $this->nameTpl    = 'admin/form';
        $this->titleForm  = 'Delete log head';
        $this->classForm  = 'logdel';
        $this->form       = $this->formDelete($path, $args);
        $this->aCrumbs[]  = [
            $this->c->Router->link('AdminLogsAction', $args),
            __('Delete log head'),
        ];

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
                    'value' => __('Delete log %s', $this->c->LogViewer->getName($path)),
                ],
                'cancel'  => [
                    'type'  => 'btn',
                    'value' => __('Cancel'),
                    'link'  => $this->c->Router->link('AdminLogs'),
                ],
            ],
        ];

        return $form;
    }
}
