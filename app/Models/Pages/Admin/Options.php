<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\Config\Model as Config;
use function \ForkBB\__;

class Options extends Admin
{
    /**
     * Редактирование натроек форума
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function edit(array $args, string $method): Page
    {
        $this->c->Lang->load('admin_options');
        $this->c->Lang->load('profile_other');

        $config = clone $this->c->config;

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_timeout' => [$this, 'vCheckTimeout'],
                    'check_dir'     => [$this, 'vCheckDir'],
                    'check_empty'   => [$this, 'vCheckEmpty'],
                ])->addRules([
                    'token'                   => 'token:AdminOptions',
                    'o_board_title'           => 'required|string:trim|max:255',
                    'o_board_desc'            => 'string:trim|max:65000 bytes',
                    'o_default_timezone'      => 'required|string:trim|in:-12,-11,-10,-9.5,-9,-8.5,-8,-7,-6,-5,-4,-3.5,-3,-2,-1,0,1,2,3,3.5,4,4.5,5,5.5,5.75,6,6.5,7,8,8.75,9,9.5,10,10.5,11,11.5,12,12.75,13,14',
                    'o_default_dst'           => 'required|integer|in:0,1',
                    'o_default_lang'          => 'required|string:trim|in:' . \implode(',', $this->c->Func->getLangs()),
                    'o_default_style'         => 'required|string:trim|in:' . \implode(',', $this->c->Func->getStyles()),
                    'o_time_format'           => 'required|string:trim|max:25',
                    'o_date_format'           => 'required|string:trim|max:25',
                    'o_timeout_visit'         => 'required|integer|min:0|max:99999',
                    'o_timeout_online'        => 'required|integer|min:0|max:99999|check_timeout',
                    'o_redirect_delay'        => 'required|integer|min:0|max:99999',
                    'o_show_user_info'        => 'required|integer|in:0,1',
                    'o_show_post_count'       => 'required|integer|in:0,1',
                    'o_smilies'               => 'required|integer|in:0,1',
                    'o_smilies_sig'           => 'required|integer|in:0,1',
                    'o_make_links'            => 'required|integer|in:0,1',
                    'o_topic_review'          => 'required|integer|min:0|max:50',
                    'o_disp_topics_default'   => 'required|integer|min:10|max:50',
                    'o_disp_posts_default'    => 'required|integer|min:10|max:50',
                    'o_disp_users'            => 'required|integer|min:10|max:50',
                    'o_quote_depth'           => 'required|integer|min:0|max:9',
                    'o_quickpost'             => 'required|integer|in:0,1',
                    'o_users_online'          => 'required|integer|in:0,1',
                    'o_signatures'            => 'required|integer|in:0,1',
                    'o_show_dot'              => 'required|integer|in:0,1',
                    'o_topic_views'           => 'required|integer|in:0,1',
                    'o_quickjump'             => 'required|integer|in:0,1',
                    'o_search_all_forums'     => 'required|integer|in:0,1',
                    'o_additional_navlinks'   => 'string:trim|max:65000 bytes',
                    'o_feed_type'             => 'required|integer|in:0,1,2',
                    'o_feed_ttl'              => 'required|integer|in:0,5,15,30,60',
                    'o_report_method'         => 'required|integer|in:0,1,2',
                    'o_mailing_list'          => 'string:trim|max:65000 bytes', // ???? проверка списка email
                    'o_avatars'               => 'required|integer|in:0,1',
                    'o_avatars_dir'           => 'required|string:trim|max:255|check_dir',
                    'o_avatars_width'         => 'required|integer|min:50|max:999',
                    'o_avatars_height'        => 'required|integer|min:50|max:999',
                    'o_avatars_size'          => 'required|integer|min:0|max:9999999',
                    'o_admin_email'           => 'required|string:trim|email',
                    'o_webmaster_email'       => 'required|string:trim|email',
                    'o_forum_subscriptions'   => 'required|integer|in:0,1',
                    'o_topic_subscriptions'   => 'required|integer|in:0,1',
                    'o_smtp_host'             => 'string:trim|max:255',
                    'o_smtp_user'             => 'string:trim|max:255',
                    'o_smtp_pass'             => 'string:trim|max:255', //??????
                    'changeSmtpPassword'      => 'checkbox',
                    'o_smtp_ssl'              => 'required|integer|in:0,1',
                    'o_regs_allow'            => 'required|integer|in:0,1',
                    'o_regs_verify'           => 'required|integer|in:0,1',
                    'o_regs_report'           => 'required|integer|in:0,1',
                    'o_rules'                 => 'required|integer|in:0,1|check_empty:o_rules_message',
                    'o_rules_message'         => 'string:trim|max:65000 bytes',
                    'o_default_email_setting' => 'required|integer|in:0,1,2',
                    'o_announcement'          => 'required|integer|in:0,1|check_empty:o_announcement_message',
                    'o_announcement_message'  => 'string:trim|max:65000 bytes',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                    'o_board_title'     => 'Must enter title message',
                    'o_admin_email'     => 'Invalid e-mail message',
                    'o_webmaster_email' => 'Invalid webmaster e-mail message',
                ]);

            $valid = $v->validation($_POST);
            $data  = $v->getData();

            if (empty($data['changeSmtpPassword'])) {
                unset($data['o_smtp_pass']);
            }
            unset($data['changeSmtpPassword'], $data['token']);

            foreach ($data as $attr => $value) {
                $config->$attr = $value;
            }

            if ($valid) {
                $config->save();

                return $this->c->Redirect->page('AdminOptions')->message('Options updated redirect');
            }

            $this->fIswev  = $v->getErrors();
        }

        $this->aIndex    = 'options';
        $this->nameTpl   = 'admin/form';
        $this->form      = $this->formEdit($config);
        $this->titleForm = __('Options head');
        $this->classForm = 'editoptions';

        return $this;
    }

    /**
     * Дополнительная проверка времени online
     *
     * @param Validator $v
     * @param int $timeout
     *
     * @return int
     */
    public function vCheckTimeout(Validator $v, $timeout)
    {
        if ($timeout >= $v->o_timeout_visit) {
            $v->addError('Timeout error message');
        }

        return $timeout;
    }

