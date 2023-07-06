<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Online;

use ForkBB\Models\Method;
use ForkBB\Models\Online\Online;

class Info extends Method
{
    /**
     * Получение информации об онлайн посетителях
     */
    public function info(): ?Online
    {
        if (! $this->model->detail) {
            return null;
        }

        $viewUsers            = $this->c->userRules->viewUsers;
        $this->model->maxNum  = $this->c->config->a_max_users['number'];
        $this->model->maxTime = $this->c->config->a_max_users['time'];
        $info                 = [];

        foreach ($this->model->users as $id => $name) {
            $info[] = [
                'name' => $name,
                'link' => $viewUsers
                    ? $this->c->Router->link(
                        'User',
                        [
                            'id'   => $id,
                            'name' => $name,
                        ]
                    )
                    : null,
            ];
        }

        $this->model->numUsers = \count($info);
        $s                     = 0;

        foreach ($this->model->bots as $bot => $arr) {
            $count  = \count($arr);
            $s     += $count;
            $info[] = [
                'name' => "[Bot] {$bot}" . ($count > 1 ? " ({$count})" : ''),
                'link' => null,
            ];
        }

        $this->model->numGuests = $s + \count($this->model->guests);
        $this->model->info      = $info;

        return $this->model;
    }
}
