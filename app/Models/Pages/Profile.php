<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Image;
use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
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
        $this->curUser = $this->c->users->load((int) $args['id']);

        if (! $this->curUser instanceof User || ($this->curUser->isUnverified && ! $this->user->isAdmMod)) {
            return $this->c->Message->message('Bad request');
        }

        $this->rules = $this->c->ProfileRules->setUser($this->curUser);

        if ($isEdit) {
            if (! $this->rules->editProfile) {
                return $this->c->Message->message('Bad request');
            }

            $this->c->Lang->load('profile_other');
        }

        $this->c->Lang->load('profile');

        if ($isEdit && 'POST' === $method) {
            if ($this->rules->rename) {
                $ruleUsername = 'required|string:trim,spaces|username';
            } else {
                $ruleUsername = 'absent';
            }

            if ($this->rules->setTitle) {
                $ruleTitle = 'string:trim|max:50|noURL';
            } else {
                $ruleTitle = 'absent';
            }

            if ($this->rules->useAvatar) {
                $ruleAvatar    = "image|max:{$this->c->Files->maxImgSize('K')}";
                $ruleDelAvatar = $this->curUser->avatar ? 'checkbox' : 'absent';
            } else {
                $ruleAvatar    = 'absent';
                $ruleDelAvatar = 'absent';
            }

            if ($this->user->isAdmMod) {
                $ruleAdminNote = 'string:trim|max:30';
            } else {
                $ruleAdminNote = 'absent';
            }

            if ($this->rules->editWebsite) {
                $ruleWebsite = 'string:trim|max:100'; // ???? валидация url?
            } else {
                $ruleWebsite = 'absent';
            }

            if ($this->rules->useSignature) {
                $ruleSignature = "string:trim|max:{$this->c->config->p_sig_length}|check_signature";
            } else {
                $ruleSignature = 'absent';
            }

            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_signature' => [$this, 'vCheckSignature'],
                ])->addRules([
                    'token'         => 'token:EditUserProfile',
                    'username'      => $ruleUsername,
                    'title'         => $ruleTitle,
                    'upload_avatar' => $ruleAvatar,
                    'delete_avatar' => $ruleDelAvatar,
                    'admin_note'    => $ruleAdminNote,
                    'realname'      => 'string:trim|max:40|noURL:1',
                    'gender'        => 'required|integer|in:0,1,2',
                    'location'      => 'string:trim|max:30|noURL:1',
                    'email_setting' => 'required|integer|in:0,1,2',
                    'url'           => $ruleWebsite,
                    'signature'     => $ruleSignature,
                ])->addAliases([
                    'username'      => 'Username',
                    'title'         => 'Title',
                    'upload_avatar' => 'New avatar',
                    'delete_avatar' => 'Delete avatar',
                    'admin_note'    => 'Admin note',
                    'realname'      => 'Realname',
                    'gender'        => 'Gender',
                    'location'      => 'Location',
                    'email_setting' => 'Email settings label',
                    'url'           => 'Website',
                    'signature'     => 'Signature',
                ])->addArguments([
                    'token'             => ['id' => $this->curUser->id],
                    'username.username' => $this->curUser,
                ])->addMessages([
                ]);

            $valid = $v->validation($_FILES + $_POST);
            $data  = $v->getData();
            unset($data['token'], $data['upload_avatar']);

            if ($valid) {
                if ($v->delete_avatar || $v->upload_avatar instanceof Image) {
                    $this->curUser->deleteAvatar();
                }

                if ($v->upload_avatar instanceof Image) {
                    $v->upload_avatar
                        ->rename(false)
                        ->rewrite(true)
                        ->resize((int) $this->c->config->o_avatars_width, (int) $this->c->config->o_avatars_height)
                        ->toFile($this->c->DIR_PUBLIC . "{$this->c->config->o_avatars_dir}/{$this->curUser->id}.(jpg|png|gif)");
                }

                $this->curUser->replAttrs($data, true);

                $this->c->DB->beginTransaction();

                $this->c->users->update($this->curUser);

                $this->c->DB->commit();

                return $this->c->Redirect->page('EditUserProfile', ['id' => $this->curUser->id])->message('Profile redirect');
            } else {
                $this->fIswev = $v->getErrors();

                $this->curUser->replAttrs($data);
            }
        }

        $crumbs = [];

        if ($isEdit) {
            $this->robots = 'noindex';
            $crumbs[]     = [$this->c->Router->link('EditUserProfile', ['id' => $this->curUser->id]), \ForkBB\__('Editing profile')];
        } else {
            $this->canonical = $this->curUser->link;
        }

        $this->crumbs     = $this->extCrumbs(...$crumbs);
        $this->fIndex     = $this->rules->my ? 'profile' : 'userlist';
        $this->nameTpl    = 'profile';
        $this->onlinePos  = 'profile-' . $this->curUser->id; // ????
        $this->title      = \ForkBB\__('%s\'s profile', $this->curUser->username);
        $this->form       = $this->profileForm($isEdit);
        $this->actionBtns = $this->btns($isEdit ? 'edit' : 'view');

        return $this;
    }

    /**
     * Подготавливает данные для шаблона просмотра профиля
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function email(array $args, $method)
    {
        $this->curUser = $this->c->users->load((int) $args['id']);

        if (! $this->curUser instanceof User || ($this->curUser->isUnverified && ! $this->user->isAdmMod)) {
            return $this->c->Message->message('Bad request');
        }

        $this->rules = $this->c->ProfileRules->setUser($this->curUser);

        if (! $this->rules->editEmail) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('profile');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_password' => [$this, 'vCheckPassword'],
                ])->addRules([
                    'token'     => 'token:ChangeUserEmail',
                    'password'  => 'required|string:trim|check_password',
                    'new_email' => 'required|string:trim,lower|email:banned,unique,flood',
                ])->addAliases([
                    'new_email' => 'New email',
                    'password'  => 'Your password',
                ])->addArguments([
                    'token'           => ['id' => $this->curUser->id],
                    'new_email.email' => $this->curUser,
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                if ($v->new_email === $this->curUser->email) {
                    return $this->c->Redirect->page('EditUserProfile', ['id' => $this->curUser->id])->message('Email is old redirect');
                }

                if ($this->user->isAdmin || '1' != $this->c->config->o_regs_verify) {
                    $this->curUser->email           = $v->new_email;
                    $this->curUser->email_confirmed = 0;

                    $this->c->users->update($this->curUser);

                    return $this->c->Redirect->page('EditUserProfile', ['id' => $this->curUser->id])->message('Email changed redirect');
                } else {
                    $key  = $this->c->Secury->randomPass(33);
                    $hash = $this->c->Secury->hash($this->curUser->id . $v->new_email . $key);
                    $link = $this->c->Router->link('SetNewEmail', ['id' => $this->curUser->id, 'email' => $v->new_email, 'key' => $key, 'hash' => $hash]);
                    $tplData = [
                        'fRootLink' => $this->c->Router->link('Index'),
                        'fMailer'   => \ForkBB\__('Mailer', $this->c->config->o_board_title),
                        'username'  => $this->curUser->username,
                        'link'      => $link,
                    ];

                    try {
                        $isSent = $this->c->Mail
                            ->reset()
                            ->setFolder($this->c->DIR_LANG)
                            ->setLanguage($this->curUser->language)
                            ->setTo($v->new_email, $this->curUser->username)
                            ->setFrom($this->c->config->o_webmaster_email, \ForkBB\__('Mailer', $this->c->config->o_board_title))
                            ->setTpl('activate_email.tpl', $tplData)
                            ->send();
                    } catch (MailException $e) {
                        $isSent = false;
                    }

                    if ($isSent) {
                        $this->curUser->activate_string = $key;
                        $this->curUser->last_email_sent = \time();

                        $this->c->users->update($this->curUser);

                        return $this->c->Message->message(\ForkBB\__('Activate email sent', $this->c->config->o_admin_email), false, 200);
                    } else {
                        return $this->c->Message->message(\ForkBB\__('Error mail', $this->c->config->o_admin_email), true, 200);
                    }
                }
            }

            $this->fIswev = $v->getErrors();
        }

        $form = [
            'action' => $this->c->Router->link('ChangeUserEmail', ['id' => $this->curUser->id]),
            'hidden' => [
                'token' => $this->c->Csrf->create('ChangeUserEmail', ['id' => $this->curUser->id]),
            ],
            'sets'   => [
                [
                    'class'  => 'data-edit',
                    'fields' => [
                        'new_email' => [
                            'id'        => 'new_email',
                            'type'      => 'text',
                            'maxlength' => 80,
                            'caption'   => \ForkBB\__('New email'),
                            'required'  => true,
                            'pattern'   => '.+@.+',
                            'value'     => isset($v->new_email) ? $v->new_email : $this->curUser->email,
                            'info'      => ! $this->user->isAdmin && '1' == $this->c->config->o_regs_verify ? \ForkBB\__('Email instructions') : null,
                        ],
                        'password' => [
                            'id'        => 'password',
                            'type'      => 'password',
                            'caption'   => \ForkBB\__('Your password'),
                            'required'  => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Submit'),
                    'accesskey' => 's',
                ],
            ],
        ];

        $this->robots     = 'noindex';
        $this->crumbs     = $this->extCrumbs(
            [$this->c->Router->link('ChangeUserEmail', ['id' => $this->curUser->id]), \ForkBB\__('Change email')]
        );
        $this->fIndex     = $this->rules->my ? 'profile' : 'userlist';
        $this->nameTpl    = 'profile';
        $this->onlinePos  = 'profile-' . $this->curUser->id; // ????
        $this->title      = \ForkBB\__('%s\'s profile', $this->curUser->username);
        $this->form       = $form;
        $this->actionBtns = $this->btns('edit');

        return $this;
    }

    /**
     * Изменяет почтовый адрес пользователя по ссылке активации
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function setEmail(array $args, $method)
    {
        if ($this->user->id !== (int) $args['id']
            || ! \hash_equals($args['hash'], $this->c->Secury->hash($args['id'] . $args['email'] . $args['key']))
            || empty($this->user->activate_string)
            || ! \hash_equals($this->user->activate_string, $args['key'])
        ) {
            return $this->c->Message->message('Bad request', false);
        }

        $this->c->Lang->load('profile');

        $this->user->email           = $args['email'];
        $this->user->email_confirmed = 1;
        $this->user->activate_string = '';

        $this->c->users->update($this->user);

        return $this->c->Redirect->url($this->user->link)->message('Email changed redirect');
    }

    /**
     * Дополнительная проверка signature
     *
     * @param Validator $v
     * @param string $signature
     *
     * @return string
     */
    public function vCheckSignature(Validator $v, $signature)
    {
        if ('' != $signature) {
            // после цензуры текст сообщения пустой
            if (\ForkBB\cens($signature) == '') {
                $v->addError('No signature after censoring');
            // количество строк
            } elseif (\substr_count($signature, "\n") >= $this->c->config->p_sig_lines) {
                $v->addError('Signature has too many lines');
            // текст сообщения только заглавными буквами
            } elseif (! $this->c->user->isAdmin
                && '0' == $this->c->config->p_sig_all_caps
                && \preg_match('%\p{Lu}%u', $signature)
                && ! \preg_match('%\p{Ll}%u', $signature)
            ) {
                $v->addError('All caps signature');
            // проверка парсером
            } else {
                $signature = $this->c->Parser->prepare($signature, true); //????

                foreach($this->c->Parser->getErrors() as $error) {
                    $v->addError($error);
                }
            }
        }

        return $signature;
    }

    /**
     * Проверяет пароль на совпадение с текущим пользователем
     *
     * @param Validator $v
     * @param string $password
     *
     * @return string
     */
    public function vCheckPassword(Validator $v, $password)
    {
        if (! \password_verify($password, $this->user->password)) {
            $v->addError('Invalid password');
        }

        return $password;
    }

    /**
     * Дополняет массив хлебных крошек
     *
     * @param mixed ...$args
     *
     * @return array
     */
    protected function extCrumbs(...$args)
    {
        $args[] = [$this->curUser->link, \ForkBB\__('User %s', $this->curUser->username)];
        $args[] = [$this->c->Router->link('Userlist'), \ForkBB\__('User list')];

        return $this->crumbs(...$args);
    }

    /**
     * Формирует массив кнопок
     *
     * @param string $type
     *
     * @return array
     */
    protected function btns($type)
    {
        $btns = [];
        if ($this->rules->banUser) {
            $btns['ban-user'] = [
                $this->c->Router->link('',  ['id' => $this->curUser->id]),
                \ForkBB\__('Ban user'),
            ];
        }
        if ($this->rules->deleteUser) {
            $btns['delete-user'] = [
                $this->c->Router->link('',  ['id' => $this->curUser->id]),
                \ForkBB\__('Delete user'),
            ];
        }
        if ('edit' != $type && $this->rules->editProfile) {
            $btns['edit-profile'] = [
                $this->c->Router->link('EditUserProfile',  ['id' => $this->curUser->id]),
                \ForkBB\__('Edit '),
            ];
        }
        if ('view' != $type) {
            $btns['view-profile'] = [
                $this->curUser->link,
                \ForkBB\__('View '),
            ];
        }
        if ('config' != $type && $this->rules->editConfig) {
            $btns['edit-settings'] = [
                $this->c->Router->link('EditBoardConfig', ['id' => $this->curUser->id]),
                \ForkBB\__('Configure '),
            ];
        }
        return $btns;
    }

    /**
     * Создает массив данных для просмотра/редактирования профиля
     *
     * @param bool $isEdit
     *
     * @return array
     */
    protected function profileForm($isEdit)
    {
        $clSuffix = $isEdit ? '-edit' : '';

        if ($isEdit) {
            $form = [
                'action' => $this->c->Router->link('EditUserProfile', ['id' => $this->curUser->id]),
                'hidden' => [
                    'token' => $this->c->Csrf->create('EditUserProfile', ['id' => $this->curUser->id]),
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
        if ($isEdit && $this->rules->rename) {
            $fields['username'] = [
                'id'        => 'username',
                'type'      => 'text',
                'maxlength' => 25,
                'caption'   => \ForkBB\__('Username'),
                'required'  => true,
                'pattern'   => '^.{2,25}$',
                'value'     => $this->curUser->username,
            ];
        } else {
            $fields['username'] = [
                'id'      => 'username',
                'class'   => 'pline',
                'type'    => 'str',
                'caption' => \ForkBB\__('Username'),
                'value'   => $this->curUser->username,
            ];
        }
        if ($isEdit && $this->rules->setTitle) {
            $fields['title'] = [
                'id'        => 'title',
                'type'      => 'text',
                'maxlength' => 50,
                'caption'   => \ForkBB\__('Title'),
                'value'     => $this->curUser->title,
                'info'      => \ForkBB\__('Leave blank'),
            ];
        } else {
            $fields['title'] = [
                'id'      => 'title',
                'class'   => 'pline',
                'type'    => 'str',
                'caption' => \ForkBB\__('Title'),
                'value'   => $this->curUser->title(),
            ];
        }
        $fields[] = [
            'type' => 'endwrap',
        ];
        if ($this->rules->useAvatar) {
            if ($isEdit && ! $this->curUser->avatar) {
                $fields['avatar'] = [
                    'id'      => 'avatar',
                    'class'   => 'pline',
                    'type'    => 'str',
                    'caption' => \ForkBB\__('Avatar'),
                    'value'   => \ForkBB\__('Not uploaded'),
                ];
            } elseif ($this->curUser->avatar) {
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

                if ($this->curUser->avatar) {
                    $fields['delete_avatar'] = [
                        'id'      => 'delete_avatar',
                        'type'    => 'checkbox',
                        'label'   => \ForkBB\__('Delete avatar'),
                        'value'   => '1',
                        'checked' => false,
                    ];
                }

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
                    'value'     => $this->curUser->admin_note,
                ];
            } elseif ('' != $this->curUser->admin_note) {
                $fields['admin_note'] = [
                    'id'        => 'admin_note',
                    'class'   => 'pline',
                    'type'      => 'str',
                    'caption'   => \ForkBB\__('Admin note'),
                    'value'     => $this->curUser->admin_note,
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
                'value'     => $this->curUser->realname,
            ];
        } elseif ('' != $this->curUser->realname) {
            $fields['realname'] = [
                'id'      => 'realname',
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
        if ($isEdit) {
            $fields['gender'] = [
                'id'      => 'gender',
                'class'   => 'block',
                'type'    => 'radio',
                'value'   => $this->curUser->gender,
                'values'  => $genders,
                'caption' => \ForkBB\__('Gender'),
            ];
        } elseif ($this->curUser->gender && isset($genders[$this->curUser->gender])) {
            $fields['gender'] = [
                'id'      => 'gender',
                'class'   => 'pline',
                'type'    => 'str',
                'value'   => $genders[$this->curUser->gender],
                'caption' => \ForkBB\__('Gender'),
            ];
        }
        if ($isEdit) {
            $fields['location'] = [
                'id'        => 'location',
                'type'      => 'text',
                'maxlength' => 30,
                'caption'   => \ForkBB\__('Location'),
                'value'     => $this->curUser->location,
            ];
        } elseif ('' != $this->curUser->location) {
            $fields['location'] = [
                'id'      => 'location',
                'class'   => 'pline',
                'type'    => 'str',
                'caption' => \ForkBB\__('Location'),
                'value'   => \ForkBB\cens($this->curUser->location),
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
        if ($this->rules->viewOEmail) {
            $fields['open-email'] = [
                'id'      => 'open-email',
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
                    'id'      => 'email',
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Email info'),
                    'value'   => \ForkBB\cens($this->curUser->email),
                    'href'    => 'mailto:' . $this->curUser->email,
                ];
            } elseif (1 === $this->curUser->email_setting) {
                $fields['email'] = [
                    'id'      => 'email',
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Email info'),
                    'value'   => \ForkBB\__('Send email'),
                    'href'    => $this->c->Router->link('', ['id' => $this->curUser->id]), // ????
                ];
            }
        }
        if ($isEdit) {
            if ($this->rules->editEmail) {
                $fields[] = [
                    'id'    => 'change_email',
                    'type'  => 'link',
                    'value' => \ForkBB\__('To change email'),
                    'href'  => $this->c->Router->link('ChangeUserEmail', ['id' => $this->curUser->id]),
                ];
            }
            $fields['email_setting'] = [
                'id'      => 'email_setting',
                'class'   => 'block',
                'type'    => 'radio',
                'value'   => $this->curUser->email_setting,
                'values'  => [
                    0 => \ForkBB\__('Display e-mail label'),
                    1 => \ForkBB\__('Hide allow form label'),
                    2 => \ForkBB\__('Hide both label'),
                ],
                'caption' => \ForkBB\__('Email settings label'),
            ];
        }
        if ($this->rules->editWebsite && $isEdit) {
            $fields['url'] = [
                'id'        => 'website',
                'type'      => 'text',
                'maxlength' => 100,
                'caption'   => \ForkBB\__('Website'),
                'value'     => isset($v->url) ? $v->url : $this->curUser->url,
            ];
        } elseif ($this->rules->viewWebsite && $this->curUser->url) {
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
            $form['sets'][] = [
                'id'     => 'contacts',
                'class'  => 'data' . $clSuffix,
                'legend' => \ForkBB\__('Contact details'),
                'fields' => $fields,
            ];
        }

        // подпись
        if ($this->rules->useSignature) {
            $fields = [];
            if ($isEdit) {
                $fields['signature'] = [
                    'id'      => 'signature',
                    'type'    => 'textarea',
                    'value'   => $this->curUser->signature,
                    'caption' => \ForkBB\__('Signature'),
                    'info'    => \ForkBB\__('Sig max size', \ForkBB\num($this->c->config->p_sig_length), \ForkBB\num($this->c->config->p_sig_lines)),
                ];
            } elseif ('' != $this->curUser->signature) {
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
            'value'   => \ForkBB\dt($this->curUser->registered, true),
            'caption' => \ForkBB\__('Registered info'),
        ];
        if ($this->rules->viewLastVisit) {
            $fields['lastvisit'] = [
                'id'      => 'lastvisit',
                'class'   => 'pline',
                'type'    => 'str',
                'value'   => \ForkBB\dt($this->curUser->last_visit, true),
                'caption' => \ForkBB\__('Last visit info'),
            ];
        }
        $fields['lastpost'] = [
            'id'      => 'lastpost',
            'class'   => 'pline',
            'type'    => 'str',
            'value'   => \ForkBB\dt($this->curUser->last_post, true),
            'caption' => \ForkBB\__('Last post info'),
        ];
        if ($this->curUser->num_posts) {
            if ('1' == $this->user->g_search) {
                $fields['posts'] = [
                    'id'      => 'posts',
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Posts info'),
                    'value'   => $this->user->showPostCount ? \ForkBB\num($this->curUser->num_posts) : \ForkBB\__('Show posts'),
                    'href'    => '',
                    'title'   => \ForkBB\__('Show posts'),
                ];
                $fields['topics'] = [
                    'id'      => 'topics',
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Topics info'),
                    'value'   => $this->user->showPostCount ? \ForkBB\num($this->curUser->num_topics) : \ForkBB\__('Show topics'),
                    'href'    => '',
                    'title'   => \ForkBB\__('Show topics'),
                ];
            } elseif ($this->user->showPostCount) {
                $fields['posts'] = [
                    'id'      => 'posts',
                    'class'   => 'pline',
                    'type'    => 'str',
                    'caption' => \ForkBB\__('Posts info'),
                    'value'   => \ForkBB\num($this->curUser->num_posts),
                ];
                $fields['topics'] = [
                    'id'      => 'topics',
                    'class'   => 'pline',
                    'type'    => 'str',
                    'caption' => \ForkBB\__('Topics info'),
                    'value'   => \ForkBB\num($this->curUser->num_topics),
                ];
            }
        }
        if ($this->rules->viewIP) {
            $fields['ip'] = [
                'id'      => 'ip',
                'class'   => 'pline',
                'type'    => 'link',
                'caption' => \ForkBB\__('IP'),
                'value'   => $this->curUser->registration_ip,
                'href'    => $this->c->Router->link('', ['id' => $this->curUser->id]), // ????
                'title'   => \ForkBB\__('IP title'),
            ];
        }
        $form['sets'][] = [
            'id'     => 'activity',
            'class'  => 'data' . $clSuffix,
            'legend' => \ForkBB\__('User activity'),
            'fields' => $fields,
        ];

        return $form;
    }
}