    /**
     * Дополнительная проверка каталога аватарок
     *
     * @param Validator $v
     * @param string $dir
     *
     * @return string
     */
    public function vCheckDir(Validator $v, $dir)
    {
        $dir = '/' . \trim(\str_replace(['\\', '.', '//', ':'], ['/', '', '', ''], $dir), '/'); //?????

        return $dir;
    }

    /**
     * Дополнительная проверка на пустоту другого поля
     *
     * @param Validator $v
     * @param int $value
     * @param string $attr
     *
     * @return int
     */
    public function vCheckEmpty(Validator $v, $value, $attr)
    {
        if (
            0 !== $value
            && 0 === \strlen($v->$attr)
        ) {
            $value = 0;
        }

        return $value;
    }

    /**
     * Формирует данные для формы
     *
     * @param Config $config
     *
     * @return array
     */
    protected function formEdit(Config $config): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminOptions'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminOptions'),
            ],
            'sets'   => [],
            'btns'   => [
                'save'  => [
                    'type'      => 'submit',
                    'value'     => __('Save changes'),
                    'accesskey' => 's',
                ],
            ],
        ];

        $yn     = [1 => __('Yes'), 0 => __('No')];
        $langs  = $this->c->Func->getNameLangs();
        $styles = $this->c->Func->getStyles();

        $form['sets']['essentials'] = [
            'legend' => __('Essentials subhead'),
            'fields' => [
                'o_board_title' => [
                    'type'      => 'text',
                    'maxlength' => 255,
                    'value'     => $config->o_board_title,
                    'caption'   => __('Board title label'),
                    'info'      => __('Board title help'),
                    'required'  => true,
#                   'autofocus' => true,
                ],
                'o_board_desc' => [
                    'type'    => 'textarea',
                    'value'   => $config->o_board_desc,
                    'caption' => __('Board desc label'),
                    'info'    => __('Board desc help'),
                ],
                'o_default_timezone' => [
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
                    'value'   => $config->o_default_timezone,
                    'caption' => __('Timezone label'),
                    'info'    => __('Timezone help'),
                ],
                'o_default_dst' => [
                    'type'    => 'radio',
                    'value'   => $config->o_default_dst,
                    'values'  => $yn,
                    'caption' => __('DST label'),
                    'info'    => __('DST help'),
                ],
                'o_default_lang' => [
                    'type'    => 'select',
                    'options' => $langs,
                    'value'   => $config->o_default_lang,
                    'caption' => __('Language label'),
                    'info'    => __('Language help'),
                ],
                'o_default_style' => [
                    'type'    => 'select',
                    'options' => $styles,
                    'value'   => $config->o_default_style,
                    'caption' => __('Default style label'),
                    'info'    => __('Default style help'),
                ],
            ],
        ];

        $timestamp = \time() + ($this->user->timezone + $this->user->dst) * 3600;
        $time      = \ForkBB\dt($timestamp, false, $config->o_date_format, $config->o_time_format, true, true);
        $date      = \ForkBB\dt($timestamp, true, $config->o_date_format, $config->o_time_format, false, true);

        $form['sets']['timeouts'] = [
            'legend' => __('Timeouts subhead'),
            'fields' => [
                'o_time_format' => [
                    'type'      => 'text',
                    'maxlength' => 25,
                    'value'     => $config->o_time_format,
                    'caption'   => __('Time format label'),
                    'info'      => __('Time format help', $time),
                    'required'  => true,
                ],
                'o_date_format' => [
                    'type'      => 'text',
                    'maxlength' => 25,
                    'value'     => $config->o_date_format,
                    'caption'   => __('Date format label'),
                    'info'      => __('Date format help', $date),
                    'required'  => true,
                ],
                'o_timeout_visit' => [
                    'type'    => 'number',
                    'min'     => 0,
                    'max'     => 99999,
                    'value'   => $config->o_timeout_visit,
                    'caption' => __('Visit timeout label'),
                    'info'    => __('Visit timeout help'),
                ],
                'o_timeout_online' => [
                    'type'    => 'number',
                    'min'     => 0,
                    'max'     => 99999,
                    'value'   => $config->o_timeout_online,
                    'caption' => __('Online timeout label'),
                    'info'    => __('Online timeout help'),
                ],
                'o_redirect_delay' => [
                    'type'    => 'number',
                    'min'     => 0,
                    'max'     => 99999,
                    'value'   => $config->o_redirect_delay,
                    'caption' => __('Redirect time label'),
                    'info'    => __('Redirect time help'),
                ],
            ],
        ];

        $form['sets']['display'] = [
            'legend' => __('Display subhead'),
            'fields' => [
                'o_show_user_info' => [
                    'type'    => 'radio',
                    'value'   => $config->o_show_user_info,
                    'values'  => $yn,
                    'caption' => __('Info in posts label'),
                    'info'    => __('Info in posts help'),
                ],
                'o_show_post_count' => [
                    'type'    => 'radio',
                    'value'   => $config->o_show_post_count,
                    'values'  => $yn,
                    'caption' => __('Post count label'),
                    'info'    => __('Post count help'),
                ],
                'o_smilies' => [
                    'type'    => 'radio',
                    'value'   => $config->o_smilies,
                    'values'  => $yn,
                    'caption' => __('Smilies label'),
                    'info'    => __('Smilies help'),
                ],
                'o_smilies_sig' => [
                    'type'    => 'radio',
                    'value'   => $config->o_smilies_sig,
                    'values'  => $yn,
                    'caption' => __('Smilies sigs label'),
                    'info'    => __('Smilies sigs help'),
                ],
                'o_make_links' => [
                    'type'    => 'radio',
                    'value'   => $config->o_make_links,
                    'values'  => $yn,
                    'caption' => __('Clickable links label'),
                    'info'    => __('Clickable links help'),
                ],
                'o_disp_topics_default' => [
                    'type'    => 'number',
                    'min'     => 10,
                    'max'     => 50,
                    'value'   => $config->o_disp_topics_default,
                    'caption' => __('Topics per page label'),
                    'info'    => __('Topics per page help'),
                ],
                'o_disp_posts_default' => [
                    'type'    => 'number',
                    'min'     => 10,
                    'max'     => 50,
                    'value'   => $config->o_disp_posts_default,
                    'caption' => __('Posts per page label'),
                    'info'    => __('Posts per page help'),
                ],
                'o_disp_users' => [
                    'type'    => 'number',
                    'min'     => 10,
                    'max'     => 50,
                    'value'   => $config->o_disp_users,
                    'caption' => __('Users per page label'),
                    'info'    => __('Users per page help'),
                ],
                'o_topic_review' => [
                    'type'    => 'number',
                    'min'     => 0,
                    'max'     => 50,
                    'value'   => $config->o_topic_review,
                    'caption' => __('Topic review label'),
                    'info'    => __('Topic review help'),
                ],
                'o_quote_depth' => [
                    'type'    => 'number',
                    'min'     => 0,
                    'max'     => 9,
                    'value'   => $config->o_quote_depth,
                    'caption' => __('Quote depth label'),
                    'info'    => __('Quote depth help'),
                ],
            ],
        ];

        $form['sets']['features'] = [
            'legend' => __('Features subhead'),
            'fields' => [
                'o_quickpost' => [
                    'type'    => 'radio',
                    'value'   => $config->o_quickpost,
                    'values'  => $yn,
                    'caption' => __('Quick post label'),
                    'info'    => __('Quick post help'),
                ],
                'o_users_online' => [
                    'type'    => 'radio',
                    'value'   => $config->o_users_online,
                    'values'  => $yn,
                    'caption' => __('Users online label'),
                    'info'    => __('Users online help'),
                ],
                'o_signatures' => [
                    'type'    => 'radio',
                    'value'   => $config->o_signatures,
                    'values'  => $yn,
                    'caption' => __('Signatures label'),
                    'info'    => __('Signatures help'),
                ],
                'o_show_dot' => [
                    'type'    => 'radio',
                    'value'   => $config->o_show_dot,
                    'values'  => $yn,
                    'caption' => __('User has posted label'),
                    'info'    => __('User has posted help'),
                ],
                'o_topic_views' => [
                    'type'    => 'radio',
                    'value'   => $config->o_topic_views,
                    'values'  => $yn,
                    'caption' => __('Topic views label'),
                    'info'    => __('Topic views help'),
                ],
                'o_quickjump' => [
                    'type'    => 'radio',
                    'value'   => $config->o_quickjump,
                    'values'  => $yn,
                    'caption' => __('Quick jump label'),
                    'info'    => __('Quick jump help'),
                ],
                'o_search_all_forums' => [ //????
                    'type'    => 'radio',
                    'value'   => $config->o_search_all_forums,
                    'values'  => $yn,
                    'caption' => __('Search all label'),
                    'info'    => __('Search all help'),
                ],
                'o_additional_navlinks' => [
                    'type'    => 'textarea',
                    'value'   => $config->o_additional_navlinks,
                    'caption' => __('Menu items label'),
                    'info'    => __('Menu items help'),
                ],

            ],
        ];

        $form['sets']['feed'] = [
            'legend' => __('Feed subhead'),
            'fields' => [
                'o_feed_type' => [
                    'type'    => 'radio',
                    'value'   => $config->o_feed_typet,
                    'values'  => [
                        0 => __('No feeds'),
                        1 => __('RSS'),
                        2 => __('Atom'),
                    ],
                    'caption' => __('Default feed label'),
                    'info'    => __('Default feed help'),
                ],
                'o_feed_ttl' => [
                    'type'    => 'select',
                    'options' => [
                        0  => __('No cache'),
                        5  => __('%d Minutes', 5),
                        15 => __('%d Minutes', 15),
                        30 => __('%d Minutes', 30),
                        60 => __('%d Minutes', 60),
                    ],
                    'value'   => $config->o_feed_ttl,
                    'caption' => __('Feed TTL label'),
                    'info'    => __('Feed TTL help'),
                ],

            ],
        ];

        $form['sets']['reports'] = [
            'legend' => __('Reports subhead'),
            'fields' => [
                'o_report_method' => [
                    'type'    => 'radio',
                    'value'   => $config->o_report_method,
                    'values'  => [
                        0 => __('Internal'),
                        1 => __('By e-mail'),
                        2 => __('Both'),
                    ],
                    'caption' => __('Reporting method label'),
                    'info'    => __('Reporting method help'),
                ],
                'o_mailing_list' => [
                    'type'    => 'textarea',
                    'value'   => $config->o_mailing_list,
                    'caption' => __('Mailing list label'),
                    'info'    => __('Mailing list help'),
                ],
            ],
        ];

        $form['sets']['avatars'] = [
            'legend' => __('Avatars subhead'),
            'fields' => [
                'o_avatars' => [
                    'type'    => 'radio',
                    'value'   => $config->o_avatars,
                    'values'  => $yn,
                    'caption' => __('Use avatars label'),
                    'info'    => __('Use avatars help'),
                ],
                'o_avatars_dir' => [ //????
                    'type'      => 'text',
                    'maxlength' => 255,
                    'value'     => $config->o_avatars_dir,
                    'caption'   => __('Upload directory label'),
                    'info'      => __('Upload directory help'),
                    'required'  => true,
                ],
                'o_avatars_width' => [
                    'type'    => 'number',
                    'min'     => 50,
                    'max'     => 999,
                    'value'   => $config->o_avatars_width,
                    'caption' => __('Max width label'),
                    'info'    => __('Max width help'),
                ],
                'o_avatars_height' => [
                    'type'    => 'number',
                    'min'     => 50,
                    'max'     => 999,
                    'value'   => $config->o_avatars_height,
                    'caption' => __('Max height label'),
                    'info'    => __('Max height help'),
                ],
                'o_avatars_size' => [
                    'type'    => 'number',
                    'min'     => 0,
                    'max'     => 9999999,
                    'value'   => $config->o_avatars_size,
                    'caption' => __('Max size label'),
                    'info'    => __('Max size help'),
                ],
            ],
        ];

        $form['sets']['email'] = [
            'legend' => __('E-mail subhead'),
            'fields' => [
                'o_admin_email' => [
                    'type'      => 'text',
                    'maxlength' => 80,
                    'value'     => $config->o_admin_email,
                    'caption'   => __('Admin e-mail label'),
                    'info'      => __('Admin e-mail help'),
                    'required'  => true,
                    'pattern'   => '.+@.+',
                ],
                'o_webmaster_email' => [
                    'type'      => 'text',
                    'maxlength' => 80,
                    'value'     => $config->o_webmaster_email,
                    'caption'   => __('Webmaster e-mail label'),
                    'info'      => __('Webmaster e-mail help'),
                    'required'  => true,
                    'pattern'   => '.+@.+',
                ],
                'o_forum_subscriptions' => [
                    'type'    => 'radio',
                    'value'   => $config->o_forum_subscriptions,
                    'values'  => $yn,
                    'caption' => __('Forum subscriptions label'),
                    'info'    => __('Forum subscriptions help'),
                ],
                'o_topic_subscriptions' => [
                    'type'    => 'radio',
                    'value'   => $config->o_topic_subscriptions,
                    'values'  => $yn,
                    'caption' => __('Topic subscriptions label'),
                    'info'    => __('Topic subscriptions help'),
                ],
                'o_smtp_host' => [
                    'type'      => 'text',
                    'maxlength' => 255,
                    'value'     => $config->o_smtp_host,
                    'caption'   => __('SMTP address label'),
                    'info'      => __('SMTP address help'),
                ],
                'o_smtp_user' => [
                    'type'      => 'text',
                    'maxlength' => 255,
                    'value'     => $config->o_smtp_user,
                    'caption'   => __('SMTP username label'),
                    'info'      => __('SMTP username help'),
                ],
                'o_smtp_pass' => [
                    'type'      => 'password',
                    'maxlength' => 255,
                    'value'     => $config->o_smtp_pass ? '          ' : null,
                    'caption'   => __('SMTP password label'),
                    'info'      => __('SMTP password help'),
                ],
                'changeSmtpPassword' => [
                    'type'    => 'checkbox',
                    'value'   => '1',
                    'caption' => '',
                    'label'   => __('SMTP change password help'),
                ],
                'o_smtp_ssl' => [
                    'type'    => 'radio',
                    'value'   => $config->o_smtp_ssl,
                    'values'  => $yn,
                    'caption' => __('SMTP SSL label'),
                    'info'    => __('SMTP SSL help'),
                ],
            ],
        ];

        $form['sets']['registration'] = [
            'legend' => __('Registration subhead'),
            'fields' => [
                'o_regs_allow' => [
                    'type'    => 'radio',
                    'value'   => $config->o_regs_allow,
                    'values'  => $yn,
                    'caption' => __('Allow new label'),
                    'info'    => __('Allow new help'),
                ],
                'o_regs_verify' => [
                    'type'    => 'radio',
                    'value'   => $config->o_regs_verify,
                    'values'  => $yn,
                    'caption' => __('Verify label'),
                    'info'    => __('Verify help'),
                ],
                'o_regs_report' => [
                    'type'    => 'radio',
                    'value'   => $config->o_regs_report,
                    'values'  => $yn,
                    'caption' => __('Report new label'),
                    'info'    => __('Report new help'),
                ],
                'o_rules' => [
                    'type'    => 'radio',
                    'value'   => $config->o_rules,
                    'values'  => $yn,
                    'caption' => __('Use rules label'),
                    'info'    => __('Use rules help'),
                ],
                'o_rules_message' => [
                    'type'    => 'textarea',
                    'value'   => $config->o_rules_message,
                    'caption' => __('Rules label'),
                    'info'    => __('Rules help'),
                ],
                'o_default_email_setting' => [
                    'class'   => 'block',
                    'type'    => 'radio',
                    'value'   => $config->o_default_email_setting,
                    'values'  => [
                        0 => __('Display e-mail label'),
                        1 => __('Hide allow form label'),
                        2 => __('Hide both label'),
                    ],
                    'caption' => __('E-mail default label'),
                    'info'    => __('E-mail default help'),
                ],
            ],
        ];

        $form['sets']['announcement'] = [
            'legend' => __('Announcement subhead'),
            'fields' => [
                'o_announcement' => [
                    'type'    => 'radio',
                    'value'   => $config->o_announcement,
                    'values'  => $yn,
                    'caption' => __('Display announcement label'),
                    'info'    => __('Display announcement help'),
                ],
                'o_announcement_message' => [
                    'type'    => 'textarea',
                    'value'   => $config->o_announcement_message,
                    'caption' => __('Announcement message label'),
                    'info'    => __('Announcement message help'),
                ],

            ],
        ];

        return $form;
    }
}
