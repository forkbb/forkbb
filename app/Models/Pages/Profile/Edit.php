<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Core\Image;
use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Profile;
use ForkBB\Models\User\User;
use function \ForkBB\__;
use function \ForkBB\num;
use function \ForkBB\size;

class Edit extends Profile
{
    /**
     * Паттерн для доступных к загрузке типов файлов
     * @var string
     */
    protected $accept = 'image/*';

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
                $ruleWebsite = 'exist|string:trim,empty|max:100|regex:%^(?:https?:)?//[^\x00-\x1F\s]+$%iu';
            } else {
                $ruleWebsite = 'absent';
            }

            if ($this->rules->useSignature) {
                $ruleSignature = "exist|string:trim|max:{$this->curUser->g_sig_length}|check_signature";
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
                    'realname'      => 'exist|string:trim|max:40|noURL:1',
                    'gender'        => 'required|integer|in:0,1,2',
                    'location'      => 'exist|string:trim|max:30|noURL:1',
                    'email_setting' => 'required|integer|in:0,1,2',
                    'url'           => $ruleWebsite,
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
                    'signature'     => 'Signature',
                ])->addArguments([
                    'token'             => $args,
                    'username.username' => $this->curUser,
                ])->addMessages([
                ]);

            $valid = $v->validation($_FILES + $_POST);
            $data  = $v->getData();
            unset($data['token'], $data['upload_avatar'], $data['delete_avatar']);

            if ($valid) {
                if (
                    $v->delete_avatar
                    || $v->upload_avatar instanceof Image
                ) {
                    $this->curUser->deleteAvatar();
                }

                if ($v->upload_avatar instanceof Image) {
                    $name   = $this->c->Secury->randomPass(8);
                    $path   = $this->c->DIR_PUBLIC . "{$this->c->config->o_avatars_dir}/{$name}.(jpg|png|gif|webp)";
                    $result = $v->upload_avatar
                        ->rename(true)
                        ->rewrite(false)
                        ->resize($this->c->config->i_avatars_width, $this->c->config->i_avatars_height)
                        ->toFile($path, $this->c->config->i_avatars_size);

                    if (true === $result) {
                        $this->curUser->avatar = $v->upload_avatar->name() . '.' . $v->upload_avatar->ext();
                    }
                }

                $this->curUser->replAttrs($data, true);

                $this->c->users->update($this->curUser);

                return $this->c->Redirect->page('EditUserProfile', $args)->message('Profile redirect');
            } else {
                $this->fIswev = $v->getErrors();

                $this->curUser->replAttrs($data);
            }
        }

        $this->crumbs     = $this->crumbs(
            [
                $this->c->Router->link('EditUserProfile', $args),
                __('Editing profile'),
            ]
        );
        $this->form       = $this->form($args);
        $this->actionBtns = $this->btns('edit');

        return $this;
    }

    /**
     * Дополнительная проверка signature
     */
    public function vCheckSignature(Validator $v, string $signature): string
    {
        if ('' !== $signature) {
            $prepare = null;

            // после цензуры текст сообщения пустой
            if ('' === $this->c->censorship->censor($signature)) {
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
                'maxlength' => '25',
                'caption'   => 'Username',
                'required'  => true,
                'pattern'   => '^.{2,25}$',
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
                'title'   => __('Configure moderator rights'),
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
            $form['hidden']['MAX_FILE_SIZE'] = $this->c->Files->maxImgSize();

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
                'accept' => $this->accept,
            ];
        }
        $form['sets']['header'] = [
            'class'  => ['header-edit'],
#            'legend' => 'Options',
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
            0 => __('Do not display'),
            1 => __('Male'),
            2 => __('Female'),
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
        } elseif (
            $this->rules->viewWebsite
            && $this->curUser->url
        ) {
            $fields['url'] = [
                'id'      => 'website',
                'class'   => ['pline'],
                'type'    => 'link',
                'caption' => 'Website',
                'value'   => $this->curUser->censorUrl,
                'href'    => $this->curUser->censorUrl,
            ];
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
                'type'    => 'textarea',
                'value'   => $this->curUser->signature,
                'caption' => 'Signature',
                'help'    => ['Sig max size', num($this->curUser->g_sig_length), num($this->curUser->g_sig_lines)],
            ];
            $form['sets']['signature'] = [
                'class'  => ['data-edit'],
                'legend' => 'Signature',
                'fields' => $fields,
            ];
        }

        return $form;
    }
}
