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
     * Подготавливает данные для шаблона настройки форума
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function config(array $args, $method)
    {
        $this->curUser = $this->c->users->load((int) $args['id']);

        if (! $this->curUser instanceof User || ($this->curUser->isUnverified && ! $this->user->isAdmMod)) {
            return $this->c->Message->message('Bad request');
        }

        $this->rules = $this->c->ProfileRules->setUser($this->curUser);

        if (! $this->rules->editConfig) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('profile');
        $this->c->Lang->load('profile_other');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'        => 'token:EditBoardConfig',
                    'language'     => 'required|string:trim|in:' . \implode(',', $this->c->Func->getLangs()),
                    'style'        => 'required|string:trim|in:' . \implode(',', $this->c->Func->getStyles()),
                    'timezone'     => 'required|string:trim|in:-12,-11,-10,-9.5,-9,-8.5,-8,-7,-6,-5,-4,-3.5,-3,-2,-1,0,1,2,3,3.5,4,4.5,5,5.5,5.75,6,6.5,7,8,8.75,9,9.5,10,10.5,11,11.5,12,12.75,13,14',
                    'dst'          => 'required|integer|in:0,1',
                    'time_format'  => 'required|integer|in:' . \implode(',', \array_keys($this->c->TIME_FORMATS)),
                    'date_format'  => 'required|integer|in:' . \implode(',', \array_keys($this->c->DATE_FORMATS)),
                    'show_smilies' => 'required|integer|in:0,1',
                    'show_sig'     => 'required|integer|in:0,1',
                    'show_avatars' => 'required|integer|in:0,1',
                    'show_img'     => 'required|integer|in:0,1',
                    'show_img_sig' => 'required|integer|in:0,1',
                    'disp_topics'  => 'integer|min:10|max:50',
                    'disp_posts'   => 'integer|min:10|max:50',
                ])->addAliases([
                    'language'     => 'Language',
                    'style'        => 'Style',
                    'timezone'     => 'Time zone',
                    'dst'          => 'DST label',
                    'time_format'  => 'Time format',
                    'date_format'  => 'Date format',
                    'show_smilies' => 'Smilies label',
                    'show_sig'     => 'Sigs label',
                    'show_avatars' => 'Avatars label',
                    'show_img'     => 'Images label',
                    'show_img_sig' => 'Images sigs label',
                    'disp_topics'  => 'Topics per page label',
                    'disp_posts'   => 'Posts per page label',
                ])->addArguments([
                    'token' => ['id' => $this->curUser->id],
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {

            }

            $this->fIswev = $v->getErrors();
        }

        $form = [
            'action' => $this->c->Router->link('EditBoardConfig', ['id' => $this->curUser->id]),
            'hidden' => [
                'token' => $this->c->Csrf->create('EditBoardConfig', ['id' => $this->curUser->id]),
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

        $yn     = [1 => \ForkBB\__('Yes'), 0 => \ForkBB\__('No')];
        $langs  = $this->c->Func->getLangs();
        $langs  = \array_combine($langs, $langs);
        $styles = $this->c->Func->getStyles();
        $styles = \array_combine($styles, $styles);
        $timeFormat = [];
        foreach ($this->c->TIME_FORMATS as $key => $value) {
            $timeFormat[$key] = \ForkBB\dt(\time(), false, null, $value, true, true) . ($key ? '' : ' (' . \ForkBB\__('Default') . ')');
        }
        $dateFormat = [];
        foreach ($this->c->DATE_FORMATS as $key => $value) {
            $dateFormat[$key] = \ForkBB\dt(\time(), true, $value, null, false, true) . ($key ? '' : ' (' . \ForkBB\__('Default') . ')');
        }

        $form['sets'][] = [
            'id'     => 'essentials',
            'legend' => \ForkBB\__('Essentials'),
            'class'  => 'data-edit',
            'fields' => [
                'language' => [
                    'id'      => 'language',
                    'type'    => 'select',
                    'options' => $langs,
                    'value'   => $this->curUser->language,
                    'caption' => \ForkBB\__('Language'),
                ],
                'style' => [
                    'id'      => 'style',
                    'type'    => 'select',
                    'options' => $styles,
                    'value'   => $this->curUser->style,
                    'caption' => \ForkBB\__('Style'),
                ],
                'timezone' => [
                    'id'      => 'timezone',
                    'type'    => 'select',
                    'options' => [
                        '-12'   => \ForkBB\__('UTC-12:00'),
                        '-11'   => \ForkBB\__('UTC-11:00'),
                        '-10'   => \ForkBB\__('UTC-10:00'),
                        '-9.5'  => \ForkBB\__('UTC-09:30'),
                        '-9'    => \ForkBB\__('UTC-09:00'),
                        '-8.5'  => \ForkBB\__('UTC-08:30'),
                        '-8'    => \ForkBB\__('UTC-08:00'),
                        '-7'    => \ForkBB\__('UTC-07:00'),
                        '-6'    => \ForkBB\__('UTC-06:00'),
                        '-5'    => \ForkBB\__('UTC-05:00'),
                        '-4'    => \ForkBB\__('UTC-04:00'),
                        '-3.5'  => \ForkBB\__('UTC-03:30'),
                        '-3'    => \ForkBB\__('UTC-03:00'),
                        '-2'    => \ForkBB\__('UTC-02:00'),
                        '-1'    => \ForkBB\__('UTC-01:00'),
                        '0'     => \ForkBB\__('UTC'),
                        '1'     => \ForkBB\__('UTC+01:00'),
                        '2'     => \ForkBB\__('UTC+02:00'),
                        '3'     => \ForkBB\__('UTC+03:00'),
                        '3.5'   => \ForkBB\__('UTC+03:30'),
                        '4'     => \ForkBB\__('UTC+04:00'),
                        '4.5'   => \ForkBB\__('UTC+04:30'),
                        '5'     => \ForkBB\__('UTC+05:00'),
                        '5.5'   => \ForkBB\__('UTC+05:30'),
                        '5.75'  => \ForkBB\__('UTC+05:45'),
                        '6'     => \ForkBB\__('UTC+06:00'),
                        '6.5'   => \ForkBB\__('UTC+06:30'),
                        '7'     => \ForkBB\__('UTC+07:00'),
                        '8'     => \ForkBB\__('UTC+08:00'),
                        '8.75'  => \ForkBB\__('UTC+08:45'),
                        '9'     => \ForkBB\__('UTC+09:00'),
                        '9.5'   => \ForkBB\__('UTC+09:30'),
                        '10'    => \ForkBB\__('UTC+10:00'),
                        '10.5'  => \ForkBB\__('UTC+10:30'),
                        '11'    => \ForkBB\__('UTC+11:00'),
                        '11.5'  => \ForkBB\__('UTC+11:30'),
                        '12'    => \ForkBB\__('UTC+12:00'),
                        '12.75' => \ForkBB\__('UTC+12:45'),
                        '13'    => \ForkBB\__('UTC+13:00'),
                        '14'    => \ForkBB\__('UTC+14:00'),
                    ],
                    'value'   => $this->curUser->timezone,
                    'caption' => \ForkBB\__('Time zone'),
                ],
                'dst' => [
                    'id'      => 'dst',
                    'type'    => 'radio',
                    'value'   => $this->curUser->dst,
                    'values'  => $yn,
                    'caption' => \ForkBB\__('DST label'),
                    'info'    => \ForkBB\__('DST help'),
                ],
                'time_format' => [
                    'id'      => 'time_format',
                    'type'    => 'select',
                    'options' => $timeFormat,
                    'value'   => $this->curUser->time_format,
                    'caption' => \ForkBB\__('Time format'),
                ],
                'date_format' => [
                    'id'      => 'date_format',
                    'type'    => 'select',
                    'options' => $dateFormat,
                    'value'   => $this->curUser->date_format,
                    'caption' => \ForkBB\__('Date format'),
                ],

            ],
        ];
        $form['sets'][] = [
            'id'     => 'viewing-posts',
            'legend' => \ForkBB\__('Viewing posts'),
            'class'  => 'data-edit',
            'fields' => [
                'show_smilies' => [
                    'id'      => 'show_smilies',
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_smilies,
                    'values'  => $yn,
                    'caption' => \ForkBB\__('Smilies label'),
                    'info'    => \ForkBB\__('Smilies info'),
                ],
                'show_sig' => [
                    'id'      => 'show_sig',
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_sig,
                    'values'  => $yn,
                    'caption' => \ForkBB\__('Sigs label'),
                    'info'    => \ForkBB\__('Sigs info'),
                ],
                'show_avatars' => [
                    'id'      => 'show_avatars',
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_avatars,
                    'values'  => $yn,
                    'caption' => \ForkBB\__('Avatars label'),
                    'info'    => \ForkBB\__('Avatars info'),
                ],
                'show_img' => [
                    'id'      => 'show_img',
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_img,
                    'values'  => $yn,
                    'caption' => \ForkBB\__('Images label'),
                    'info'    => \ForkBB\__('Images info'),
                ],
                'show_img_sig' => [
                    'id'      => 'show_img_sig',
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_img_sig,
                    'values'  => $yn,
                    'caption' => \ForkBB\__('Images sigs label'),
                    'info'    => \ForkBB\__('Images sigs info'),
                ],
            ],
        ];
        $form['sets'][] = [
            'id'     => 'pagination',
            'legend' => \ForkBB\__('Pagination'),
            'class'  => 'data-edit',
            'fields' => [
                'disp_topics' => [
                    'id'      => 'disp_topics',
                    'type'    => 'number',
                    'min'     => 10,
                    'max'     => 50,
                    'value'   => $this->curUser->disp_topics,
                    'caption' => \ForkBB\__('Topics per page label'),
                    'info'    => \ForkBB\__('For default'),
                ],
                'disp_posts' => [
                    'id'      => 'disp_posts',
                    'type'    => 'number',
                    'min'     => 10,
                    'max'     => 50,
                    'value'   => $this->curUser->disp_posts,
                    'caption' => \ForkBB\__('Posts per page label'),
                    'info'    => \ForkBB\__('For default'),
                ],
            ],
        ];

        $crumbs = [];
        $crumbs[] = [$this->c->Router->link('EditBoardConfig', ['id' => $this->curUser->id]), \ForkBB\__('Board configuration')];

        $this->robots     = 'noindex';
        $this->crumbs     = $this->extCrumbs(...$crumbs);
        $this->fIndex     = $this->rules->my ? 'profile' : 'userlist';
        $this->nameTpl    = 'profile';
        $this->onlinePos  = 'profile-' . $this->curUser->id; // ????
        $this->title      = \ForkBB\__('%s\'s profile', $this->curUser->username);
        $this->form       = $form;
        $this->actionBtns = $this->btns('config');

        return $this;
    }

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
                    'token'     => 'token:EditUserEmail',
                    'password'  => 'required|string:trim|check_password',
                    'new_email' => 'required|string:trim,lower|email:noban,unique,flood',
                ])->addAliases([
                    'new_email' => 'New email',
                    'password'  => 'Your passphrase',
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
            'action' => $this->c->Router->link('EditUserEmail', ['id' => $this->curUser->id]),
            'hidden' => [
                'token' => $this->c->Csrf->create('EditUserEmail', ['id' => $this->curUser->id]),
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
                            'caption'   => \ForkBB\__('Your passphrase'),
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
            [$this->c->Router->link('EditUserEmail', ['id' => $this->curUser->id]), \ForkBB\__('Change email')],
            [$this->c->Router->link('EditUserProfile', ['id' => $this->curUser->id]), \ForkBB\__('Editing profile')]
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
     * Подготавливает данные для шаблона просмотра профиля
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function pass(array $args, $method)
    {
        $this->curUser = $this->c->users->load((int) $args['id']);

        if (! $this->curUser instanceof User || ($this->curUser->isUnverified && ! $this->user->isAdmMod)) {
            return $this->c->Message->message('Bad request');
        }

        $this->rules = $this->c->ProfileRules->setUser($this->curUser);

        if (! $this->rules->editPass) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('profile');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_password' => [$this, 'vCheckPassword'],
                ])->addRules([
                    'token'     => 'token:EditUserPass',
                    'password'  => 'required|string:trim|check_password',
                    'new_pass'  => 'required|string:trim,lower|password',
                ])->addAliases([
                    'new_pass'  => 'New pass',
                    'password'  => 'Your passphrase',
                ])->addArguments([
                    'token'           => ['id' => $this->curUser->id],
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
//                if (\password_verify($v->new_pass, $this->curUser->password)) {
//                    return $this->c->Redirect->page('EditUserProfile', ['id' => $this->curUser->id])->message('Email is old redirect');
//                }

                $this->curUser->password = \password_hash($v->new_pass, \PASSWORD_DEFAULT);
                $this->c->users->update($this->curUser);

                if ($this->rules->my) {
#                    $auth = $this->c->Auth;
#                    $auth->fIswev = ['s' => [\ForkBB\__('Pass updated')]];
#                    return $auth->login(['_username' => $this->curUser->username], 'GET');
                    return $this->c->Redirect->page('Login')->message('Pass updated'); // ???? нужна передача данных между скриптами не привязанная к пользователю
                } else {
                    return $this->c->Redirect->page('EditUserProfile', ['id' => $this->curUser->id])->message('Pass updated redirect');
                }
            }

            $this->fIswev = $v->getErrors();
        }

        $form = [
            'action' => $this->c->Router->link('EditUserPass', ['id' => $this->curUser->id]),
            'hidden' => [
                'token' => $this->c->Csrf->create('EditUserPass', ['id' => $this->curUser->id]),
            ],
            'sets'   => [
                [
                    'class'  => 'data-edit',
                    'fields' => [
                        'new_pass' => [
                            'id'        => 'new_pass',
                            'type'      => 'password',
                            'maxlength' => 25,
                            'caption'   => \ForkBB\__('New pass'),
                            'required'  => true,
                            'pattern'   => '^.{16,}$',
                            'info'      => \ForkBB\__('Pass format') . ' ' . \ForkBB\__('Pass info'),
                        ],
                        'password' => [
                            'id'        => 'password',
                            'type'      => 'password',
                            'caption'   => \ForkBB\__('Your passphrase'),
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
            [$this->c->Router->link('EditUserPass', ['id' => $this->curUser->id]), \ForkBB\__('Change pass')],
            [$this->c->Router->link('EditUserProfile', ['id' => $this->curUser->id]), \ForkBB\__('Editing profile')]
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
            $v->addError('Invalid passphrase');
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
        if ($isEdit && $this->rules->editPass) {
            $fields[] = [
                'id'    => 'change_pass',
                'type'  => 'link',
                'value' => \ForkBB\__('Change passphrase'),
                'href'  => $this->c->Router->link('EditUserPass', ['id' => $this->curUser->id]),
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
                    'href'    => $this->c->Router->link('SearchAction', ['action' => 'posts', 'uid' => $this->curUser->id]),
                    'title'   => \ForkBB\__('Show posts'),
                ];
                $fields['topics'] = [
                    'id'      => 'topics',
                    'class'   => 'pline',
                    'type'    => 'link',
                    'caption' => \ForkBB\__('Topics info'),
                    'value'   => $this->user->showPostCount ? \ForkBB\num($this->curUser->num_topics) : \ForkBB\__('Show topics'),
                    'href'    => $this->c->Router->link('SearchAction', ['action' => 'topics', 'uid' => $this->curUser->id]),
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
