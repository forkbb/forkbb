<?php

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Profile;

class Config extends Profile
{
    /**
     * Подготавливает данные для шаблона настройки форума
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function config(array $args, string $method): Page
    {
        if (
            false === $this->initProfile($args['id'])
            || ! $this->rules->editConfig
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('profile_other');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'to_zero' => [$this, 'vToZero'],
                ])->addRules([
                    'token'        => 'token:EditUserBoardConfig',
                    'language'     => 'required|string:trim|in:' . \implode(',', $this->c->Func->getLangs()),
                    'style'        => 'required|string:trim|in:' . \implode(',', $this->c->Func->getStyles()),
                    'timezone'     => 'required|numeric|in:-12,-11,-10,-9.5,-9,-8.5,-8,-7,-6,-5,-4,-3.5,-3,-2,-1,0,1,2,3,3.5,4,4.5,5,5.5,5.75,6,6.5,7,8,8.75,9,9.5,10,10.5,11,11.5,12,12.75,13,14',
                    'dst'          => 'required|integer|in:0,1',
                    'time_format'  => 'required|integer|in:' . \implode(',', \array_keys($this->c->TIME_FORMATS)),
                    'date_format'  => 'required|integer|in:' . \implode(',', \array_keys($this->c->DATE_FORMATS)),
                    'show_smilies' => 'required|integer|in:0,1',
                    'show_sig'     => 'required|integer|in:0,1',
                    'show_avatars' => 'required|integer|in:0,1',
                    'show_img'     => 'required|integer|in:0,1',
                    'show_img_sig' => 'required|integer|in:0,1',
                    'disp_topics'  => 'integer|min:0|max:50|to_zero',
                    'disp_posts'   => 'integer|min:0|max:50|to_zero',
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
                $data  = $v->getData();
                unset($data['token']);

                $this->curUser->replAttrs($data, true);

                $this->c->users->update($this->curUser);

                return $this->c->Redirect->page('EditUserBoardConfig', ['id' => $this->curUser->id])->message('Board configuration redirect');
            }

            $this->fIswev = $v->getErrors();
        }

        $this->crumbs     = $this->crumbs([$this->c->Router->link('EditUserBoardConfig', ['id' => $this->curUser->id]), \ForkBB\__('Board configuration')]);
        $this->form       = $this->form();
        $this->actionBtns = $this->btns('config');

        return $this;
    }

    /**
     * Преобразовывает число меньше 10 в 0
     *
     * @param Validator $v
     * @param int $value
     *
     * @return int
     */
    public function vToZero(Validator $v, $value)
    {
        return $value < 10 ? 0 : $value;
    }

    /**
     * Создает массив данных для формы
     *
     * @return array
     */
    protected function form(): array
    {
        $form = [
            'action' => $this->c->Router->link('EditUserBoardConfig', ['id' => $this->curUser->id]),
            'hidden' => [
                'token' => $this->c->Csrf->create('EditUserBoardConfig', ['id' => $this->curUser->id]),
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
        $langs  = $this->c->Func->getNameLangs();
        $styles = $this->c->Func->getStyles();
        $timeFormat = [];
        foreach ($this->c->TIME_FORMATS as $key => $value) {
            $timeFormat[$key] = \ForkBB\dt(\time(), false, null, $value, true, true) . ($key ? '' : ' (' . \ForkBB\__('Default') . ')');
        }
        $dateFormat = [];
        foreach ($this->c->DATE_FORMATS as $key => $value) {
            $dateFormat[$key] = \ForkBB\dt(\time(), true, $value, null, false, true) . ($key ? '' : ' (' . \ForkBB\__('Default') . ')');
        }

        $form['sets']['essentials'] = [
            'legend' => \ForkBB\__('Essentials'),
            'class'  => 'data-edit',
            'fields' => [
                'language' => [
                    'type'    => 'select',
                    'options' => $langs,
                    'value'   => $this->curUser->language,
                    'caption' => \ForkBB\__('Language'),
                ],
                'style' => [
                    'type'    => 'select',
                    'options' => $styles,
                    'value'   => $this->curUser->style,
                    'caption' => \ForkBB\__('Style'),
                ],
                'timezone' => [
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
                    'type'    => 'radio',
                    'value'   => $this->curUser->dst,
                    'values'  => $yn,
                    'caption' => \ForkBB\__('DST label'),
                    'info'    => \ForkBB\__('DST help'),
                ],
                'time_format' => [
                    'type'    => 'select',
                    'options' => $timeFormat,
                    'value'   => $this->curUser->time_format,
                    'caption' => \ForkBB\__('Time format'),
                ],
                'date_format' => [
                    'type'    => 'select',
                    'options' => $dateFormat,
                    'value'   => $this->curUser->date_format,
                    'caption' => \ForkBB\__('Date format'),
                ],

            ],
        ];
        $form['sets']['viewing-posts'] = [
            'legend' => \ForkBB\__('Viewing posts'),
            'class'  => 'data-edit',
            'fields' => [
                'show_smilies' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_smilies,
                    'values'  => $yn,
                    'caption' => \ForkBB\__('Smilies label'),
                    'info'    => \ForkBB\__('Smilies info'),
                ],
                'show_sig' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_sig,
                    'values'  => $yn,
                    'caption' => \ForkBB\__('Sigs label'),
                    'info'    => \ForkBB\__('Sigs info'),
                ],
                'show_avatars' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_avatars,
                    'values'  => $yn,
                    'caption' => \ForkBB\__('Avatars label'),
                    'info'    => \ForkBB\__('Avatars info'),
                ],
                'show_img' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_img,
                    'values'  => $yn,
                    'caption' => \ForkBB\__('Images label'),
                    'info'    => \ForkBB\__('Images info'),
                ],
                'show_img_sig' => [
                    'type'    => 'radio',
                    'value'   => $this->curUser->show_img_sig,
                    'values'  => $yn,
                    'caption' => \ForkBB\__('Images sigs label'),
                    'info'    => \ForkBB\__('Images sigs info'),
                ],
            ],
        ];
        $form['sets']['pagination'] = [
            'legend' => \ForkBB\__('Pagination'),
            'class'  => 'data-edit',
            'fields' => [
                'disp_topics' => [
                    'type'    => 'number',
                    'min'     => 0,
                    'max'     => 50,
                    'value'   => $this->curUser->__disp_topics,
                    'caption' => \ForkBB\__('Topics per page label'),
                    'info'    => \ForkBB\__('For default'),
                ],
                'disp_posts' => [
                    'type'    => 'number',
                    'min'     => 0,
                    'max'     => 50,
                    'value'   => $this->curUser->__disp_posts,
                    'caption' => \ForkBB\__('Posts per page label'),
                    'info'    => \ForkBB\__('For default'),
                ],
            ],
        ];

        return $form;
    }
}
