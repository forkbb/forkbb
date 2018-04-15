<?php

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Core\Image;
use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Pages\Profile;
use ForkBB\Models\User\Model as User;

class Edit extends Profile
{
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
        if (false === $this->initProfile($args['id']) || ! $this->rules->editProfile) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('profile_other');

        if ('POST' === $method) {
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

        $this->crumbs     = $this->crumbsExt([$this->c->Router->link('EditUserProfile', ['id' => $this->curUser->id]), \ForkBB\__('Editing profile')]);
        $this->form       = $this->form();
        $this->actionBtns = $this->btns('edit');

        return $this;
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
     * Создает массив данных для формы
     *
     * @return array
     */
    protected function form()
    {
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

        // имя, титул и аватара
        $fields = [];
        $fields[] = [
            'class' => 'usertitle',
            'type'  => 'wrap',
        ];
        if ($this->rules->rename) {
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
        if ($this->rules->editPass) {
            $fields[] = [
                'id'    => 'change_pass',
                'type'  => 'link',
                'value' => \ForkBB\__('Change passphrase'),
                'href'  => $this->c->Router->link('EditUserPass', ['id' => $this->curUser->id]),
            ];
        }
        if ($this->rules->setTitle) {
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
            if (! $this->curUser->avatar) {
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
        $form['sets'][] = [
            'id'     => 'header',
            'class'  => 'header-edit',
#            'legend' => \ForkBB\__('Options'),
            'fields' => $fields,
        ];

        // примечание администрации
        if ($this->user->isAdmMod) {
            $form['sets'][] = [
                'id'     => 'note',
                'class'  => 'data-edit',
                'legend' => \ForkBB\__('Admin note'),
                'fields' => [
                    'admin_note' => [
                        'id'        => 'admin_note',
                        'type'      => 'text',
                        'maxlength' => 30,
                        'caption'   => \ForkBB\__('Admin note'),
                        'value'     => $this->curUser->admin_note,
                    ],
                ],
            ];
        }

        // личное
        $fields = [];
        $fields['realname'] = [
            'id'        => 'realname',
            'type'      => 'text',
            'maxlength' => 40,
            'caption'   => \ForkBB\__('Realname'),
            'value'     => $this->curUser->realname,
        ];
        $genders = [
            0 => \ForkBB\__('Do not display'),
            1 => \ForkBB\__('Male'),
            2 => \ForkBB\__('Female'),
        ];
        $fields['gender'] = [
            'id'      => 'gender',
            'class'   => 'block',
            'type'    => 'radio',
            'value'   => $this->curUser->gender,
            'values'  => $genders,
            'caption' => \ForkBB\__('Gender'),
        ];
        $fields['location'] = [
            'id'        => 'location',
            'type'      => 'text',
            'maxlength' => 30,
            'caption'   => \ForkBB\__('Location'),
            'value'     => $this->curUser->location,
        ];
        $form['sets'][] = [
            'id'     => 'personal',
            'class'  => 'data-edit',
            'legend' => \ForkBB\__('Personal information'),
            'fields' => $fields,
        ];

        // контактная информация
        $fields = [];
        if ($this->rules->viewOEmail) {
            $fields['open-email'] = [
                'id'      => 'open-email',
                'class'   => 'pline',
                'type'    => 'str',
                'caption' => \ForkBB\__('Email info'),
                'value'   => \ForkBB\cens($this->curUser->email),
            ];
        }
        if ($this->rules->editEmail) {
            $fields[] = [
                'id'    => 'change_email',
                'type'  => 'link',
                'value' => \ForkBB\__('To change email'),
                'href'  => $this->c->Router->link('EditUserEmail', ['id' => $this->curUser->id]),
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

        if ($this->rules->editWebsite) {
            $fields['url'] = [
                'id'        => 'website',
                'type'      => 'text',
                'maxlength' => 100,
                'caption'   => \ForkBB\__('Website'),
                'value'     => $this->curUser->url,
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
        $form['sets'][] = [
            'id'     => 'contacts',
            'class'  => 'data-edit',
            'legend' => \ForkBB\__('Contact details'),
            'fields' => $fields,
        ];

        // подпись
        if ($this->rules->useSignature) {
            $fields = [];
            $fields['signature'] = [
                'id'      => 'signature',
                'type'    => 'textarea',
                'value'   => $this->curUser->signature,
                'caption' => \ForkBB\__('Signature'),
                'info'    => \ForkBB\__('Sig max size', \ForkBB\num($this->c->config->p_sig_length), \ForkBB\num($this->c->config->p_sig_lines)),
            ];
            $form['sets'][] = [
                'id'     => 'signature',
                'class'  => 'data-edit',
                'legend' => \ForkBB\__('Signature'),
                'fields' => $fields,
            ];
        }

        return $form;
    }
}
