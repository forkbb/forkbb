<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Core\Image;
use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Profile;
use function \ForkBB\{__, num, size};

class Edit extends Profile
{
    /**
     * Паттерн для доступных к загрузке типов файлов
     */
    protected string $accept = 'image/*';

    /**
     * Подготавливает данные для шаблона редактирования профиля
     */
    public function edit(array $args, string $method): Page
    {
        if (
            false === $this->initProfile($args['id'])
            || ! $this->rules->editProfile
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('validator');
        $this->c->Lang->load('profile_other');

        if ('POST' === $method) {
            if ($this->rules->rename) {
                $ruleUsername = 'required|string:trim|username|noURL:1';

            } else {
                $ruleUsername = 'absent';
            }

            if ($this->rules->setTitle) {
                $ruleTitle = 'exist|string:trim|max:50|noURL';

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
                $ruleAdminNote = 'exist|string:trim|max:30';

            } else {
                $ruleAdminNote = 'absent';
            }

            if ($this->rules->editWebsite) {
                $ruleWebsite = 'exist|string:trim,empty|max:100|regex:%^https?://[^\x00-\x1F\s]+$%uD';
                $ruleSN      = 'exist|string:trim,empty|max:200|regex:%^https?://[^\x00-\x1F\s]+$%uD|check_sn';

            } else {
                $ruleWebsite = 'absent';
                $ruleSN      = 'absent';
            }

            if ($this->rules->useSignature) {
                $ruleSignature = "exist|string:trim|max:{$this->curUser->g_sig_length}|check_signature";

            } else {
                $ruleSignature = 'absent';
            }

            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_signature' => [$this, 'vCheckSignature'],
                    'check_sn'        => [$this, 'vCheckSN'],
                ])->addRules([
                    'token'         => 'token:EditUserProfile',
                    'username'      => $ruleUsername,
                    'title'         => $ruleTitle,
                    'upload_avatar' => $ruleAvatar,
                    'delete_avatar' => $ruleDelAvatar,
                    'admin_note'    => $ruleAdminNote,
                    'realname'      => 'exist|string:trim|max:40|noURL:1',
                    'gender'        => 'required|integer|in:' . FORK_GEN_NOT . ',' . FORK_GEN_MAN . ',' . FORK_GEN_FEM,
                    'location'      => 'exist|string:trim|max:30|noURL:1',
                    'email_setting' => 'required|integer|in:0,1,2',
                    'url'           => $ruleWebsite,
                    'sn_profile1'   => $ruleSN,
                    'sn_profile2'   => $ruleSN,
                    'sn_profile3'   => $ruleSN,
                    'sn_profile4'   => $ruleSN,
                    'sn_profile5'   => $ruleSN,
                    'signature'     => $ruleSignature,
                    'save'          => 'required|string',
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
                    'sn_profile1'   => ['SN profile %s', '1'],
                    'sn_profile2'   => ['SN profile %s', '2'],
                    'sn_profile3'   => ['SN profile %s', '3'],
                    'sn_profile4'   => ['SN profile %s', '4'],
                    'sn_profile5'   => ['SN profile %s', '5'],
                    'signature'     => 'Signature',
                ])->addArguments([
                    'token'             => $args,
                    'username.username' => $this->curUser,
                ])->addMessages([
                ]);

            $valid = $v->validation($_FILES + $_POST);
            $data  = $v->getData(false, ['token', 'upload_avatar', 'delete_avatar']);

            if ($valid) {
                if (
                    $v->delete_avatar
                    || $v->upload_avatar instanceof Image
                ) {
                    $this->curUser->deleteAvatar();
                }

                if ($v->upload_avatar instanceof Image) {
                    $name   = $this->c->Secury->randomPass(8);
                    $path   = $this->c->DIR_PUBLIC . "{$this->c->config->o_avatars_dir}/{$name}.(webp|jpg|png|gif)";
                    $result = $v->upload_avatar
                        ->rename(true)
                        ->rewrite(false)
                        ->setQuality($this->c->config->i_avatars_quality ?? 75)
                        ->resize($this->c->config->i_avatars_width, $this->c->config->i_avatars_height)
                        ->toFile($path, $this->c->config->i_avatars_size);

                    if (true === $result) {
                        $this->curUser->avatar = $v->upload_avatar->name() . '.' . $v->upload_avatar->ext();

                    } else {
                        $this->c->Log->warning('Profile Failed image processing', [
                            'user'    => $this->user->fLog(),
                            'curUser' => $this->curUser->fLog(),
                            'error'   => $v->upload_avatar->error(),
                        ]);
                    }
                }

                $this->curUser->replAttrs($data, true);

                $this->c->users->update($this->curUser);

                return $this->c->Redirect->page('EditUserProfile', $args)->message('Profile redirect', FORK_MESS_SUCC);

            } else {
                $this->fIswev = $v->getErrors();

                $this->curUser->replAttrs($data);
            }
        }

