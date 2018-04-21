<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Validator;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\Config\Model as Config;

class Users extends Admin
{
    /**
     * Генерирует список доступных групп пользователей
     *
     * @param bool $onlyKeys
     *
     * @return array
     */
    protected function groups($onlyKeys = false)
    {
        $groups = [
            -1 => \ForkBB\__('All groups'),
            0  => \ForkBB\__('Unverified users'),
        ];

        foreach ($this->c->groups->getList() as $group) {
            if (! $group->groupGuest) {
                $groups[$group->g_id] = $group->g_title;
            }
        }

        return $onlyKeys ? \array_keys($groups) : $groups;
    }

    /**
     * Подготавливает данные для шаблона поиска пользователей
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function view(array $args, $method)
    {
        $this->c->Lang->load('admin_users');

        $v = null;
        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_message' => [$this, 'vCheckMessage'],
                ])->addRules([
                    'token'                 => 'token:AdminMaintenance',
                    'o_maintenance'         => 'required|integer|in:0,1',
                    'o_maintenance_message' => 'string:trim|max:65000 bytes|check_message',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                $this->c->DB->beginTransaction();

                $this->c->config->o_maintenance         = $v->o_maintenance;
                $this->c->config->o_maintenance_message = $v->o_maintenance_message;
                $this->c->config->save();

                $this->c->DB->commit();

                return $this->c->Redirect->page('AdminMaintenance')->message('Data updated redirect');
            }

            $this->fIswev = $v->getErrors();
        }

        $this->nameTpl    = 'admin/users';
        $this->aIndex     = 'users';
        $this->titles     = \ForkBB\__('Users');
        $this->formSearch = $this->formSearch($v);

        if ($this->user->isAdmin) {
            $this->formIP = $this->formIP($v);
        }

        return $this;
    }

    /**
     * Создает массив данных для формы поиска
     *
     * @param mixed $v
     *
     * @return array
     */
    protected function formSearch($v)
    {
        $form = [
            'action' => $this->c->Router->link('AdminUsers'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminUsers'),
            ],
            'sets'   => [],
            'btns'   => [
                'search' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Submit search'),
                    'accesskey' => 's',
                ],
            ],
        ];
        $form['sets']['search-info'] = [
            'info' => [
                'info1' => [
                    'type'  => '', //????
                    'value' => \ForkBB\__('User search info'),
                ],
            ],
        ];
        $fields = [];
        $fields['username'] = [
            'type'      => 'text',
            'maxlength' => 25,
            'caption'   => \ForkBB\__('Username label'),
            'value'     => isset($v->username) ? $v->username : null,
        ];
        $fields['email'] = [
            'type'      => 'text',
            'maxlength' => 80,
            'caption'   => \ForkBB\__('E-mail address label'),
            'value'     => isset($v->email) ? $v->email : null,
        ];
        $fields['title'] = [
            'type'      => 'text',
            'maxlength' => 50,
            'caption'   => \ForkBB\__('Title label'),
            'value'     => isset($v->title) ? $v->title : null,
        ];
        $fields['realname'] = [
            'type'      => 'text',
            'maxlength' => 40,
            'caption'   => \ForkBB\__('Real name label'),
            'value'     => isset($v->realname) ? $v->realname : null,
        ];
        $genders = [
            0 => \ForkBB\__('Do not display'),
            1 => \ForkBB\__('Male'),
            2 => \ForkBB\__('Female'),
        ];
        $fields['gender'] = [
#            'class'   => 'block',
            'type'    => 'radio',
            'value'   => isset($v->gender) ? $v->gender : -1,
            'values'  => $genders,
            'caption' => \ForkBB\__('Gender label'),
        ];
        $fields['url'] = [
            'id'        => 'website',
            'type'      => 'text',
            'maxlength' => 100,
            'caption'   => \ForkBB\__('Website label'),
            'value'     => isset($v->url) ? $v->url : null,
        ];
        $fields['location'] = [
            'type'      => 'text',
            'maxlength' => 30,
            'caption'   => \ForkBB\__('Location label'),
            'value'     => isset($v->location) ? $v->location : null,
        ];
        $fields['signature'] = [
            'type'      => 'text',
            'maxlength' => 512,
            'caption'   => \ForkBB\__('Signature label'),
            'value'     => isset($v->signature) ? $v->signature : null,
        ];
        $fields['admin_note'] = [
            'type'      => 'text',
            'maxlength' => 30,
            'caption'   => \ForkBB\__('Admin note label'),
            'value'     => isset($v->admin_note) ? $v->admin_note : null,
        ];
        $fields['between1'] = [
            'class' => 'between',
            'type'  => 'wrap',
        ];
        $fields['num_posts_1'] = [
            'type'    => 'number',
            'class'   => 'bstart',
            'min'     => 0,
            'max'     => 9999999999,
            'value'   => isset($v->num_posts_1) ? $v->num_posts_1 : null,
            'caption' => \ForkBB\__('Posts label'),
        ];
        $fields['num_posts_2'] = [
            'type'    => 'number',
            'class'   => 'bend',
            'min'     => 0,
            'max'     => 9999999999,
            'value'   => isset($v->num_posts_2) ? $v->num_posts_2 : null,
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $fields['between2'] = [
            'class' => 'between',
            'type'  => 'wrap',
        ];
        $fields['last_post_1'] = [
            'class'     => 'bstart',
            'type'      => 'text',
            'maxlength' => 100,
            'value'     => isset($v->last_post_1) ? $v->last_post_1 : null,
            'caption'   => \ForkBB\__('Last post label'),
        ];
        $fields['last_post_2'] = [
            'class'     => 'bend',
            'type'      => 'text',
            'maxlength' => 100,
            'value'     => isset($v->last_post_2) ? $v->last_post_2 : null,
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $fields['between3'] = [
            'class' => 'between',
            'type'  => 'wrap',
        ];
        $fields['last_visit_1'] = [
            'class'     => 'bstart',
            'type'      => 'text',
            'maxlength' => 100,
            'value'     => isset($v->last_visit_1) ? $v->last_visit_1 : null,
            'caption'   => \ForkBB\__('Last visit label'),
        ];
        $fields['last_visit_2'] = [
            'class'     => 'bend',
            'type'      => 'text',
            'maxlength' => 100,
            'value'     => isset($v->last_visit_2) ? $v->last_visit_2 : null,
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $fields['between4'] = [
            'class' => 'between',
            'type'  => 'wrap',
        ];
        $fields['registered_1'] = [
            'class'     => 'bstart',
            'type'      => 'text',
            'maxlength' => 100,
            'value'     => isset($v->registered_1) ? $v->registered_1 : null,
            'caption'   => \ForkBB\__('Registered label'),
        ];
        $fields['registered_2'] = [
            'class'     => 'bend',
            'type'      => 'text',
            'maxlength' => 100,
            'value'     => isset($v->registered_2) ? $v->registered_2 : null,
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $form['sets']['filters'] = [
            'legend' => \ForkBB\__('User search subhead'),
            'fields' => $fields,
        ];

        $fields = [];
        $fields['between5'] = [
            'class' => 'between',
            'type'  => 'wrap',
        ];
        $fields['order_by'] = [
            'class'   => 'bstart',
            'type'    => 'select',
            'options' => [
                'username'   => \ForkBB\__('Order by username'),
                'email'      => \ForkBB\__('Order by e-mail'),
                'num_posts'  => \ForkBB\__('Order by posts'),
                'last_post'  => \ForkBB\__('Order by last post'),
                'last_visit' => \ForkBB\__('Order by last visit'),
                'registered' => \ForkBB\__('Order by registered'),
            ],
            'value'   => isset($v->order_by) ? $v->order_by : 'registered',
            'caption' => \ForkBB\__('Order by label'),
        ];
        $fields['direction'] = [
            'class'   => 'bend',
            'type'    => 'select',
            'options' => [
                'ASC'  => \ForkBB\__('Ascending'),
                'DESC' => \ForkBB\__('Descending'),
            ],
            'value'   => isset($v->direction) ? $v->direction : 'DESC',
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $fields['user_group'] = [
            'type'    => 'select',
            'options' => $this->groups(),
            'value'   => isset($v->user_group) ? $v->user_group : -1,
            'caption' => \ForkBB\__('User group label'),
        ];

        $form['sets']['sorting'] = [
            'legend' => \ForkBB\__('Search results legend'),
            'fields' => $fields,
        ];

        return $form;
    }

    /**
     * Создает массив данных для формы поиска по IP
     *
     * @param mixed $v
     *
     * @return array
     */
    protected function formIP($v)
    {
        $form = [
            'action' => $this->c->Router->link('AdminUsers'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminUsers'),
            ],
            'sets'   => [],
            'btns'   => [
                'find' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Find IP address'),
                    'accesskey' => 'f',
                ],
            ],
        ];
        $fields = [];
        $fields['ip'] = [
            'type'      => 'text',
            'maxlength' => 49,
            'caption'   => \ForkBB\__('IP address label'),
            'value'     => isset($v->ip) ? $v->ip : null,
            'required'  => true,
        ];
        $form['sets']['ip'] = [
            'fields' => $fields,
        ];

        return $form;
    }
}
