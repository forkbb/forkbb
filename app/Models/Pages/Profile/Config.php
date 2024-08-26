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
use ForkBB\Models\Pages\TimeZoneTrait;
use DateTimeZone;
// use IntlDateFormatter;
use ResourceBundle;
use function \ForkBB\{__, dt};

class Config extends Profile
{
    use TimeZoneTrait;

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
                    'language'      => [
                        'required',
                        'string:trim',
                        'in' => $this->c->Func->getLangs(),
                    ],
                    'style'         => [
                        'required',
                        'string:trim',
                        'in' => $this->c->Func->getStyles(),
                    ],
                    'timezone'      => [
                        'required',
                        'string:trim',
                        'in' => DateTimeZone::listIdentifiers(),
                    ],
                    'locale'      => [
                        'required',
                        'string:trim',
                        'in' => \array_keys($this->localeOptions),
                    ],
                    'time_format'   => 'required|integer|in:1,2,3,4',
                    'date_format'   => 'required|integer|in:1,2,3,4',
                    'page_scroll'   => 'required|integer|in:-2,-1,0,1,2',
                    'show_smilies'  => 'required|integer|in:0,1',
                    'show_sig'      => 'required|integer|in:0,1',
                    'show_avatars'  => 'required|integer|in:0,1',
                    'show_img'      => 'required|integer|in:0,1',
                    'show_img_sig'  => 'required|integer|in:0,1',
                    'show_reaction' => 'required|integer|in:0,1',
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
                    'locale'        => 'Locale',
                    'time_format'   => 'Time format',
                    'date_format'   => 'Date format',
                    'page_scroll'   => 'Page scroll label',
                    'show_smilies'  => 'Smilies label',
                    'show_sig'      => 'Sigs label',
                    'show_avatars'  => 'Avatars label',
                    'show_img'      => 'Images label',
                    'show_img_sig'  => 'Images sigs label',
                    'show_reaction' => 'Reaction label',
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
                $data = $v->getData(false, ['token']);

                $this->curUser->replAttrs($data, true);

                if ($this->curUser->isModified('ip_check_type')) {
                    $this->c->users->updateLoginIpCache($this->curUser); // ????
                }

                $this->c->users->update($this->curUser);

