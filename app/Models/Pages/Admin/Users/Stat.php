<?php

namespace ForkBB\Models\Pages\Admin\Users;

use ForkBB\Core\Validator;
use ForkBB\Models\Pages\Admin\Users;

class Stat extends Users
{
    /**
     * Подготавливает данные для шаблона ip статистики для пользователя
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function view(array $args, $method)
    {
        $stat   = $this->c->posts->userStat($args['id']);
        $number = \count($stat);

        $page  = isset($args['page']) ? (int) $args['page'] : 1;
        $pages = (int) \ceil(($number ?: 1) / $this->c->config->o_disp_users);

        if ($page > $pages) {
            return $this->c->Message->message('Bad request');
        }

        $startNum = ($page - 1) * $this->c->config->o_disp_users;
        $stat     = \array_slice($stat, $startNum, $this->c->config->o_disp_users);

        $user = $this->c->users->load((int) $args['id']);

        if (0 == $number) {
            $this->fIswev = ['i', \ForkBB\__('Results no posts found')];
        }

        $this->nameTpl    = 'admin/users_result';
        $this->mainSuffix = '-one-column';
        $this->aCrumbs[]  = [$this->c->Router->link('AdminUserStat', ['id' => $args['id']]), $user->username];
        $this->formResult = $this->form($stat, $startNum);
        $this->pagination = $this->c->Func->paginate($pages, $page, 'AdminUserStat', ['id' => $args['id']]);

        return $this;
    }

    /**
     * Создает массив данных для формы статистики пользователя по ip
     *
     * @param array $stat
     * @param int $number
     *
     * @return array
     */
    protected function form(array $stat, $number)
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
                'caption' => \ForkBB\__('Results IP address head'),
                'value'   => $flag ? $ip : null,
                'href'    => $flag ? $this->c->Router->link('AdminHost', ['ip' => $ip]) : null,
            ];
            $fields["l{$number}-last-used"] = [
                'class'   => ['result', 'last-used'],
                'type'    => 'str',
                'caption' => \ForkBB\__('Results last used head'),
                'value'   => $flag ? \ForkBB\dt($data['last_used']) : null,
            ];
            $fields["l{$number}-used-times"] = [
                'class'   => ['result', 'used-times'],
                'type'    => 'str',
                'caption' => \ForkBB\__('Results times found head'),
                'value'   => $flag ? \ForkBB\num($data['used_times']) : null,
            ];
            $fields["l{$number}-action"] = [
                'class'   => ['result', 'action'],
                'type'    => $flag ? 'link' : 'str',
                'caption' => \ForkBB\__('Results action head'),
                'value'   => $flag ? \ForkBB\__('Results find more link') : null,
                'href'    => $flag ? $this->c->Router->link('AdminUsersResult', ['data' => $this->encodeData($ip)]) : null,
            ];

            $form['sets']["l{$number}"] = [
                'class'  => ['result', 'stat'],
                'legend' => $flag ? $number : null,
                'fields' => $fields,
            ];

            ++$number;
            $flag = true;
        }

        return $form;
    }
}
