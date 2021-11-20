<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin\Users;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin\Users;
use function \ForkBB\__;

class Stat extends Users
{
    /**
     * Подготавливает данные для шаблона ip статистики для пользователя
     */
    public function view(array $args, string $method): Page
    {
        $stat   = $this->c->posts->userStat($args['id']);
        $number = \count($stat);

        $page  = $args['page'] ?? 1;
        $pages = (int) \ceil(($number ?: 1) / $this->c->config->i_disp_users);

        if ($page > $pages) {
            return $this->c->Message->message('Not Found', true, 404);
        }

        $startNum = ($page - 1) * $this->c->config->i_disp_users;
        $stat     = \array_slice($stat, $startNum, $this->c->config->i_disp_users);

        $user = $this->c->users->load($args['id']);

        if (0 == $number) {
            $this->fIswev = ['i', 'Results no posts found'];
        }

        $this->nameTpl    = 'admin/users_result';
        $this->mainSuffix = '-one-column';
        $this->aCrumbs[]  = [
            $this->c->Router->link(
                'AdminUserStat',
                [
                    'id' => $args['id'],
                ]
            ),
            $user->username,
        ];
        $this->formResult = $this->form($stat, $startNum);
        $this->pagination = $this->c->Func->paginate(
            $pages,
            $page,
            'AdminUserStat',
            [
                'id' => $args['id'],
            ]
        );

        return $this;
    }

    /**
     * Создает массив данных для формы статистики пользователя по ip
     */
    protected function form(array $stat, int $number): array
    {
        $form = [
            'action' => null,
            'hidden' => null,
            'sets'   => [],
            'btns'   => null,
        ];

        \array_unshift($stat, ['last_used' => null, 'used_times' => null]);
        $flag = false;

        foreach ($stat as $ip => $data) {
            $fields = [];

            $fields["l{$number}-ip"] = [
                'class'   => ['result', 'ip'],
                'type'    => $flag ? 'link' : 'str',
                'caption' => 'Results IP address head',
                'value'   => $flag ? $ip : null,
                'href'    => $flag
                    ? $this->c->Router->link(
                        'AdminHost',
                        [
                            'ip' => $ip,
                        ]
                    )
                    : null,
            ];
            $fields["l{$number}-last-used"] = [
                'class'   => ['result', 'last-used'],
                'type'    => 'str',
                'caption' => 'Results last used head',
                'value'   => $flag ? \ForkBB\dt($data['last_used']) : null,
            ];
            $fields["l{$number}-used-times"] = [
                'class'   => ['result', 'used-times'],
                'type'    => 'str',
                'caption' => 'Results times found head',
                'value'   => $flag ? \ForkBB\num($data['used_times']) : null,
            ];
            $fields["l{$number}-action"] = [
                'class'   => ['result', 'action'],
                'type'    => $flag ? 'link' : 'str',
                'caption' => 'Results action head',
                'value'   => $flag ? __('Results find more link') : null,
                'href'    => $flag
                    ? $this->c->Router->link(
                        'AdminUsersResult',
                        [
                            'data' => $this->encodeData($ip),
                        ]
                    )
                    : null,
            ];

            $form['sets']["l{$number}"] = [
                'class'  => ['result', 'stat'],
                'legend' => $flag ? (string) $number : null,
                'fields' => $fields,
            ];

            ++$number;
            $flag = true;
        }

        return $form;
    }
}
