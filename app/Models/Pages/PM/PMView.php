<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\PM;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\PM\AbstractPM;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\User\Model as User;
use InvalidArgumentException;
use function \ForkBB\__;

class PMView extends AbstractPM
{
    /**
     * Списки новых, текущих и архивных приватных топиков
     */
    public function view(array $args, string $method): Page
    {
        $this->args      = $args;
        $this->pms->page = $args['more1'] ?? 1;

        if (
            isset($args['more2'])
            || ! $this->pms->hasPage()
        ) {
            return $this->c->Message->message('Not Found', true, 404);
        }

        $this->pmIndex    = $this->pms->area;
        $this->nameTpl    = 'pm/view';
        $this->pmList     = $this->pms->pmListCurPage();
        $this->pagination = $this->pms->pagination;

        return $this;
    }
}
