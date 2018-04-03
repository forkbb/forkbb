<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Image;
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
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_username' => [$this->c->Validators, 'vCheckUsername'],
                ])->addRules([
                    'token'         => 'token:EditUserProfile',
                    'username'      => $rules->rename? 'required|string:trim,spaces|min:2|max:25|login|check_username' : 'absent',
                    'title'         => $rules->setTitle ? 'string:trim|max:50' : 'absent',
                    'upload_avatar' => $rules->useAvatar ? "image|max:{$this->c->Files->maxImgSize('K')}" : 'absent',
                    'admin_note'    => $this->user->isAdmMod ? 'string:trim|max:30' : 'absent',
                    'realname'      => 'string:trim|max:40',
                    'gender'        => 'required|integer|in:0,1,2',
                    'location'      => 'string:trim|max:30',
                    'email_setting' => 'required|integer|in:0,1,2',
                    'url'           => 'string:trim|max:100',
                    'signature'     => $rules->useSignature ? 'string:trim|max:' . $this->c->config->p_sig_length . '' : 'absent',
                ])->addAliases([
                ])->addArguments([
                    'token'                   => ['id' => $curUser->id],
                    'username.check_username' => $curUser,
                ])->addMessages([
                ]);

            if ($v->validation($_FILES + $_POST)) {
                if ($v->upload_avatar instanceof Image) {
                    $curUser->deleteAvatar();
                    $v->upload_avatar
                        ->rename(false)
                        ->rewrite(true)
                        ->resize((int) $this->c->config->o_avatars_width, (int) $this->c->config->o_avatars_height)
                        ->toFile($this->c->DIR_PUBLIC . "{$this->c->config->o_avatars_dir}/{$curUser->id}.(jpg|png|gif)");
#                    var_dump(
#                        $v->upload_avatar->path(),
#                        $v->upload_avatar->name(),
#                        $v->upload_avatar->ext(),
#                        $v->upload_avatar->size(),
#                       $v->upload_avatar->error()
#                    );
                }
            }

            $this->fIswev  = $v->getErrors();
        }

        $clSuffix = $isEdit ? '-edit' : '';

        if ($isEdit) {
            $form = [
                'action' => $this->c->Router->link('EditUserProfile', ['id' => $curUser->id]),
                'hidden' => [
                    'token' => $this->c->Csrf->create('EditUserProfile', ['id' => $curUser->id]),
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
        $fields = [];
        $fields[] = [
            'class' => 'usertitle',
            'type'  => 'wrap',
        ];
        if ($isEdit && $rules->rename) {
            $fields['username'] = [
                'id'        => 'username',
                'type'      => 'text',
                'maxlength' => 25,
                'caption'   => \ForkBB\__('Username'),
                'required'  => true,
                'pattern'   => '^.{2,25}$',
                'value'     => $curUser->username,
            ];
        } else {
            $fields['username'] = [
                'id'      => 'username',
                'class'   => 'pline',
                'type'    => 'str',
                'caption' => \ForkBB\__('Username'),
                'value'   => $curUser->username,
            ];
        }
        if ($isEdit && $rules->setTitle) {
            $fields['title'] = [
                'id'        => 'title',
                'type'      => 'text',
                'maxlength' => 50,
                'caption'   => \ForkBB\__('Title'),
                'value'     => $curUser->title,
                'info'      => \ForkBB\__('Leave blank'),
            ];
        } else {
            $fields['title'] = [
                'id'      => 'title',
                'class'   => 'pline',
                'type'    => 'str',
                'caption' => \ForkBB\__('Title'),
                'value'   => $curUser->title(),
            ];
        }
        $fields[] = [
            'type' => 'endwrap',
        ];
        if ($rules->useAvatar) {
            if ($isEdit && ! $curUser->avatar) {
                $fields['avatar'] = [
                    'id'      => 'avatar',
                    'class'   => 'pline',
                    'type'    => 'str',
                    'caption' => \ForkBB\__('Avatar'),
                    'value'   => \ForkBB\__('Not uploaded'),
                ];
            } elseif ($curUser->avatar) {
                $fields['avatar'] = [
                    'id'      => 'avatar',
                    'type'    => 'yield',
                    'caption' => \ForkBB\__('Avatar'),
                    'value'   => 'avatar',
                ];
            }
            if ($isEdit) {
                $form['enctype'] = 'multipart/form-data';
                $form['hidden']['MAX_FILE_SIZE'] = $this->c->Files->maxImgSize();

                $fields['upload_avatar'] = [
                    'id'        => 'upload_avatar',
                    'type'      => 'file',
                    'caption'   => \ForkBB\__('New avatar'),
                    'info'      => \ForkBB\__('New avatar info',
                        \ForkBB\num($this->c->config->o_avatars_width),
                        \ForkBB\num($this->c->config->o_avatars_height),
                        \ForkBB\num($this->c->config->o_avatars_size),
                        \ForkBB\size($this->c->config->o_avatars_size)
                    ),
                ];
            }
        }
        $form['sets'][] = [
            'id'     => 'header',
            'class'  => 'header' . $clSuffix,
#            'legend' => \ForkBB\__('Options'),
            'fields' => $fields,
        ];

        // примечание администрации
        if ($this->user->isAdmMod) {
            $fields = [];
            if ($isEdit) {
                $fields['admin_note'] = [
                    'id'        => 'admin_note',
                    'type'      => 'text',
                    'maxlength' => 30,
                    'caption'   => \ForkBB\__('Admin note'),
                    'value'     => $curUser->admin_note,
                ];
            } elseif ('' != $curUser->admin_note) {
                $fields['admin_note'] = [
                    'id'        => 'admin_note',
                    'class'   => 'pline',
                    'type'      => 'str',
                    'caption'   => \ForkBB\__('Admin note'),
                    'value'     => $curUser->admin_note,
                ];
            }
            if (! empty($fields)) {
                $form['sets'][] = [
                    'id'     => 'note',
                    'class'  => 'data' . $clSuffix,
                    'legend' => \ForkBB\__('Admin note'),
                    'fields' => $fields,
                ];
            }
        }

        // личное
        $fields = [];
        if ($isEdit) {
            $fields['realname'] = [
                'id'        => 'realname',
                'type'      => 'text',
                'maxlength' => 40,
                'caption'   => \ForkBB\__('Realname'),
                'value'     => $curUser->realname,
            ];
        } elseif ('' != $curUser->realname) {
            $fields['realname'] = [
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
            $fields['gender'] = [
                'id'      => 'gender',
                'class'   => 'block',
                'type'    => 'radio',
                'value'   => $curUser->gender,
                'values'  => $genders,
                'caption' => \ForkBB\__('Gender'),
            ];
        } elseif ($curUser->gender && isset($genders[$curUser->gender])) {
            $fields['gender'] = [
                'id'      => 'gender',
                'class'   => 'pline',
                'type'    => 'str',
                'value'   => $genders[$curUser->gender],
                'caption' => \ForkBB\__('Gender'),
            ];
        }
        if ($isEdit) {
            $fields['location'] = [
                'id'        => 'location',
                'type'      => 'text',
                'maxlength' => 30,
                'caption'   => \ForkBB\__('Location'),
                'value'     => $curUser->location,
            ];
        } elseif ('' != $curUser->location) {
            $fields['location'] = [
                'id'      => 'location',
                'class'   => 'pline',
                'type'    => 'str',
                'caption' => \ForkBB\__('Location'),
                'value'   => \ForkBB\cens($curUser->location),
            ];
        }
        if (! empty($fields)) {
            $form['sets'][] = [
                'id'     => 'personal',
                'class'  => 'data' . $clSuffix,
                'legend' => \ForkBB\__('Personal information'),
                'fields' => $fields,
            ];
        }

        // контактная информация
        $fields = [];
        if ($rules->viewOEmail) {
            $fields['open-email'] = [
                'id'      => 'open-email',
                'class'   => 'pline',
                'type'    => 'link',
                'caption' => \ForkBB\__('Email info'),
                'value'   => \ForkBB\cens($curUser->email),
                'href'    => 'mailto:' . $curUser->email,
            ];
        }
        if ($rules->viewEmail) {
            if (0 === $curUser->email_setting) {
                $fields['email'] = [
                    'id'      => 'email',
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Email info'),
                    'value'   => \ForkBB\cens($curUser->email),
                    'href'    => 'mailto:' . $curUser->email,
                ];
            } elseif (1 === $curUser->email_setting) {
                $fields['email'] = [
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
            $fields['email_setting'] = [
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
            $fields['url'] = [
                'id'        => 'website',
                'type'      => 'text',
                'maxlength' => 100,
                'caption'   => \ForkBB\__('Website'),
                'value'     => $curUser->url
            ];
        } elseif ($curUser->url) {
            $fields['url'] = [
                'id'      => 'website',
                'class'   => 'pline',
                'type'    => 'link',
                'caption' => \ForkBB\__('Website'),
                'value'   => \ForkBB\cens($curUser->url),
                'href'    => \ForkBB\cens($curUser->url),
            ];
        }
        if (! empty($fields)) {
            $form['sets'][] = [
                'id'     => 'contacts',
                'class'  => 'data' . $clSuffix,
                'legend' => \ForkBB\__('Contact details'),
                'fields' => $fields,
            ];
        }

        // подпись
        if ($rules->useSignature) {
            $fields = [];
            if ($isEdit) {
                $fields['signature'] = [
                    'id'      => 'signature',
                    'type'    => 'textarea',
                    'value'   => $curUser->signature,
                    'caption' => \ForkBB\__('Signature'),
                    'info'    => \ForkBB\__('Sig max size', \ForkBB\num($this->c->config->p_sig_length), \ForkBB\num($this->c->config->p_sig_lines)),
                ];
            } elseif ('' != $curUser->signature) {
                $fields['signature'] = [
                    'id'      => 'signature',
                    'type'    => 'yield',
                    'caption' => \ForkBB\__('Signature'),
                    'value'   => 'signature',
                ];
            }
            if (! empty($fields)) {
                $form['sets'][] = [
                    'id'     => 'signature',
                    'class'  => 'data' . $clSuffix,
                    'legend' => \ForkBB\__('Signature'),
                    'fields' => $fields,
                ];
            }
        }

        // активность
        $fields = [];
        $fields['registered'] = [
            'id'      => 'registered',
            'class'   => 'pline',
            'type'    => 'str',
            'value'   => \ForkBB\dt($curUser->registered, true),
            'caption' => \ForkBB\__('Registered info'),
        ];
        if ($rules->viewIP) {
            $fields['ip'] = [
                'id'      => 'ip',
                'class'   => 'pline',
                'type'    => 'link',
                'caption' => 'IP',
                'value'   => $curUser->registration_ip,
                'href'    => $this->c->Router->link('', ['id' => $curUser->id]), // ????
                'title'   => 'IP',
            ];
        }
        if ($rules->viewLastVisit) {
            $fields['lastvisit'] = [
                'id'      => 'lastvisit',
                'class'   => 'pline',
                'type'    => 'str',
                'value'   => \ForkBB\dt($curUser->last_visit, true),
                'caption' => \ForkBB\__('Last visit info'),
            ];
        }
        $fields['lastpost'] = [
            'id'      => 'lastpost',
            'class'   => 'pline',
            'type'    => 'str',
            'value'   => \ForkBB\dt($curUser->last_post, true),
            'caption' => \ForkBB\__('Last post info'),
        ];
        if ($curUser->num_posts) {
            if ('1' == $this->user->g_search) {
                $fields['posts'] = [
                    'id'      => 'posts',
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Posts info'),
                    'value'   => $this->user->showPostCount ? \ForkBB\num($curUser->num_posts) : \ForkBB\__('Show posts'),
                    'href'    => '',
                    'title'   => \ForkBB\__('Show posts'),
                ];
                $fields['topics'] = [
                    'id'      => 'topics',
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Topics info'),
                    'value'   => $this->user->showPostCount ? \ForkBB\num($curUser->num_topics) : \ForkBB\__('Show topics'),
                    'href'    => '',
                    'title'   => \ForkBB\__('Show topics'),
                ];
            } elseif ($this->user->showPostCount) {
                $fields['posts'] = [
                    'id'      => 'posts',
                    'class'   => 'pline',
                    'type'    => 'str',
                    'caption' => \ForkBB\__('Posts info'),
                    'value'   => \ForkBB\num($curUser->num_posts),
                ];
                $fields['topics'] = [
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
            'fields' => $fields,
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
