<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use function \ForkBB\__;

class Drafts extends Page
{
    /**
     * Просмотр черновиков
     */
    public function view(array $args, string $method): Page
    {
        $this->c->Lang->load('draft');

        if ($this->user->num_drafts < 1) {
            return $this->c->Message->message('No drafts');
        }

        $this->numPage = $args['page'] ?? 1;
        $this->drafts  = $this->c->drafts->view($this->numPage);

        if (empty($this->drafts)) {
            $count = $this->c->drafts->count();

            if ($count !== $this->user->num_drafts) {
                $this->user->num_drafts = $count;

                $this->c->users->update($this->user);
            }

            return $this->c->Message->message($count > 0 ? 'Page missing' : 'No drafts', false, 404);
        }

        $this->numPages   = $this->c->drafts->numPages();
        $this->pagination = $this->c->Func->paginate($this->numPages, $this->numPage, 'Drafts');
        $this->useMediaJS = true;
        $this->nameTpl    = 'drafts';
        $this->identifier = 'search-result';
        $this->fIndex     = self::FI_DRAFT;
        $this->onlinePos  = 'drafts';
        $this->robots     = 'noindex';
        $this->crumbs     = $this->crumbs(
            [
                $this->c->Router->link('Drafts'),
                'Drafts',
            ]
        );

        $this->c->Parser; // предзагрузка

        $this->c->Lang->load('search');

        return $this;
    }
}