        $this->identifier      = ['profile', 'profile-edit'];
        $this->crumbs          = $this->crumbs(
            [
                $this->c->Router->link('EditUserProfile', $args),
                'Editing profile',
            ]
        );
        $this->form            = $this->form($args);
        $this->actionBtns      = $this->btns('edit');
        $this->profileIdSuffix = '-edit';

        return $this;
    }

    /**
     * Дополнительная проверка signature
     */
    public function vCheckSignature(Validator $v, string $signature): string
    {
        if ('' != $signature) {
            $prepare = null;

            // после цензуры текст сообщения пустой
            if ('' == $this->c->censorship->censor($signature)) {
                $v->addError('No signature after censoring');

            // количество строк
            } elseif (\substr_count($signature, "\n") >= $this->curUser->g_sig_lines) {
                $v->addError('Signature has too many lines');

            // проверка парсером
            } else {
                $prepare   = true;
                $signature = $this->c->Parser->prepare($signature, true); //????

                foreach ($this->c->Parser->getErrors([], [], true) as $error) {
                    $prepare = false;
                    $v->addError($error);
                }
            }

            // текст сообщения только заглавными буквами
            if (
                true === $prepare
                && ! $this->user->isAdmin
                && 1 !== $this->c->config->b_sig_all_caps
            ) {
                $text = $this->c->Parser->getText();

                if (
                    \preg_match('%\p{Lu}%u', $text)
                    && ! \preg_match('%\p{Ll}%u', $text)
                ) {
                    $v->addError('All caps signature');
                }
            }
        }

        return $signature;
    }

    /**
     * Дополнительная проверка соц.сети
     */
    public function vCheckSN(Validator $v, string $url): string
    {
        if (empty($url)) {
            return '';
        }

        $this->snsGetTitle(''); // загрузка $this->snsArray

        foreach ($this->snsArray as $type => $cur) {
            foreach ($cur['urls'] as $pattern => $repl) {
                $result = \preg_replace('%^https?://(?:www\.)?' . $pattern . '.*$%u', $repl, $url, 1, $count);

                if (
                    1 === $count
                    && \is_string($result)
                ) {
                    return "{$type}\n{$result}";
                }
            }
        }

        return '';
    }

    /**
     * Создает массив данных для формы
     */
    protected function form(array $args): array
    {
        $form = [
            'action' => $this->c->Router->link('EditUserProfile', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('EditUserProfile', $args),
            ],
            'sets'   => [],
            'btns'   => [
                'save' => [
                    'type'  => 'submit',
                    'value' => __('Save changes'),
                ],
            ],
        ];

        // имя, титул и аватара
        $fields = [];

        if ($this->rules->rename) {
            $fields['username'] = [
                'type'      => 'text',
                'minlength' => $this->c->USERNAME['min'],
                'maxlength' => $this->user->isAdmin ? '190' : $this->c->USERNAME['max'],
                'caption'   => 'Username',
                'required'  => true,
                'pattern'   => $this->c->USERNAME['jsPattern'],
                'value'     => $this->curUser->username,
            ];

        } else {
            $fields['username'] = [
                'class'   => ['pline'],
                'type'    => 'str',
                'caption' => 'Username',
                'value'   => $this->curUser->username,
            ];
        }

        if ($this->rules->deleteMyProfile) {
            $fields['delete_profile'] = [
                'type'    => 'link',
                'value'   => __('Delete my profile'),
                'title'   => __('Delete my profile'),
                'href'    => $this->c->Router->link('DeleteUserProfile', $args),
            ];
        }

        if ($this->rules->changeGroup) {
            $fields['group'] = [
                'type'    => 'link',
                'caption' => 'Group',
                'value'   => $this->curUser->group_id ? $this->curUser->g_title : __('Change user group'),
                'title'   => __('Change user group'),
                'href'    => $this->linkChangeGroup(),
            ];

        } else {
            $fields['group'] = [
                'class'   => ['pline'],
                'type'    => 'str',
                'caption' => 'Group',
                'value'   => $this->curUser->group_id ? $this->curUser->g_title : '-',
            ];
        }

        if ($this->rules->confModer) {
            $fields['configure-moderator'] = [
                'type'    => 'link',
                'value'   => __('Configure moderator rights'),
                'href'    => $this->c->Router->link('EditUserModeration', $args),
            ];
        }

        if ($this->rules->setTitle) {
            $fields['title'] = [
                'type'      => 'text',
                'maxlength' => '50',
                'caption'   => 'Title',
                'value'     => $this->curUser->title,
                'help'      => 'Leave blank',
            ];

        } else {
            $fields['title'] = [
                'class'   => ['pline'],
                'type'    => 'str',
                'caption' => 'Title',
                'value'   => $this->curUser->title(),
            ];
        }

        if ($this->rules->editPass) {
            $fields['change_pass'] = [
                'type'  => 'link',
                'value' => __('Change passphrase'),
                'href'  => $this->c->Router->link('EditUserPass', $args),
            ];
        }

        if ($this->rules->configureOAuth) {
            $fields['configure_oauth'] = [
                'type'  => 'link',
                'value' => __('Configure OAuth'),
                'href'  => $this->c->Router->link('EditUserOAuth', $args),
            ];
        }

        if ($this->rules->useAvatar) {
            if (! $this->curUser->avatar) {
                $fields['avatar'] = [
                    'class'   => ['pline'],
                    'type'    => 'str',
                    'caption' => 'Avatar',
                    'value'   => __('Not uploaded'),
                ];

            } elseif ($this->curUser->avatar) {
                $fields['avatar'] = [
                    'type'    => 'yield',
                    'caption' => 'Avatar',
                    'value'   => 'avatar',
                ];
            }

            $form['enctype'] = 'multipart/form-data';
            $form['maxfsz']  = $this->c->Files->maxImgSize();

            if ($this->curUser->avatar) {
                $fields['delete_avatar'] = [
                    'type'    => 'checkbox',
                    'label'   => 'Delete avatar',
                    'checked' => false,
                ];
            }

            $fields['upload_avatar'] = [
                'type'    => 'file',
                'caption' => 'New avatar',
                'help'    => ['New avatar info',
                    num($this->c->config->i_avatars_width),
                    num($this->c->config->i_avatars_height),
                    num($this->c->config->i_avatars_size),
                    size($this->c->config->i_avatars_size)
                ],
                'accept'  => $this->accept,
            ];
        }
        $form['sets']['header'] = [
            'class'  => ['header-edit'],
            'legend' => 'Essentials',
            'fields' => $fields,
        ];

        // примечание администрации
        if ($this->user->isAdmMod) {
            $form['sets']['note'] = [
                'class'  => ['data-edit'],
                'legend' => 'Admin note',
                'fields' => [
                    'admin_note' => [
                        'type'      => 'text',
                        'maxlength' => '30',
                        'caption'   => 'Admin note',
                        'value'     => $this->curUser->admin_note,
                        'help'      => 'Admin note help',
                    ],
                ],
            ];
        }

        // личное
        $fields = [];
        $fields['realname'] = [
            'type'      => 'text',
            'maxlength' => '40',
            'caption'   => 'Realname',
            'value'     => $this->curUser->realname,
        ];
        $genders = [
            FORK_GEN_NOT => __('Do not display'),
            FORK_GEN_MAN => __('Male'),
            FORK_GEN_FEM => __('Female'),
        ];
        $fields['gender'] = [
            'class'   => ['block'],
            'type'    => 'radio',
            'value'   => $this->curUser->gender,
            'values'  => $genders,
            'caption' => 'Gender',
        ];
        $fields['location'] = [
            'type'      => 'text',
            'maxlength' => 30,
            'caption'   => 'Location',
            'value'     => $this->curUser->location,
        ];

        if ($this->rules->editAboutMe) {
            $fields['change_about'] = [
                'type'    => 'link',
                'caption' => 'About me',
                'value'   => __('To change about me'),
                'href'    => $this->c->Router->link('EditUserAboutMe', $args),
            ];
        }

        $form['sets']['personal'] = [
            'class'  => ['data-edit'],
            'legend' => 'Personal information',
            'fields' => $fields,
        ];

        // контактная информация
        $fields = [];

        if ($this->rules->viewOEmail) {
            $fields['open-email'] = [
                'class'   => ['pline'],
                'type'    => 'str',
                'caption' => 'Email info',
                'value'   => $this->curUser->censorEmail,
            ];
        }

        if ($this->rules->editEmail) {
            $fields['change_email'] = [
                'type'  => 'link',
                'value' => __($this->rules->confirmEmail ? 'To confirm/change email' : 'To change email'),
                'href'  => $this->c->Router->link('EditUserEmail', $args),
            ];
        }

        $fields['email_setting'] = [
            'class'   => ['block'],
            'type'    => 'radio',
            'value'   => $this->curUser->email_setting,
            'values'  => [
                0 => __('Display e-mail label'),
                1 => __('Hide allow form label'),
                2 => __('Hide both label'),
            ],
            'caption' => 'Email settings label',
        ];

        if ($this->rules->editWebsite) {
            $fields['url'] = [
                'id'        => 'website',
                'type'      => 'text',
                'maxlength' => '100',
                'caption'   => 'Website',
                'value'     => $this->curUser->url,
            ];

            for ($i = 1; $i < 6; $i++) {
                $f = "sn_profile{$i}";

                if ($this->curUser->$f) {
                    list(, $v) = \explode("\n", $this->curUser->$f, 2);

                } else {
                    $v = '';
                }

                $fields[$f] = [
                    'type'      => 'text',
                    'maxlength' => '200',
                    'caption'   => ['SN profile %s', "{$i}"],
                    'value'     => $v,
                ];
            }

        } elseif ($this->rules->viewWebsite) {
            if ($this->curUser->url) {
                $fields['url'] = [
                    'id'      => 'website',
                    'class'   => ['pline'],
                    'type'    => 'link',
                    'caption' => 'Website',
                    'value'   => $this->curUser->censorUrl,
                    'href'    => $this->curUser->censorUrl,
                ];
            }

            for ($i = 1; $i < 6; $i++) {
                $f = "sn_profile{$i}";

                if ($this->curUser->$f) {
                    list(, $v) = \explode("\n", $this->curUser->$f, 2);

                    $fields[$f] = [
                        'id'      => 'website',
                        'class'   => ['pline'],
                        'type'    => 'link',
                        'caption' => ['SN profile %s', "{$i}"],
                        'value'   => $v,
                        'href'    => $v,
                    ];
                }
            }
        }

        $form['sets']['contacts'] = [
            'class'  => ['data-edit'],
            'legend' => 'Contact details',
            'fields' => $fields,
        ];

        // подпись
        if ($this->rules->useSignature) {
            $fields = [];
            $fields['signature'] = [
                'type'      => 'textarea',
                'value'     => $this->curUser->signature,
                'caption'   => 'Signature',
                'help'      => ['Sig max size', num($this->curUser->g_sig_length), num($this->curUser->g_sig_lines)],
                'maxlength' => $this->curUser->g_sig_length,
            ];
            $form['sets']['signature'] = [
                'class'  => ['data-edit'],
                'legend' => 'Signature',
                'fields' => $fields,
            ];
        }

        return $form;
    }

    /**
     * Пересчитывает количество сообщений и тем
     */
    public function recalc(array $args, string $method): Page
    {
        if (
            ! $this->c->Csrf->verify($args['token'], 'EditUserRecalc', $args)
            || false === $this->initProfile($args['id'])
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->users->updateCountPosts($this->curUser);
        $this->c->users->updateCountTopics($this->curUser);

        return $this->c->Redirect
            ->url($this->curUser->link)
            ->message(['Recalculate %s redirect', $this->curUser->username], FORK_MESS_SUCC);
    }
}
