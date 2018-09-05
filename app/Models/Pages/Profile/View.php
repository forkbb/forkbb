<?php

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Models\Pages\Profile;

class View extends Profile
{
    /**
     * Подготавливает данные для шаблона просмотра профиля
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function view(array $args, $method)
    {
        if (false === $this->initProfile($args['id'])) {
            return $this->c->Message->message('Bad request');
        }

        $this->canonical  = $this->curUser->link;
        $this->robots     = null;
        $this->crumbs     = $this->crumbs();
        $this->form       = $this->form();
        $this->actionBtns = $this->btns('view');

        return $this;
    }

    /**
     * Создает массив данных для формы
     *
     * @return array
     */
    protected function form()
    {
        $form = [
            'sets' => []
        ];

        // имя, титул и аватара
        $fields = [];
        $fields['usertitle'] = [
            'class' => 'usertitle',
            'type'  => 'wrap',
        ];
        $fields['username'] = [
            'class'   => 'pline',
            'type'    => 'str',
            'caption' => \ForkBB\__('Username'),
            'value'   => $this->curUser->username,
        ];
        $fields['title'] = [
            'class'   => 'pline',
            'type'    => 'str',
            'caption' => \ForkBB\__('Title'),
            'value'   => $this->curUser->title(),
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        if ($this->rules->useAvatar && $this->curUser->avatar) {
            $fields['avatar'] = [
                'type'    => 'yield',
                'caption' => \ForkBB\__('Avatar'),
                'value'   => 'avatar',
            ];
        }
        $form['sets']['header'] = [
            'class'  => 'header',
#            'legend' => \ForkBB\__('Options'),
            'fields' => $fields,
        ];

        // примечание администрации
        if ($this->user->isAdmMod && '' != $this->curUser->admin_note) {
            $form['sets']['note'] = [
                'class'  => 'data',
                'legend' => \ForkBB\__('Admin note'),
                'fields' => [
                    'admin_note' => [
                        'class'     => 'pline',
                        'type'      => 'str',
                        'caption'   => \ForkBB\__('Admin note'),
                        'value'     => $this->curUser->admin_note,
                    ],
                ],
            ];
        }

        // личное
        $fields = [];
        if ('' != $this->curUser->realname) {
            $fields['realname'] = [
                'class'   => 'pline',
                'type'    => 'str',
                'caption' => \ForkBB\__('Realname'),
                'value'   => \ForkBB\cens($this->curUser->realname),
            ];
        }
        $genders = [
            0 => \ForkBB\__('Do not display'),
            1 => \ForkBB\__('Male'),
            2 => \ForkBB\__('Female'),
        ];
        if ($this->curUser->gender && isset($genders[$this->curUser->gender])) {
            $fields['gender'] = [
                'class'   => 'pline',
                'type'    => 'str',
                'value'   => $genders[$this->curUser->gender],
                'caption' => \ForkBB\__('Gender'),
            ];
        }
        if ('' != $this->curUser->location) {
            $fields['location'] = [
                'class'   => 'pline',
                'type'    => 'str',
                'caption' => \ForkBB\__('Location'),
                'value'   => \ForkBB\cens($this->curUser->location),
            ];
        }
        if (! empty($fields)) {
            $form['sets']['personal'] = [
                'class'  => 'data',
                'legend' => \ForkBB\__('Personal information'),
                'fields' => $fields,
            ];
        }

        // контактная информация
        $fields = [];
        if ($this->rules->viewOEmail) {
            $fields['open-email'] = [
                'class'   => 'pline',
                'type'    => 2 === $this->curUser->email_setting ? 'str' : 'link',
                'caption' => \ForkBB\__('Email info'),
                'value'   => \ForkBB\cens($this->curUser->email),
                'href'    => 'mailto:' . $this->curUser->email,
            ];
        }
        if ($this->rules->viewEmail) {
            if (0 === $this->curUser->email_setting) {
                $fields['email'] = [
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Email info'),
                    'value'   => \ForkBB\cens($this->curUser->email),
                    'href'    => 'mailto:' . $this->curUser->email,
                ];
            } elseif (1 === $this->curUser->email_setting) {
                $fields['email'] = [
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Email info'),
                    'value'   => \ForkBB\__('Send email'),
                    'href'    => $this->c->Router->link('', ['id' => $this->curUser->id]), // ????
                ];
            }
        }
        if ($this->rules->viewWebsite && $this->curUser->url) {
            $fields['url'] = [
                'id'      => 'website',
                'class'   => 'pline',
                'type'    => 'link',
                'caption' => \ForkBB\__('Website'),
                'value'   => \ForkBB\cens($this->curUser->url),
                'href'    => \ForkBB\cens($this->curUser->url),
            ];
        }
        if (! empty($fields)) {
            $form['sets']['contacts'] = [
                'class'  => 'data',
                'legend' => \ForkBB\__('Contact details'),
                'fields' => $fields,
            ];
        }

        // подпись
        if ($this->rules->useSignature) {
            $fields = [];
            if ('' != $this->curUser->signature) {
                $fields['signature'] = [
                    'type'    => 'yield',
                    'caption' => \ForkBB\__('Signature'),
                    'value'   => 'signature',
                ];
            }
            if (! empty($fields)) {
                $form['sets']['signature'] = [
                    'class'  => 'data',
                    'legend' => \ForkBB\__('Signature'),
                    'fields' => $fields,
                ];
            }
        }

        // активность
        $fields = [];
        $fields['registered'] = [
            'class'   => 'pline',
            'type'    => 'str',
            'value'   => \ForkBB\dt($this->curUser->registered, true),
            'caption' => \ForkBB\__('Registered info'),
        ];
        if ($this->rules->viewLastVisit) {
            $fields['lastvisit'] = [
                'class'   => 'pline',
                'type'    => 'str',
                'value'   => \ForkBB\dt($this->curUser->last_visit, true),
                'caption' => \ForkBB\__('Last visit info'),
            ];
        }
        $fields['lastpost'] = [
            'class'   => 'pline',
            'type'    => 'str',
            'value'   => \ForkBB\dt($this->curUser->last_post, true),
            'caption' => \ForkBB\__('Last post info'),
        ];
        if ($this->curUser->num_posts) {
            if ('1' == $this->user->g_search) {
                $fields['posts'] = [
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Posts info'),
                    'value'   => $this->user->showPostCount ? \ForkBB\num($this->curUser->num_posts) : \ForkBB\__('Show posts'),
                    'href'    => $this->c->Router->link('SearchAction', ['action' => 'posts', 'uid' => $this->curUser->id]),
                    'title'   => \ForkBB\__('Show posts'),
                ];
                $fields['topics'] = [
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Topics info'),
                    'value'   => $this->user->showPostCount ? \ForkBB\num($this->curUser->num_topics) : \ForkBB\__('Show topics'),
                    'href'    => $this->c->Router->link('SearchAction', ['action' => 'topics', 'uid' => $this->curUser->id]),
                    'title'   => \ForkBB\__('Show topics'),
                ];
            } elseif ($this->user->showPostCount) {
                $fields['posts'] = [
                    'class'   => 'pline',
                    'type'    => 'str',
                    'caption' => \ForkBB\__('Posts info'),
                    'value'   => \ForkBB\num($this->curUser->num_posts),
                ];
                $fields['topics'] = [
                    'class'   => 'pline',
                    'type'    => 'str',
                    'caption' => \ForkBB\__('Topics info'),
                    'value'   => \ForkBB\num($this->curUser->num_topics),
                ];
            }
        }
        if ($this->rules->viewIP && false !== \filter_var($this->curUser->registration_ip, \FILTER_VALIDATE_IP)) {
            $fields['ip'] = [
                'class'   => 'pline',
                'type'    => 'link',
                'caption' => \ForkBB\__('IP'),
                'value'   => $this->curUser->registration_ip,
                'href'    => $this->c->Router->link('AdminHost', ['ip' => $this->curUser->registration_ip]),
                'title'   => \ForkBB\__('IP title'),
            ];
        }
        $form['sets']['activity'] = [
            'class'  => 'data',
            'legend' => \ForkBB\__('User activity'),
            'fields' => $fields,
        ];

        return $form;
    }
}