                return $this->c->Redirect->page('EditUserBoardConfig', $args)->message('Board configuration redirect', FORK_MESS_SUCC);
            }

            $this->fIswev = $v->getErrors();
        }

        $this->identifier      = ['profile', 'profile-config'];
        $this->crumbs          = $this->crumbs(
            [
                $this->c->Router->link('EditUserBoardConfig', $args),
                'Board configuration',
            ]
        );
        $this->form            = $this->form($args);
        $this->actionBtns      = $this->btns('config');
        $this->profileIdSuffix = '-config';

        return $this;
    }

    /**
     * Преобразовывает число меньше 10 в 0
     */
    public function vToZero(Validator $v, int $value): int
    {
        return $value < 10 ? 0 : $value;
    }

    /**
     * Возвращает список локалей из текущего языка
     */
    protected function getlocaleOptions(): array
    {
        $curLocales = \array_flip(ResourceBundle::getLocales(''));
        $allLocales = include $this->c->DIR_CONFIG . '/locales.php';
        $result     = [];
        $keys       = \explode('|', __('lang_locales'));

        \array_unshift($keys, $this->curUser->locale);
        \array_push($keys, 'en_001');

        foreach ($keys as $key) {
            if (isset($curLocales[$key], $allLocales[$key])) {
//                $dt           = (new IntlDateFormatter($key, IntlDateFormatter::LONG, IntlDateFormatter::LONG, $this->curUser->timezone))->format(\time());
                $result[$key] = $allLocales[$key]; // . " [{$dt}]";
            }
        }

        return $result;
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

        foreach ([1, 2, 3, 4] as $key) {
            $timeFormat[$key] = dt(\time(), 0, $key, true);
        }

        $dateFormat = [];

        foreach ([1, 2, 3, 4] as $key) {
            $dateFormat[$key] = dt(\time(), $key, 0, true);
        }

        $form['sets']['essentials'] = [
            'legend' => 'Essentials',
            'class'  => ['data-edit'],
            'fields' => [
                'language' => [
                    'type'    => 'select',
                    'options' => $langs,
                    'value'   => $this->curUser->language,
                    'caption' => 'Language',
                ],
                'style' => [
                    'type'    => 'select',
                    'options' => $styles,
                    'value'   => $this->curUser->style,
                    'caption' => 'Style',
                ],
                'timezone' => [
                    'type'    => 'select',
                    'options' => $this->createTimeZoneOptions(),
                    'value'   => $this->curUser->timezone,
                    'caption' => 'Time zone',
                ],
                'locale' => [
                    'type'    => 'select',
                    'options' => $this->localeOptions,
                    'value'   => $this->curUser->locale,
                    'caption' => 'Locale',
                    'help'    => 'Locale info',
                ],
                'time_format' => [
                    'type'    => 'select',
                    'options' => $timeFormat,
                    'value'   => $this->curUser->time_format,
                    'caption' => 'Time format',
                ],
                'date_format' => [
                    'type'    => 'select',
                    'options' => $dateFormat,
                    'value'   => $this->curUser->date_format,
                    'caption' => 'Date format',
                ],
                'page_scroll' => [
                    'type'    => 'select',
                    'options' => [
                        -2 => __('Past the header and announce (no animation)'),
                        -1 => __('Past the header (no animation)'),
                        0  => __('Not active'),
                        1  => __('Past the header (smooth)'),
                        2  => __('Past the header and announce (smooth)'),
                    ],
                    'value'   => $this->curUser->page_scroll,
                    'caption' => 'Page scroll label',
                    'help'    => 'Page scroll info',
                ],
            ],
        ];
        $form['sets']['viewing-posts'] = [
            'legend' => 'Viewing posts',
            'class'  => ['data-edit'],
            'fields' => [
                'show_smilies' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_smilies,
                    'values'  => $yn,
                    'caption' => 'Smilies label',
                    'help'    => 'Smilies info',
                ],
                'show_sig' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_sig,
                    'values'  => $yn,
                    'caption' => 'Sigs label',
                    'help'    => 'Sigs info',
                ],
                'show_avatars' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_avatars,
                    'values'  => $yn,
                    'caption' => 'Avatars label',
                    'help'    => 'Avatars info',
                ],
                'show_img' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_img,
                    'values'  => $yn,
                    'caption' => 'Images label',
                    'help'    => 'Images info',
                ],
                'show_img_sig' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_img_sig,
                    'values'  => $yn,
                    'caption' => 'Images sigs label',
                    'help'    => 'Images sigs info',
                ],
                'show_reaction' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_reaction,
                    'values'  => $yn,
                    'caption' => 'Reaction label',
                    'help'    => 'Reaction info',
                ],
            ],
        ];
        $form['sets']['pagination'] = [
            'legend' => 'Pagination',
            'class'  => ['data-edit'],
            'fields' => [
                'disp_topics' => [
                    'type'    => 'number',
                    'min'     => '0',
                    'max'     => '50',
                    'value'   => $this->curUser->__disp_topics,
                    'caption' => 'Topics per page label',
                    'help'    => 'For default',
                ],
                'disp_posts' => [
                    'type'    => 'number',
                    'min'     => '0',
                    'max'     => '50',
                    'value'   => $this->curUser->__disp_posts,
                    'caption' => 'Posts per page label',
                    'help'    => 'For default',
                ],
            ],
        ];
        $form['sets']['security'] = [
            'legend' => 'Security',
            'class'  => ['data-edit'],
            'fields' => [
                'ip_check_type' => [
                    'type'     => 'select',
                    'options'  => [
                        '0' => __('Disable check'),
                        '1' => __('Not strict check'),
                        '2' => __('Strict check'),
                    ],
                    'value'    => $this->curUser->ip_check_type,
                    'caption'  => 'IP check',
                    'help'     => 'IP check info',
                    'disabled' => $this->rules->editIpCheckType ? null : true,
                ],
            ],
        ];

        if ($this->rules->viewSubscription) { // ???? модераторы?
            $form['sets']['subscriptions'] = [
                'legend' => 'Subscription options',
                'class'  => ['data-edit'],
                'fields' => [
                    'notify_with_post' => [
                        'type'    => 'radio',
                        'value'   => $this->curUser->notify_with_post,
                        'values'  => $yn,
                        'caption' => 'Notify label',
                        'help'    => 'Notify info',
                    ],
                    'auto_notify' => [
                        'type'    => 'radio',
                        'value'   => $this->curUser->auto_notify,
                        'values'  => $yn,
                        'caption' => 'Auto notify label',
                        'help'    => 'Auto notify info',
                    ],
                ],
            ];
        }

        if ($this->rules->configureSearch) {
            $form['sets']['search'] = [
                'legend' => 'Search options',
                'class'  => ['data-edit'],
                'fields' => [
                    'search_config' => [
                        'type'  => 'link',
                        'value' => __('Set up search'),
                        'title' => __('Set up search'),
                        'href'  => $this->c->Router->link('EditUserSearch', $args),
                    ],
                ],
            ];
        }

        return $form;
    }
}
