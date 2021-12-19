<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\Config\Config;
use function \ForkBB\__;

class Options extends Admin
{
    /**
     * Редактирование натроек форума
     */
    public function edit(array $args, string $method): Page
    {
        $this->c->Lang->load('validator');
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
                    'o_board_desc'            => 'string:trim|max:65000 bytes|html',
                    'o_default_timezone'      => 'required|string:trim|in:-12,-11,-10,-9.5,-9,-8.5,-8,-7,-6,-5,-4,-3.5,-3,-2,-1,0,1,2,3,3.5,4,4.5,5,5.5,5.75,6,6.5,7,8,8.75,9,9.5,10,10.5,11,11.5,12,12.75,13,14',
                    'o_default_dst'           => 'required|integer|in:0,1',
                    'o_default_lang'          => 'required|string:trim|in:' . \implode(',', $this->c->Func->getLangs()),
                    'o_default_style'         => 'required|string:trim|in:' . \implode(',', $this->c->Func->getStyles()),
                    'i_timeout_visit'         => 'required|integer|min:0|max:99999',
                    'i_timeout_online'        => 'required|integer|min:0|max:99999|check_timeout',
                    'i_redirect_delay'        => 'required|integer|min:0|max:99999',
                    'b_show_user_info'        => 'required|integer|in:0,1',
                    'b_show_post_count'       => 'required|integer|in:0,1',
                    'i_topic_review'          => 'required|integer|min:0|max:50',
                    'i_disp_topics_default'   => 'required|integer|min:10|max:50',
                    'i_disp_posts_default'    => 'required|integer|min:10|max:50',
                    'i_disp_users'            => 'required|integer|min:10|max:50',
                    'b_quickpost'             => 'required|integer|in:0,1',
                    'b_users_online'          => 'required|integer|in:0,1',
                    'o_show_dot'              => 'required|integer|in:0,1',
                    'o_topic_views'           => 'required|integer|in:0,1',
                    'o_quickjump'             => 'required|integer|in:0,1',
                    'o_search_all_forums'     => 'required|integer|in:0,1',
                    'o_additional_navlinks'   => 'string:trim|max:65000 bytes',
                    'i_feed_type'             => 'required|integer|in:0,1,2',
                    'i_feed_ttl'              => 'required|integer|in:0,5,15,30,60',
                    'i_report_method'         => 'required|integer|in:0,1,2',
                    'o_mailing_list'          => 'string:trim|max:65000 bytes', // ???? проверка списка email
                    'o_avatars'               => 'required|integer|in:0,1',
                    'o_avatars_dir'           => 'required|string:trim|max:255|check_dir',
                    'i_avatars_width'         => 'required|integer|min:50|max:999',
                    'i_avatars_height'        => 'required|integer|min:50|max:999',
                    'i_avatars_size'          => 'required|integer|min:0|max:9999999',
                    'o_admin_email'           => 'required|string:trim|email',
                    'o_webmaster_email'       => 'required|string:trim|email',
                    'o_forum_subscriptions'   => 'required|integer|in:0,1',
                    'o_topic_subscriptions'   => 'required|integer|in:0,1',
                    'i_email_max_recipients'  => 'required|integer|min:1|max:99999',
                    'o_smtp_host'             => 'string:trim|max:255',
                    'o_smtp_user'             => 'string:trim|max:255',
                    'o_smtp_pass'             => 'string:trim|max:255',
                    'changeSmtpPassword'      => 'checkbox',
                    'o_smtp_ssl'              => 'required|integer|in:0,1',
                    'o_regs_allow'            => 'required|integer|in:0,1',
                    'o_regs_verify'           => 'required|integer|in:0,1',
                    'o_regs_report'           => 'required|integer|in:0,1',
                    'o_rules'                 => 'required|integer|in:0,1|check_empty:o_rules_message',
                    'o_rules_message'         => 'string:trim|max:65000 bytes|html',
                    'i_default_email_setting' => 'required|integer|in:0,1,2',
                    'o_announcement'          => 'required|integer|in:0,1|check_empty:o_announcement_message',
                    'o_announcement_message'  => 'string:trim|max:65000 bytes|html',
                    'p_message_all_caps'      => 'required|integer|in:0,1',
                    'p_subject_all_caps'      => 'required|integer|in:0,1',
                    'p_force_guest_email'     => 'required|integer|in:0,1',
                    'p_sig_all_caps'          => 'required|integer|in:0,1',
                    'b_poll_enabled'          => 'required|integer|in:0,1',
                    'i_poll_max_questions'    => 'required|integer|min:1|max:99',
                    'i_poll_max_fields'       => 'required|integer|min:2|max:99',
                    'i_poll_time'             => 'required|integer|min:0|max:999999',
                    'i_poll_term'             => 'required|integer|min:0|max:99',
                    'b_poll_guest'            => 'required|integer|in:0,1',
                    'b_pm'                    => 'required|integer|in:0,1',
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
        $this->titleForm = 'Options head';
        $this->classForm = ['editoptions'];

        return $this;
    }

