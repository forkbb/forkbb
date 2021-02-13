<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Profile;
use function \ForkBB\__;

class Config extends Profile
{
    /**
     * Подготавливает данные для шаблона настройки форума
     */
    public function config(array $args, string $method): Page
    {
        if (
            false === $this->initProfile($args['id'])
            || ! $this->rules->editConfig
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('validator');
        $this->c->Lang->load('profile_other');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'to_zero' => [$this, 'vToZero'],
                ])->addRules([
                    'token'         => 'token:EditUserBoardConfig',
                    'language'      => 'required|string:trim|in:' . \implode(',', $this->c->Func->getLangs()),
                    'style'         => 'required|string:trim|in:' . \implode(',', $this->c->Func->getStyles()),
                    'timezone'      => 'required|numeric|in:-12,-11,-10,-9.5,-9,-8.5,-8,-7,-6,-5,-4,-3.5,-3,-2,-1,0,1,2,3,3.5,4,4.5,5,5.5,5.75,6,6.5,7,8,8.75,9,9.5,10,10.5,11,11.5,12,12.75,13,14',
                    'dst'           => 'required|integer|in:0,1',
                    'time_format'   => 'required|integer|in:' . \implode(',', \array_keys($this->c->TIME_FORMATS)),
                    'date_format'   => 'required|integer|in:' . \implode(',', \array_keys($this->c->DATE_FORMATS)),
                    'show_smilies'  => 'required|integer|in:0,1',
                    'show_sig'      => 'required|integer|in:0,1',
                    'show_avatars'  => 'required|integer|in:0,1',
                    'show_img'      => 'required|integer|in:0,1',
                    'show_img_sig'  => 'required|integer|in:0,1',
                    'disp_topics'   => 'integer|min:0|max:50|to_zero',
                    'disp_posts'    => 'integer|min:0|max:50|to_zero',
                    'ip_check_type' => 'required|integer|in:' . (
                        $this->rules->editIpCheckType
                        ? '0,1,2'
                        : (int) $this->curUser->ip_check_type
                    ),
                    'save'          => 'required|string',
                ])->addAliases([
                    'language'      => 'Language',
                    'style'         => 'Style',
                    'timezone'      => 'Time zone',
                    'dst'           => 'DST label',
                    'time_format'   => 'Time format',
                    'date_format'   => 'Date format',
                    'show_smilies'  => 'Smilies label',
                    'show_sig'      => 'Sigs label',
                    'show_avatars'  => 'Avatars label',
                    'show_img'      => 'Images label',
                    'show_img_sig'  => 'Images sigs label',
                    'disp_topics'   => 'Topics per page label',
                    'disp_posts'    => 'Posts per page label',
                    'ip_check_type' => 'IP check',
                ])->addArguments([
                    'token' => $args,
                ])->addMessages([
                ]);

            if ($this->rules->viewSubscription) { // ???? модераторы?
                $v = $this->c->Validator
                    ->addRules([
                        'notify_with_post' => 'required|integer|in:0,1',
                        'auto_notify'      => 'required|integer|in:0,1',
                    ])->addAliases([
                        'notify_with_post' => 'Notify label',
                        'auto_notify'      => 'Auto notify label',
                    ]);
            }

            if ($v->validation($_POST)) {
                $data  = $v->getData();
                unset($data['token']);

                $this->curUser->replAttrs($data, true);

                if ($this->curUser->isModified('ip_check_type')) {
                    $this->c->users->updateLoginIpCache($this->curUser); // ????
                }

                $this->c->users->update($this->curUser);

                return $this->c->Redirect->page('EditUserBoardConfig', $args)->message('Board configuration redirect');
            }

