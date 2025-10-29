<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Models\Extension\Extension;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use Throwable;
use function \ForkBB\__;

class Extensions extends Admin
{
    /**
     * Подготавливает данные для шаблона
     */
    public function info(array $args, string $method): Page
    {
        $this->c->Lang->load('admin_extensions');

        $this->nameTpl    = 'admin/extensions';
        $this->aIndex     = 'extensions';
        $this->extensions = $this->c->extensions->repository;
        $this->actionLink = $this->c->Router->link('AdminExtensionsAction');
        $this->formsToken = $this->c->Csrf->create('AdminExtensionsAction');

        return $this;
    }

    public function action(array $args, string $method): Page
    {
        $this->c->Lang->load('admin_extensions');

        $v = $this->c->Validator->reset()
            ->addRules([
                'token'     => 'token:AdminExtensionsAction',
                'name'      => 'required|string',
                'confirm'   => 'required|string|in:1',
                'install'   => 'string',
                'uninstall' => 'string',
                'update'    => 'string',
                'downdate'  => 'string',
                'enable'    => 'string',
                'disable'   => 'string',
            ])->addAliases([
            ])->addMessages([
                'confirm' => [FORK_MESS_WARN, 'No confirm redirect'],
            ])->addArguments([
            ]);

        if (! $v->validation($_POST)) {
            $message         = $this->c->Message;
            $message->fIswev = $v->getErrors();

            return $message->message('');
        }

        $ext = $this->c->extensions->get($v->name);

        if (! $ext instanceof Extension) {
            return $this->c->Message->message('Extension not found');
        }

        $actions = $v->getData(false, ['token', 'name', 'confirm']);
        $action  = \array_key_first($actions);

        if (empty($action)) {
            return $this->c->Message->message('Invalid action');
        }

        if (true !== $this->c->extensions->{$action}($ext)) {
            return $this->c->Message->message($this->c->extensions->error);
        }

        $this->c->dbMap->reset();

        return $this->c->Redirect->page('AdminExtensions', ['#' => $ext->id])->message("Redirect {$action}", FORK_MESS_SUCC);
    }
}