    /**
     * Дополнительная проверка времени online
     */
    public function vCheckTimeout(Validator $v, $timeout)
    {
        if ($timeout >= $v->i_timeout_visit) {
            $v->addError('Timeout error message');
        }

        return $timeout;
    }

    /**
     * Дополнительная проверка каталога аватарок
     */
    public function vCheckDir(Validator $v, $dir)
    {
        $dir = '/' . \trim(\str_replace(['\\', '.', '//', ':'], ['/', '', '', ''], $dir), '/');

        if (! \is_dir($this->c->DIR_PUBLIC . $dir)) {
            $v->addError('The folder for uploading avatars is incorrectly');
        } elseif (! \is_writable($this->c->DIR_PUBLIC . $dir)) {
            $v->addError('For PHP, it is forbidden to write in the folder for uploading avatars');
        }

        return $dir;
    }

    /**
     * Дополнительная проверка на пустоту другого поля
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
                    'type'  => 'submit',
                    'value' => __('Save changes'),
                ],
            ],
        ];

        $yn     = [1 => __('Yes'), 0 => __('No')];
        $langs  = $this->c->Func->getNameLangs();
        $styles = $this->c->Func->getStyles();

        $form['sets']['essentials'] = [
            'legend' => 'Essentials subhead',
            'fields' => [
                'o_board_title' => [
                    'type'      => 'text',
                    'maxlength' => '255',
                    'value'     => $config->o_board_title,
                    'caption'   => 'Board title label',
                    'help'      => 'Board title help',
                    'required'  => true,
#                   'autofocus' => true,
                ],
                'o_board_desc' => [
                    'type'    => 'textarea',
                    'value'   => $config->o_board_desc,
                    'caption' => 'Board desc label',
                    'help'    => 'Board desc help',
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
                    'caption' => 'Timezone label',
                    'help'    => 'Timezone help',
                ],
                'o_default_dst' => [
                    'type'    => 'radio',
                    'value'   => $config->o_default_dst,
                    'values'  => $yn,
                    'caption' => 'DST label',
                    'help'    => 'DST help',
                ],
                'o_default_lang' => [
                    'type'    => 'select',
                    'options' => $langs,
                    'value'   => $config->o_default_lang,
                    'caption' => 'Language label',
                    'help'    => 'Language help',
                ],
                'o_default_style' => [
                    'type'    => 'select',
                    'options' => $styles,
                    'value'   => $config->o_default_style,
                    'caption' => 'Default style label',
                    'help'    => 'Default style help',
                ],
            ],
        ];

        $timestamp = \time() + ($this->user->timezone + $this->user->dst) * 3600;

        $form['sets']['timeouts'] = [
            'legend' => 'Timeouts subhead',
            'fields' => [
                'i_timeout_visit' => [
                    'type'    => 'number',
                    'min'     => '0',
                    'max'     => '99999',
                    'value'   => $config->i_timeout_visit,
                    'caption' => 'Visit timeout label',
                    'help'    => 'Visit timeout help',
                ],
                'i_timeout_online' => [
                    'type'    => 'number',
                    'min'     => '0',
                    'max'     => '99999',
                    'value'   => $config->i_timeout_online,
                    'caption' => 'Online timeout label',
                    'help'    => 'Online timeout help',
                ],
                'i_redirect_delay' => [
                    'type'    => 'number',
                    'min'     => '0',
                    'max'     => '99999',
                    'value'   => $config->i_redirect_delay,
                    'caption' => 'Redirect time label',
                    'help'    => 'Redirect time help',
                ],
            ],
        ];

        $form['sets']['display'] = [
            'legend' => 'Display subhead',
            'fields' => [
                'b_show_user_info' => [
                    'type'    => 'radio',
                    'value'   => $config->b_show_user_info,
                    'values'  => $yn,
                    'caption' => 'Info in posts label',
                    'help'    => 'Info in posts help',
                ],
                'b_show_post_count' => [
                    'type'    => 'radio',
                    'value'   => $config->b_show_post_count,
                    'values'  => $yn,
                    'caption' => 'Post count label',
                    'help'    => 'Post count help',
                ],
                'i_disp_topics_default' => [
                    'type'    => 'number',
                    'min'     => '10',
                    'max'     => '50',
                    'value'   => $config->i_disp_topics_default,
                    'caption' => 'Topics per page label',
                    'help'    => 'Topics per page help',
                ],
                'i_disp_posts_default' => [
                    'type'    => 'number',
                    'min'     => '10',
                    'max'     => '50',
                    'value'   => $config->i_disp_posts_default,
                    'caption' => 'Posts per page label',
                    'help'    => 'Posts per page help',
                ],
                'i_disp_users' => [
                    'type'    => 'number',
                    'min'     => '10',
                    'max'     => '50',
                    'value'   => $config->i_disp_users,
                    'caption' => 'Users per page label',
                    'help'    => 'Users per page help',
                ],
                'i_topic_review' => [
                    'type'    => 'number',
                    'min'     => '0',
                    'max'     => '50',
                    'value'   => $config->i_topic_review,
                    'caption' => 'Topic review label',
                    'help'    => 'Topic review help',
                ],
                'p_message_all_caps' => [
                    'type'    => 'radio',
                    'value'   => $config->p_message_all_caps,
                    'values'  => $yn,
                    'caption' => 'All caps message label',
                    'help'    => 'All caps message help',
                ],
                'p_subject_all_caps' => [
                    'type'    => 'radio',
                    'value'   => $config->p_subject_all_caps,
                    'values'  => $yn,
                    'caption' => 'All caps subject label',
                    'help'    => 'All caps subject help',
                ],
                'p_sig_all_caps' => [
                    'type'    => 'radio',
                    'value'   => $config->p_sig_all_caps,
                    'values'  => $yn,
                    'caption' => 'All caps sigs label',
                    'help'    => 'All caps sigs help',
                ],
                'p_force_guest_email' => [
                    'type'    => 'radio',
                    'value'   => $config->p_force_guest_email,
                    'values'  => $yn,
                    'caption' => 'Require e-mail label',
                    'help'    => 'Require e-mail help',
                ],
            ],
        ];

        $form['sets']['features'] = [
            'legend' => 'Features subhead',
            'fields' => [
                'b_quickpost' => [
                    'type'    => 'radio',
                    'value'   => $config->b_quickpost,
                    'values'  => $yn,
                    'caption' => 'Quick post label',
                    'help'    => 'Quick post help',
                ],
                'b_users_online' => [
                    'type'    => 'radio',
                    'value'   => $config->b_users_online,
                    'values'  => $yn,
                    'caption' => 'Users online label',
                    'help'    => 'Users online help',
                ],
                'o_show_dot' => [
                    'type'    => 'radio',
                    'value'   => $config->o_show_dot,
                    'values'  => $yn,
                    'caption' => 'User has posted label',
                    'help'    => 'User has posted help',
                ],
                'o_topic_views' => [
                    'type'    => 'radio',
                    'value'   => $config->o_topic_views,
                    'values'  => $yn,
                    'caption' => 'Topic views label',
                    'help'    => 'Topic views help',
                ],
                'o_quickjump' => [
                    'type'    => 'radio',
                    'value'   => $config->o_quickjump,
                    'values'  => $yn,
                    'caption' => 'Quick jump label',
                    'help'    => 'Quick jump help',
                ],
                'o_search_all_forums' => [ //????
                    'type'    => 'radio',
                    'value'   => $config->o_search_all_forums,
                    'values'  => $yn,
                    'caption' => 'Search all label',
                    'help'    => 'Search all help',
                ],
                'o_additional_navlinks' => [
                    'type'    => 'textarea',
                    'value'   => $config->o_additional_navlinks,
                    'caption' => 'Menu items label',
                    'help'    => 'Menu items help',
                ],

            ],
        ];

        $form['sets']['feed'] = [
            'legend' => 'Feed subhead',
            'fields' => [
                'i_feed_type' => [
                    'type'    => 'radio',
                    'value'   => $config->i_feed_type,
                    'values'  => [
                        0 => __('No feeds'),
                        1 => __('RSS'),
                        2 => __('Atom'),
                    ],
                    'caption' => 'Default feed label',
                    'help'    => 'Default feed help',
                ],
                'i_feed_ttl' => [
                    'type'    => 'select',
                    'options' => [
                        0  => __('No cache'),
                        5  => __(['%d Minutes', 5]),
                        15 => __(['%d Minutes', 15]),
                        30 => __(['%d Minutes', 30]),
                        60 => __(['%d Minutes', 60]),
                    ],
                    'value'   => $config->i_feed_ttl,
                    'caption' => 'Feed TTL label',
                    'help'    => 'Feed TTL help',
                ],

            ],
        ];

        $form['sets']['reports'] = [
            'legend' => 'Reports subhead',
            'fields' => [
                'i_report_method' => [
                    'type'    => 'radio',
                    'value'   => $config->i_report_method,
                    'values'  => [
                        0 => __('Internal'),
                        1 => __('By e-mail'),
                        2 => __('Both'),
                    ],
                    'caption' => 'Reporting method label',
                    'help'    => 'Reporting method help',
                ],
                'o_mailing_list' => [
                    'type'    => 'textarea',
                    'value'   => $config->o_mailing_list,
                    'caption' => 'Mailing list label',
                    'help'    => 'Mailing list help',
                ],
            ],
        ];

        $form['sets']['avatars'] = [
            'legend' => 'Avatars subhead',
            'fields' => [
                'o_avatars' => [
                    'type'    => 'radio',
                    'value'   => $config->o_avatars,
                    'values'  => $yn,
                    'caption' => 'Use avatars label',
                    'help'    => 'Use avatars help',
                ],
                'o_avatars_dir' => [ //????
                    'type'      => 'text',
                    'maxlength' => '255',
                    'value'     => $config->o_avatars_dir,
                    'caption'   => 'Upload directory label',
                    'help'      => ['Upload directory help', $this->c->PUBLIC_URL],
                    'required'  => true,
                ],
                'i_avatars_width' => [
                    'type'    => 'number',
                    'min'     => '50',
                    'max'     => '999',
                    'value'   => $config->i_avatars_width,
                    'caption' => 'Max width label',
                    'help'    => 'Max width help',
                ],
                'i_avatars_height' => [
                    'type'    => 'number',
                    'min'     => '50',
                    'max'     => '999',
                    'value'   => $config->i_avatars_height,
                    'caption' => 'Max height label',
                    'help'    => 'Max height help',
                ],
                'i_avatars_size' => [
                    'type'    => 'number',
                    'min'     => '0',
                    'max'     => '9999999',
                    'value'   => $config->i_avatars_size,
                    'caption' => 'Max size label',
                    'help'    => 'Max size help',
                ],
            ],
        ];

        $form['sets']['email'] = [
            'legend' => 'E-mail subhead',
            'fields' => [
                'o_admin_email' => [
                    'type'      => 'text',
                    'maxlength' => '80',
                    'value'     => $config->o_admin_email,
                    'caption'   => 'Admin e-mail label',
                    'help'      => 'Admin e-mail help',
                    'required'  => true,
                    'pattern'   => '.+@.+',
                ],
                'o_webmaster_email' => [
                    'type'      => 'text',
                    'maxlength' => '80',
                    'value'     => $config->o_webmaster_email,
                    'caption'   => 'Webmaster e-mail label',
                    'help'      => 'Webmaster e-mail help',
                    'required'  => true,
                    'pattern'   => '.+@.+',
                ],
                'o_forum_subscriptions' => [
                    'type'    => 'radio',
                    'value'   => $config->o_forum_subscriptions,
                    'values'  => $yn,
                    'caption' => 'Forum subscriptions label',
                    'help'    => 'Forum subscriptions help',
                ],
                'o_topic_subscriptions' => [
                    'type'    => 'radio',
                    'value'   => $config->o_topic_subscriptions,
                    'values'  => $yn,
                    'caption' => 'Topic subscriptions label',
                    'help'    => 'Topic subscriptions help',
                ],
                'i_email_max_recipients' => [
                    'type'    => 'number',
                    'min'     => '1',
                    'max'     => '99999',
                    'value'   => $config->i_email_max_recipients,
                    'caption' => 'Email max recipients label',
                    'help'    => 'Email max recipients help',
                ],
                'o_smtp_host' => [
                    'type'      => 'text',
                    'maxlength' => '255',
                    'value'     => $config->o_smtp_host,
                    'caption'   => 'SMTP address label',
                    'help'      => 'SMTP address help',
                ],
                'o_smtp_user' => [
                    'type'      => 'text',
                    'maxlength' => '255',
                    'value'     => $config->o_smtp_user,
                    'caption'   => 'SMTP username label',
                    'help'      => 'SMTP username help',
                ],
                'o_smtp_pass' => [
                    'type'      => 'password',
                    'maxlength' => '255',
                    'value'     => $config->o_smtp_pass ? '          ' : null,
                    'caption'   => 'SMTP password label',
                    'help'      => 'SMTP password help',
                ],
                'changeSmtpPassword' => [
                    'type'    => 'checkbox',
                    'caption' => '',
                    'label'   => 'SMTP change password help',
                ],
                'o_smtp_ssl' => [
                    'type'    => 'radio',
                    'value'   => $config->o_smtp_ssl,
                    'values'  => $yn,
                    'caption' => 'SMTP SSL label',
                    'help'    => 'SMTP SSL help',
                ],
            ],
        ];

        $form['sets']['registration'] = [
            'legend' => 'Registration subhead',
            'fields' => [
                'o_regs_allow' => [
                    'type'    => 'radio',
                    'value'   => $config->o_regs_allow,
                    'values'  => $yn,
                    'caption' => 'Allow new label',
                    'help'    => 'Allow new help',
                ],
                'o_regs_verify' => [
                    'type'    => 'radio',
                    'value'   => $config->o_regs_verify,
                    'values'  => $yn,
                    'caption' => 'Verify label',
                    'help'    => 'Verify help',
                ],
                'o_regs_report' => [
                    'type'    => 'radio',
                    'value'   => $config->o_regs_report,
                    'values'  => $yn,
                    'caption' => 'Report new label',
                    'help'    => 'Report new help',
                ],
                'o_rules' => [
                    'type'    => 'radio',
                    'value'   => $config->o_rules,
                    'values'  => $yn,
                    'caption' => 'Use rules label',
                    'help'    => 'Use rules help',
                ],
                'o_rules_message' => [
                    'type'    => 'textarea',
                    'value'   => $config->o_rules_message,
                    'caption' => 'Rules label',
                    'help'    => 'Rules help',
                ],
                'i_default_email_setting' => [
                    'class'   => ['block'],
                    'type'    => 'radio',
                    'value'   => $config->i_default_email_setting,
                    'values'  => [
                        0 => __('Display e-mail label'),
                        1 => __('Hide allow form label'),
                        2 => __('Hide both label'),
                    ],
                    'caption' => 'E-mail default label',
                    'help'    => 'E-mail default help',
                ],
            ],
        ];

        $form['sets']['announcement'] = [
            'legend' => 'Announcement subhead',
            'fields' => [
                'o_announcement' => [
                    'type'    => 'radio',
                    'value'   => $config->o_announcement,
                    'values'  => $yn,
                    'caption' => 'Display announcement label',
                    'help'    => 'Display announcement help',
                ],
                'o_announcement_message' => [
                    'type'    => 'textarea',
                    'value'   => $config->o_announcement_message,
                    'caption' => 'Announcement message label',
                    'help'    => 'Announcement message help',
                ],

            ],
        ];

        $form['sets']['polls'] = [
            'legend' => 'Polls subhead',
            'fields' => [
                'b_poll_enabled' => [
                    'type'    => 'radio',
                    'value'   => $config->b_poll_enabled,
                    'values'  => $yn,
                    'caption' => 'Allow polls label',
                ],
                'i_poll_max_questions' => [
                    'type'    => 'number',
                    'min'     => '1',
                    'max'     => '99',
                    'value'   => $config->i_poll_max_questions,
                    'caption' => 'Max questions label',
                    'help'    => 'Max questions help',
                ],
                'i_poll_max_fields' => [
                    'type'    => 'number',
                    'min'     => '2',
                    'max'     => '99',
                    'value'   => $config->i_poll_max_fields,
                    'caption' => 'Max options label',
                    'help'    => 'Max options help',
                ],
                'i_poll_time' => [
                    'type'    => 'number',
                    'min'     => '0',
                    'max'     => '999999',
                    'value'   => $config->i_poll_time,
                    'caption' => 'Poll edit time label',
                    'help'    => 'Poll edit time help',
                ],
                'i_poll_term' => [
                    'type'    => 'number',
                    'min'     => '0',
                    'max'     => '99',
                    'value'   => $config->i_poll_term,
                    'caption' => 'Hidden voices label',
                    'help'    => 'Hidden voices help',
                ],
                'b_poll_guest' => [
                    'type'    => 'radio',
                    'value'   => $config->b_poll_guest,
                    'values'  => $yn,
                    'caption' => 'Result for guest label',
                    'help'    => 'Result for guest help',
                ],
            ],
        ];

        $form['sets']['pm'] = [
            'legend' => 'PM subhead',
            'fields' => [
                'b_pm' => [
                    'type'    => 'radio',
                    'value'   => $config->b_pm,
                    'values'  => $yn,
                    'caption' => 'Allow PM label',
                    'help'    => ['Allow PM help', __('User groups'), $this->c->Router->link('AdminGroups')],
                ],
            ],
        ];

        return $form;
    }
}