            $this->fIswev = $v->getErrors();
        }

        $this->crumbs     = $this->crumbs(
            [
                $this->c->Router->link('EditUserBoardConfig', $args),
                __('Board configuration'),
            ]
        );
        $this->form       = $this->form($args);
        $this->actionBtns = $this->btns('config');

        return $this;
    }

    /**
     * Преобразовывает число меньше 10 в 0
     */
    public function vToZero(Validator $v, $value)
    {
        return $value < 10 ? 0 : $value;
    }

    /**
     * Создает массив данных для формы
     */
    protected function form(array $args): array
    {
        $form = [
            'action' => $this->c->Router->link('EditUserBoardConfig', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('EditUserBoardConfig', $args),
            ],
            'sets'   => [],
            'btns'   => [
                'save' => [
                    'type'  => 'submit',
                    'value' => __('Save changes'),
                ],
            ],
        ];

        $yn     = [1 => __('Yes'), 0 => __('No')];
        $langs  = $this->c->Func->getNameLangs();
        $styles = $this->c->Func->getStyles();
        $timeFormat = [];
        foreach ($this->c->TIME_FORMATS as $key => $value) {
            $timeFormat[$key] = \ForkBB\dt(\time(), false, null, $value, true, true)
                . (
                    $key > 1
                    ? ''
                    : ' (' . __('Default for language') . ')'
                );
        }
        $dateFormat = [];
        foreach ($this->c->DATE_FORMATS as $key => $value) {
            $dateFormat[$key] = \ForkBB\dt(\time(), true, $value, null, false, true)
                . (
                    $key > 1
                    ? ''
                    : ' (' . __('Default for language') . ')'
                );
        }

        $form['sets']['essentials'] = [
            'legend' => __('Essentials'),
            'class'  => 'data-edit',
            'fields' => [
                'language' => [
                    'type'    => 'select',
                    'options' => $langs,
                    'value'   => $this->curUser->language,
                    'caption' => __('Language'),
                ],
                'style' => [
                    'type'    => 'select',
                    'options' => $styles,
                    'value'   => $this->curUser->style,
                    'caption' => __('Style'),
                ],
                'timezone' => [
                    'type'    => 'select',
                    'options' => [
                        '-12'   => __('UTC-12:00'),
                        '-11'   => __('UTC-11:00'),
                        '-10'   => __('UTC-10:00'),
                        '-9.5'  => __('UTC-09:30'),
                        '-9'    => __('UTC-09:00'),
                        '-8.5'  => __('UTC-08:30'),
                        '-8'    => __('UTC-08:00'),
                        '-7'    => __('UTC-07:00'),
                        '-6'    => __('UTC-06:00'),
                        '-5'    => __('UTC-05:00'),
                        '-4'    => __('UTC-04:00'),
                        '-3.5'  => __('UTC-03:30'),
                        '-3'    => __('UTC-03:00'),
                        '-2'    => __('UTC-02:00'),
                        '-1'    => __('UTC-01:00'),
                        '0'     => __('UTC'),
                        '1'     => __('UTC+01:00'),
                        '2'     => __('UTC+02:00'),
                        '3'     => __('UTC+03:00'),
                        '3.5'   => __('UTC+03:30'),
                        '4'     => __('UTC+04:00'),
                        '4.5'   => __('UTC+04:30'),
                        '5'     => __('UTC+05:00'),
                        '5.5'   => __('UTC+05:30'),
                        '5.75'  => __('UTC+05:45'),
                        '6'     => __('UTC+06:00'),
                        '6.5'   => __('UTC+06:30'),
                        '7'     => __('UTC+07:00'),
                        '8'     => __('UTC+08:00'),
                        '8.75'  => __('UTC+08:45'),
                        '9'     => __('UTC+09:00'),
                        '9.5'   => __('UTC+09:30'),
                        '10'    => __('UTC+10:00'),
                        '10.5'  => __('UTC+10:30'),
                        '11'    => __('UTC+11:00'),
                        '11.5'  => __('UTC+11:30'),
                        '12'    => __('UTC+12:00'),
                        '12.75' => __('UTC+12:45'),
                        '13'    => __('UTC+13:00'),
                        '14'    => __('UTC+14:00'),
                    ],
                    'value'   => $this->curUser->timezone,
                    'caption' => __('Time zone'),
                ],
                'dst' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->dst,
                    'values'  => $yn,
                    'caption' => __('DST label'),
                    'info'    => __('DST help'),
                ],
                'time_format' => [
                    'type'    => 'select',
                    'options' => $timeFormat,
                    'value'   => $this->curUser->time_format,
                    'caption' => __('Time format'),
                ],
                'date_format' => [
                    'type'    => 'select',
                    'options' => $dateFormat,
                    'value'   => $this->curUser->date_format,
                    'caption' => __('Date format'),
                ],

            ],
        ];
        $form['sets']['viewing-posts'] = [
            'legend' => __('Viewing posts'),
            'class'  => 'data-edit',
            'fields' => [
                'show_smilies' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_smilies,
                    'values'  => $yn,
                    'caption' => __('Smilies label'),
                    'info'    => __('Smilies info'),
                ],
                'show_sig' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_sig,
                    'values'  => $yn,
                    'caption' => __('Sigs label'),
                    'info'    => __('Sigs info'),
                ],
                'show_avatars' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_avatars,
                    'values'  => $yn,
                    'caption' => __('Avatars label'),
                    'info'    => __('Avatars info'),
                ],
                'show_img' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_img,
                    'values'  => $yn,
                    'caption' => __('Images label'),
                    'info'    => __('Images info'),
                ],
                'show_img_sig' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_img_sig,
                    'values'  => $yn,
                    'caption' => __('Images sigs label'),
                    'info'    => __('Images sigs info'),
                ],
            ],
        ];
        $form['sets']['pagination'] = [
            'legend' => __('Pagination'),
            'class'  => 'data-edit',
            'fields' => [
                'disp_topics' => [
                    'type'    => 'number',
                    'min'     => '0',
                    'max'     => '50',
                    'value'   => $this->curUser->__disp_topics,
                    'caption' => __('Topics per page label'),
                    'info'    => __('For default'),
                ],
                'disp_posts' => [
                    'type'    => 'number',
                    'min'     => '0',
                    'max'     => '50',
                    'value'   => $this->curUser->__disp_posts,
                    'caption' => __('Posts per page label'),
                    'info'    => __('For default'),
                ],
            ],
        ];
        $form['sets']['security'] = [
            'legend' => __('Security'),
            'class'  => 'data-edit',
            'fields' => [
                'ip_check_type' => [
                    'type'     => 'select',
                    'options'  => [
                        '0' => __('Disable check'),
                        '1' => __('Not strict check'),
                        '2' => __('Strict check'),
                    ],
                    'value'    => $this->curUser->ip_check_type,
                    'caption'  => __('IP check'),
                    'info'     => __('IP check info'),
                    'disabled' => $this->rules->editIpCheckType ? null : true,
                ],
            ],
        ];

        if ($this->rules->viewSubscription) { // ???? модераторы?
            $form['sets']['subscriptions'] = [
                'legend' => __('Subscription options'),
                'class'  => 'data-edit',
                'fields' => [
                    'notify_with_post' => [
                        'type'    => 'radio',
                        'value'   => $this->curUser->notify_with_post,
                        'values'  => $yn,
                        'caption' => __('Notify label'),
                        'info'    => __('Notify info'),
                    ],
                    'auto_notify' => [
                        'type'    => 'radio',
                        'value'   => $this->curUser->auto_notify,
                        'values'  => $yn,
                        'caption' => __('Auto notify label'),
                        'info'    => __('Auto notify info'),
                    ],
                ],
            ];
            }

        return $form;
    }
}
