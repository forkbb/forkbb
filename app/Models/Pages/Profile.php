<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Models\User\Model as User;

class Profile extends Page
{
    use CrumbTrait;

    /**
     * Подготавливает данные для шаблона редактирования профиля
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function edit(array $args, $method)
    {
        return $this->view($args, $method, true);
    }

    /**
     * Подготавливает данные для шаблона просмотра профиля
     *
     * @param array $args
     * @param string $method
     * @param bool $isEdit
     *
     * @return Page
     */
    public function view(array $args, $method, $isEdit = false)
    {
        $curUser = $this->c->users->load((int) $args['id']);

        if (! $curUser instanceof User || ($curUser->isUnverified && ! $this->user->isAdmMod)) {
            return $this->c->Message->message('Bad request');
        }

        $rules = $this->c->ProfileRules->setUser($curUser);

        if ($isEdit) {
            if (! $rules->editProfile) {
                return $this->c->Message->message('Bad request');
            }

            $this->c->Lang->load('profile_other');
        }

        $this->c->Lang->load('profile');

        if ($isEdit && 'POST' === $method) {

        }

        $clSuffix = $isEdit ? '-edit' : '';

        if ($isEdit) {
            $form = [
                'action' => $this->c->Router->link('EditUserProfile',  ['id' => $curUser->id]),
                'hidden' => [
                    'token' => $this->c->Csrf->create('EditUserProfile',  ['id' => $curUser->id]),
                ],
                'sets'   => [],
                'btns'   => [
                    'save' => [
                        'type'      => 'submit',
                        'value'     => \ForkBB\__('Save changes'),
                        'accesskey' => 's',
                    ],
                ],
            ];
        } else {
            $form = ['sets' => []];
        }

        // имя, титул и аватара
        $fieldset = [];
        $fieldset[] = [
            'class' => 'usertitle',
            'type'  => 'wrap',
        ];
        if ($isEdit && $rules->rename) {
            $fieldset['username'] = [
                'id'        => 'username',
                'type'      => 'text',
                'maxlength' => 25,
                'caption'   => \ForkBB\__('Username'),
                'required'  => true,
                'pattern'   => '^.{2,25}$',
                'value'     => $curUser->username,
            ];
        } else {
            $fieldset['username'] = [
                'id'      => 'username',
                'class'   => 'pline',
                'type'    => 'str',
                'caption' => \ForkBB\__('Username'),
                'value'   => $curUser->username,
            ];
        }
        if ($isEdit && $rules->setTitle) {
            $fieldset['title'] = [
                'id'        => 'title',
                'type'      => 'text',
                'maxlength' => 50,
                'caption'   => \ForkBB\__('Title'),
                'value'     => $curUser->title,
                'info'      => \ForkBB\__('Leave blank'),
            ];
        } else {
            $fieldset['title'] = [
                'id'      => 'title',
                'class'   => 'pline',
                'type'    => 'str',
                'caption' => \ForkBB\__('Title'),
                'value'   => $curUser->title(),
            ];
        }
        $fieldset[] = [
            'type' => 'endwrap',
        ];
        if ('1' == $this->c->config->o_avatars) {
            if ($isEdit && ! $curUser->avatar) { //// может стоит поле для загрузки вставить????
                $fieldset['avatar'] = [
                    'id'      => 'avatar',
                    'class'   => 'pline',
                    'type'    => 'str',
                    'caption' => \ForkBB\__('Avatar'),
                    'value'   => \ForkBB\__('Not uploaded'),
                ];
            } elseif ($curUser->avatar) {
                $fieldset['avatar'] = [
                    'id'      => 'avatar',
                    'type'    => 'yield',
                    'caption' => \ForkBB\__('Avatar'),
                    'value'   => 'avatar',
                ];
            }
        }
        $form['sets'][] = [
            'id'     => 'header',
            'class'  => 'header' . $clSuffix,
#            'legend' => \ForkBB\__('Options'),
            'fields' => $fieldset,
        ];

        // примечание администрации
        if ($this->user->isAdmMod) {
            $fieldset = [];
            if ($isEdit) {
                $fieldset['admin_note'] = [
                    'id'        => 'admin_note',
                    'type'      => 'text',
                    'maxlength' => 30,
                    'caption'   => \ForkBB\__('Admin note'),
                    'value'     => $curUser->admin_note,
                ];
            } elseif ('' != $curUser->admin_note) {
                $fieldset['admin_note'] = [
                    'id'        => 'admin_note',
                    'class'   => 'pline',
                    'type'      => 'str',
                    'caption'   => \ForkBB\__('Admin note'),
                    'value'     => $curUser->admin_note,
                ];
            }
            if (! empty($fieldset)) {
                $form['sets'][] = [
                    'id'     => 'note',
                    'class'  => 'data' . $clSuffix,
                    'legend' => \ForkBB\__('Admin note'),
                    'fields' => $fieldset,
                ];
            }
        }

        // личное
        $fieldset = [];
        if ($isEdit) {
            $fieldset['realname'] = [
                'id'        => 'realname',
                'type'      => 'text',
                'maxlength' => 40,
                'caption'   => \ForkBB\__('Realname'),
                'value'     => $curUser->realname,
            ];
        } elseif ('' != $curUser->realname) {
            $fieldset['realname'] = [
                'id'      => 'realname',
                'class'   => 'pline',
                'type'    => 'str',
                'caption' => \ForkBB\__('Realname'),
                'value'   => \ForkBB\cens($curUser->realname),
            ];
        }
        $genders = [
            0 => \ForkBB\__('Unknown'),
            1 => \ForkBB\__('Male'),
            2 => \ForkBB\__('Female'),
        ];
        if ($isEdit) {
            $fieldset['gender'] = [
                'id'      => 'gender',
                'class'   => 'block',
                'type'    => 'radio',
                'value'   => $curUser->gender,
                'values'  => $genders,
                'caption' => \ForkBB\__('Gender'),
            ];
        } elseif ($curUser->gender && isset($genders[$curUser->gender])) {
            $fieldset['gender'] = [
                'id'      => 'gender',
                'class'   => 'pline',
                'type'    => 'str',
                'value'   => $genders[$curUser->gender],
                'caption' => \ForkBB\__('Gender'),
            ];
        }
        if ($isEdit) {
            $fieldset['location'] = [
                'id'        => 'location',
                'type'      => 'text',
                'maxlength' => 40,
                'caption'   => \ForkBB\__('Location'),
                'value'     => $curUser->location,
            ];
        } elseif ('' != $curUser->location) {
            $fieldset['location'] = [
                'id'      => 'location',
                'class'   => 'pline',
                'type'    => 'str',
                'caption' => \ForkBB\__('Location'),
                'value'   => \ForkBB\cens($curUser->location),
            ];
        }
        if (! empty($fieldset)) {
            $form['sets'][] = [
                'id'     => 'personal',
                'class'  => 'data' . $clSuffix,
                'legend' => \ForkBB\__('Personal information'),
                'fields' => $fieldset,
            ];
        }

        // контактная информация
        $fieldset = [];
        if ($rules->openEmail) {
            $fieldset['open-email'] = [
                'id'      => 'open-email',
                'class'   => 'pline',
                'type'    => 'link',
                'caption' => \ForkBB\__('Email info'),
                'value'   => \ForkBB\cens($curUser->email),
                'href'    => 'mailto:' . $curUser->email,
            ];
        }
        if ($rules->email) {
            if (0 === $curUser->email_setting) {
                $fieldset['email'] = [
                    'id'      => 'email',
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Email info'),
                    'value'   => \ForkBB\cens($curUser->email),
                    'href'    => 'mailto:' . $curUser->email,
                ];
            } elseif (1 === $curUser->email_setting) {
                $fieldset['email'] = [
                    'id'      => 'email',
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Email info'),
                    'value'   => \ForkBB\__('Send email'),
                    'href'    => $this->c->Router->link('', ['id' => $curUser->id]), // ????
                ];
            }
        }
        if ($isEdit) {
            $fieldset['email_setting'] = [
                'id'      => 'email_setting',
                'class'   => 'block',
                'type'    => 'radio',
                'value'   => $curUser->email_setting,
                'values'  => [
                    0 => \ForkBB\__('Display e-mail label'),
                    1 => \ForkBB\__('Hide allow form label'),
                    2 => \ForkBB\__('Hide both label'),
                ],
                'caption' => \ForkBB\__('Email settings label'),
            ];
        }
        if ($isEdit) {
            $fieldset['url'] = [
                'id'        => 'website',
                'type'      => 'text',
                'maxlength' => 100,
                'caption'   => \ForkBB\__('Website'),
                'value'     => $curUser->url
            ];
        } elseif ($curUser->url) {
            $fieldset['url'] = [
                'id'      => 'website',
                'class'   => 'pline',
                'type'    => 'link',
                'caption' => \ForkBB\__('Website'),
                'value'   => \ForkBB\cens($curUser->url),
                'href'    => \ForkBB\cens($curUser->url),
            ];
        }
        if (! empty($fieldset)) {
            $form['sets'][] = [
                'id'     => 'contacts',
                'class'  => 'data' . $clSuffix,
                'legend' => \ForkBB\__('Contact details'),
                'fields' => $fieldset,
            ];
        }

        // подпись
        if ('1' == $this->c->config->o_signatures) {
            $fieldset = [];
            if ($isEdit) {
                $fieldset['signature'] = [
                    'id'      => 'signature',
                    'type'    => 'textarea',
                    'value'   => $curUser->signature,
                    'caption' => \ForkBB\__('Signature'),
                ];
            } elseif ('' != $curUser->signature) {
                $fieldset['signature'] = [
                    'id'      => 'signature',
                    'type'    => 'yield',
                    'caption' => \ForkBB\__('Signature'),
                    'value'   => 'signature',
                ];
            }
            if (! empty($fieldset)) {
                $form['sets'][] = [
                    'id'     => 'signature',
                    'class'  => 'data' . $clSuffix,
                    'legend' => \ForkBB\__('Signature'),
                    'fields' => $fieldset,
                ];
            }
        }

        // активность
        $fieldset = [];
        $fieldset['registered'] = [
            'id'      => 'registered',
            'class'   => 'pline',
            'type'    => 'str',
            'value'   => \ForkBB\dt($curUser->registered, true),
            'caption' => \ForkBB\__('Registered info'),
        ];
        if ($this->user->isAdmin) {
            $fieldset['ip'] = [
                'id'      => 'ip',
                'class'   => 'pline',
                'type'    => 'link',
                'caption' => 'IP',
                'value'   => $curUser->registration_ip,
                'href'    => $this->c->Router->link('', ['id' => $curUser->id]), // ????
                'title'   => 'IP',
            ];
        }
        if ($rules->lastvisit) {
            $fieldset['lastvisit'] = [
                'id'      => 'lastvisit',
                'class'   => 'pline',
                'type'    => 'str',
                'value'   => \ForkBB\dt($curUser->last_visit, true),
                'caption' => \ForkBB\__('Last visit info'),
            ];
        }
        $fieldset['lastpost'] = [
            'id'      => 'lastpost',
            'class'   => 'pline',
            'type'    => 'str',
            'value'   => \ForkBB\dt($curUser->last_post, true),
            'caption' => \ForkBB\__('Last post info'),
        ];
        if ($curUser->num_posts) {
            if ('1' == $this->user->g_search) {
                $fieldset['posts'] = [
                    'id'      => 'posts',
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Posts info'),
                    'value'   => $this->user->showPostCount ? \ForkBB\num($curUser->num_posts) : \ForkBB\__('Show posts'),
                    'href'    => '',
                    'title'   => \ForkBB\__('Show posts'),
                ];
                $fieldset['topics'] = [
                    'id'      => 'topics',
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Topics info'),
                    'value'   => $this->user->showPostCount ? \ForkBB\num($curUser->num_topics) : \ForkBB\__('Show topics'),
                    'href'    => '',
                    'title'   => \ForkBB\__('Show topics'),
                ];
            } elseif ($this->user->showPostCount) {
                $fieldset['posts'] = [
                    'id'      => 'posts',
                    'class'   => 'pline',
                    'type'    => 'str',
                    'caption' => \ForkBB\__('Posts info'),
                    'value'   => \ForkBB\num($curUser->num_posts),
                ];
                $fieldset['topics'] = [
                    'id'      => 'topics',
                    'class'   => 'pline',
                    'type'    => 'str',
                    'caption' => \ForkBB\__('Topics info'),
                    'value'   => \ForkBB\num($curUser->num_topics),
                ];
            }
        }
        $form['sets'][] = [
            'id'     => 'activity',
            'class'  => 'data' . $clSuffix,
            'legend' => \ForkBB\__('User activity'),
            'fields' => $fieldset,
        ];

        if ($isEdit) {
            $this->robots    = 'noindex';
            $this->crumbs    = $this->crumbs(
                \ForkBB\__('Editing profile'),
                [$curUser->link, \ForkBB\__('User %s', $curUser->username)],
                [$this->c->Router->link('Userlist'), \ForkBB\__('User list')]
            );
        } else {
            $this->canonical = $curUser->link;
            $this->crumbs    = $this->crumbs(
                [$curUser->link, \ForkBB\__('User %s', $curUser->username)],
                [$this->c->Router->link('Userlist'), \ForkBB\__('User list')]
            );
        }

        $this->fIndex    = $rules->my ? 'profile' : 'userlist';
        $this->nameTpl   = 'profile';
        $this->onlinePos = 'profile-' . $curUser->id; // ????
        $this->title     = \ForkBB\__('%s\'s profile', $curUser->username);
        $this->form      = $form;
        $this->curUser   = $curUser;

        $btns = [];
        if ($rules->banUser) {
            $btns['ban-user'] = [
                $this->c->Router->link('',  ['id' => $curUser->id]),
                \ForkBB\__('Ban user'),
            ];
        }
        if ($rules->deleteUser) {
            $btns['delete-user'] = [
                $this->c->Router->link('',  ['id' => $curUser->id]),
                \ForkBB\__('Delete user'),
            ];
        }
        if (! $isEdit && $rules->editProfile) {
            $btns['edit-profile'] = [
                $this->c->Router->link('EditUserProfile',  ['id' => $curUser->id]),
                \ForkBB\__('Edit '),
            ];
        }
        if ($isEdit) {
            $btns['view-profile'] = [
                $curUser->link,
                \ForkBB\__('View '),
            ];
        }
        if ($rules->editConfig) {
            $btns['edit-settings'] = [
                $this->c->Router->link('EditBoardConfig', ['id' => $curUser->id]),
                \ForkBB\__('Configure '),
            ];
        }
        $this->actionBtns = $btns;

        return $this;
    }
}
