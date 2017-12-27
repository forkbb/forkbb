<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Validator;
use ForkBB\Models\Pages\Admin;

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
    public function edit(array $args, $method)
    {
        $this->c->Lang->load('admin_options');

        $config = clone $this->c->config;

        if ('POST' === $method) {
            $v = $this->c->Validator->addValidators([
                'check_timeout' => [$this, 'vCheckTimeout'],
            ])->setRules([
                'token'                   => 'token:AdminOptions',
                'o_board_title'           => 'required|string:trim|max:255',
                'o_board_desc'            => 'string:trim|max:65000 bytes',
                'o_default_timezone'      => 'required|string:trim|in:-12,-11,-10,-9.5,-9,-8.5,-8,-7,-6,-5,-4,-3.5,-3,-2,-1,0,1,2,3,3.5,4,4.5,5,5.5,5.75,6,6.5,7,8,8.75,9,9.5,10,10.5,11,11.5,12,12.75,13,14',
                'o_default_dst'           => 'required|integer|in:0,1',
                'o_default_lang'          => 'required|string:trim|in:' . implode(',', $this->c->Func->getLangs()),
                'o_default_style'         => 'required|string:trim|in:' . implode(',', $this->c->Func->getStyles()),
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
                'o_indent_num_spaces'     => 'required|integer|min:0|max:99',
                'o_quote_depth'           => 'required|integer|min:0|max:9',
                'o_quickpost'             => 'required|integer|in:0,1',
                'o_users_online'          => 'required|integer|in:0,1',
                'o_censoring'             => 'required|integer|in:0,1',
                'o_signatures'            => 'required|integer|in:0,1',
                'o_show_dot'              => 'required|integer|in:0,1',
                'o_topic_views'           => 'required|integer|in:0,1',
                'o_quickjump'             => 'required|integer|in:0,1',
                'o_gzip'                  => 'required|integer|in:0,1',
                'o_search_all_forums'     => 'required|integer|in:0,1',
                'o_additional_navlinks'   => 'string:trim|max:65000 bytes',
                'o_feed_type'             => 'required|integer|in:0,1,2',
                'o_feed_ttl'              => 'required|integer|in:0,5,15,30,60',
                'o_report_method'         => 'required|integer|in:0,1,2',
                'o_mailing_list'          => 'string:trim|max:65000 bytes',
                'o_avatars'               => 'required|integer|in:0,1',
                'o_avatars_dir'           => 'required|string:trim|max:255', //?????
                'o_avatars_width'         => 'required|integer|min:50|max:999',
                'o_avatars_height'        => 'required|integer|min:50|max:999',
                'o_avatars_size'          => 'required|integer|min:0|max:9999999',
                'o_admin_email'           => 'required|string:trim|max:80|email',
                'o_webmaster_email'       => 'required|string:trim|max:80|email',
                'o_forum_subscriptions'   => 'required|integer|in:0,1',
                'o_topic_subscriptions'   => 'required|integer|in:0,1',
                'o_smtp_host'             => 'string:trim|max:255',
                'o_smtp_user'             => 'string:trim|max:255',
                'o_smtp_pass'             => 'string:trim|max:255', //??????
                'changeSmtpPassword'      => 'integer',             //??????
                'o_smtp_ssl'              => 'required|integer|in:0,1',
                'o_regs_allow'            => 'required|integer|in:0,1',
                'o_regs_verify'           => 'required|integer|in:0,1',
                'o_regs_report'           => 'required|integer|in:0,1',
                'o_rules'                 => 'required|integer|in:0,1',
                'o_rules_message'         => 'string:trim|max:65000 bytes',
                'o_default_email_setting' => 'required|integer|in:0,1,2',
                'o_announcement'          => 'required|integer|in:0,1',
                'o_announcement_message'  => 'string:trim|max:65000 bytes',
                'o_maintenance'           => 'required|integer|in:0,1',
                'o_maintenance_message'   => 'string:trim|max:65000 bytes',
            ])->setArguments([
            ])->setMessages([
                'o_board_title'     => 'Must enter title message',
                'o_admin_email'     => 'Invalid e-mail message',
                'o_webmaster_email' => 'Invalid webmaster e-mail message',
            ]);

            $valid = $v->validation($_POST);
            $data  = $v->getData();

            if (1 !== $data['changeSmtpPassword']) { //????
                unset($data['o_smtp_pass']);
            }
            unset($data['changeSmtpPassword'], $data['token']);

            foreach ($data as $attr => $value) {
                $config->$attr = $value;
            }

            if ($valid) {
                $config->save();
                
                return $this->c->Redirect->page('AdminOptions')->message(\ForkBB\__('Options updated redirect'));
            }
    
            $this->fIswev  = $v->getErrors();
        }

        $this->aIndex    = 'options';
        $this->nameTpl   = 'admin/form';
        $this->form      = $this->viewForm($config);
        $this->titleForm = \ForkBB\__('Options head');
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
     * Формирует данные для формы
     *
     * @return array
     */
    protected function viewForm($config)
    {
        $form = [
            'action' => $this->c->Router->link('AdminOptions'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminOptions'),
            ],
            'sets'   => [],
            'btns'   => [
                'submit'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Save'),
                    'accesskey' => 's',
                ],
            ],
        ];

        $yn     = [1 => \ForkBB\__('Yes'), 0 => \ForkBB\__('No')];
        $langs  = $this->c->Func->getLangs();
        $langs  = array_combine($langs, $langs);
        $styles = $this->c->Func->getStyles();
        $styles = array_combine($styles, $styles);

        $form['sets'][] = [
            'legend' => \ForkBB\__('Essentials subhead'),
            'fields' => [
                'o_board_title' => [
                    'type'      => 'text',
                    'maxlength' => 255,
                    'value'     => $config->o_board_title,
                    'title'     => \ForkBB\__('Board title label'),
                    'info'      => \ForkBB\__('Board title help'),
                    'required'  => true,
#                   'autofocus' => true,
                ],
                'o_board_desc' => [
                    'type'      => 'textarea',
                    'value'     => $config->o_board_desc,
                    'title'     => \ForkBB\__('Board desc label'),
                    'info'      => \ForkBB\__('Board desc help'),
                ],
                'o_default_timezone' => [
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
                    'value'   => $config->o_default_timezone,
                    'title'   => \ForkBB\__('Timezone label'),
                    'info'    => \ForkBB\__('Timezone help'),
                ],
                'o_default_dst' => [
                    'type'   => 'radio',
                    'value'  => $config->o_default_dst,
                    'values' => $yn,
                    'title'  => \ForkBB\__('DST label'),
                    'info'   => \ForkBB\__('DST help'),
                ],
                'o_default_lang' => [
                    'type'    => 'select',
                    'options' => $langs,
                    'value'   => $config->o_default_lang,
                    'title'   => \ForkBB\__('Language label'),
                    'info'    => \ForkBB\__('Language help'),
                ],
                'o_default_style' => [
                    'type'    => 'select',
                    'options' => $styles,
                    'value'   => $config->o_default_style,
                    'title'   => \ForkBB\__('Default style label'),
                    'info'    => \ForkBB\__('Default style help'),
                ],
            ],
        ];

        $timestamp = time() + ($this->c->user->timezone + $this->c->user->dst) * 3600;
        $time = \ForkBB\dt($timestamp, false, $config->o_date_format, $config->o_time_format, true, true);
        $date = \ForkBB\dt($timestamp, true, $config->o_date_format, $config->o_time_format, false, true);
    
        $form['sets'][] = [
            'legend' => \ForkBB\__('Timeouts subhead'),
            'fields' => [
                'o_time_format' => [
                    'type'      => 'text',
                    'maxlength' => 25,
                    'value'     => $config->o_time_format,
                    'title'     => \ForkBB\__('Time format label'),
                    'info'      => \ForkBB\__('Time format help', $time),
                    'required'  => true,
                ],
                'o_date_format' => [
                    'type'      => 'text',
                    'maxlength' => 25,
                    'value'     => $config->o_date_format,
                    'title'     => \ForkBB\__('Date format label'),
                    'info'      => \ForkBB\__('Date format help', $date),
                    'required'  => true,
                ],
                'o_timeout_visit' => [
                    'type'  => 'number',
                    'min'   => 0,
                    'max'   => 99999,
                    'value' => $config->o_timeout_visit,
                    'title' => \ForkBB\__('Visit timeout label'),
                    'info'  => \ForkBB\__('Visit timeout help'),
                ],
                'o_timeout_online' => [
                    'type'  => 'number',
                    'min'   => 0,
                    'max'   => 99999,
                    'value' => $config->o_timeout_online,
                    'title' => \ForkBB\__('Online timeout label'),
                    'info'  => \ForkBB\__('Online timeout help'),
                ],
                'o_redirect_delay' => [
                    'type'  => 'number',
                    'min'   => 0,
                    'max'   => 99999,
                    'value' => $config->o_redirect_delay,
                    'title' => \ForkBB\__('Redirect time label'),
                    'info'  => \ForkBB\__('Redirect time help'),
                ],
            ],
        ];

        $form['sets'][] = [
            'legend' => \ForkBB\__('Display subhead'),
            'fields' => [
                'o_show_user_info' => [
                    'type'   => 'radio',
                    'value'  => $config->o_show_user_info,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Info in posts label'),
                    'info'   => \ForkBB\__('Info in posts help'),
                ],
                'o_show_post_count' => [
                    'type'   => 'radio',
                    'value'  => $config->o_show_post_count,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Post count label'),
                    'info'   => \ForkBB\__('Post count help'),
                ],
                'o_smilies' => [
                    'type'   => 'radio',
                    'value'  => $config->o_smilies,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Smilies label'),
                    'info'   => \ForkBB\__('Smilies help'),
                ],
                'o_smilies_sig' => [
                    'type'   => 'radio',
                    'value'  => $config->o_smilies_sig,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Smilies sigs label'),
                    'info'   => \ForkBB\__('Smilies sigs help'),
                ],
                'o_make_links' => [
                    'type'   => 'radio',
                    'value'  => $config->o_make_links,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Clickable links label'),
                    'info'   => \ForkBB\__('Clickable links help'),
                ],
                'o_topic_review' => [
                    'type'  => 'number',
                    'min'   => 0,
                    'max'   => 50,
                    'value' => $config->o_topic_review,
                    'title' => \ForkBB\__('Topic review label'),
                    'info'  => \ForkBB\__('Topic review help'),
                ],
                'o_disp_topics_default' => [
                    'type'  => 'number',
                    'min'   => 10,
                    'max'   => 50,
                    'value' => $config->o_disp_topics_default,
                    'title' => \ForkBB\__('Topics per page label'),
                    'info'  => \ForkBB\__('Topics per page help'),
                ],
                'o_disp_posts_default' => [
                    'type'  => 'number',
                    'min'   => 10,
                    'max'   => 50,
                    'value' => $config->o_disp_posts_default,
                    'title' => \ForkBB\__('Posts per page label'),
                    'info'  => \ForkBB\__('Posts per page help'),
                ],
                'o_indent_num_spaces' => [
                    'type'  => 'number',
                    'min'   => 0,
                    'max'   => 99,
                    'value' => $config->o_indent_num_spaces,
                    'title' => \ForkBB\__('Indent label'),
                    'info'  => \ForkBB\__('Indent help'),
                ],
                'o_quote_depth' => [
                    'type'  => 'number',
                    'min'   => 0,
                    'max'   => 9,
                    'value' => $config->o_quote_depth,
                    'title' => \ForkBB\__('Quote depth label'),
                    'info'  => \ForkBB\__('Quote depth help'),
                ],
            ],
        ];

        $form['sets'][] = [
            'legend' => \ForkBB\__('Features subhead'),
            'fields' => [
                'o_quickpost' => [
                    'type'   => 'radio',
                    'value'  => $config->o_quickpost,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Quick post label'),
                    'info'   => \ForkBB\__('Quick post help'),
                ],
                'o_users_online' => [
                    'type'   => 'radio',
                    'value'  => $config->o_users_online,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Users online label'),
                    'info'   => \ForkBB\__('Users online help'),
                ],
                'o_censoring' => [
                    'type'   => 'radio',
                    'value'  => $config->o_censoring,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Censor words label'),
                    'info'   => \ForkBB\__('Censor words help', $this->c->Router->link('AdminCensoring')),
                ],
                'o_signatures' => [
                    'type'   => 'radio',
                    'value'  => $config->o_signatures,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Signatures label'),
                    'info'   => \ForkBB\__('Signatures help'),
                ],
                'o_show_dot' => [
                    'type'   => 'radio',
                    'value'  => $config->o_show_dot,
                    'values' => $yn,
                    'title'  => \ForkBB\__('User has posted label'),
                    'info'   => \ForkBB\__('User has posted help'),
                ],
                'o_topic_views' => [
                    'type'   => 'radio',
                    'value'  => $config->o_topic_views,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Topic views label'),
                    'info'   => \ForkBB\__('Topic views help'),
                ],
                'o_quickjump' => [
                    'type'   => 'radio',
                    'value'  => $config->o_quickjump,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Quick jump label'),
                    'info'   => \ForkBB\__('Quick jump help'),
                ],
                'o_gzip' => [ //????
                    'type'   => 'radio',
                    'value'  => $config->o_gzip,
                    'values' => $yn,
                    'title'  => \ForkBB\__('GZip label'),
                    'info'   => \ForkBB\__('GZip help'),
                ],
                'o_search_all_forums' => [ //????
                    'type'   => 'radio',
                    'value'  => $config->o_search_all_forums,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Search all label'),
                    'info'   => \ForkBB\__('Search all help'),
                ],
                'o_additional_navlinks' => [
                    'type'      => 'textarea',
                    'value'     => $config->o_additional_navlinks,
                    'title'     => \ForkBB\__('Menu items label'),
                    'info'      => \ForkBB\__('Menu items help'),
                ],

            ],
        ];

        $form['sets'][] = [
            'legend' => \ForkBB\__('Feed subhead'),
            'fields' => [
                'o_feed_type' => [
                    'type'   => 'radio',
                    'value'  => $config->o_feed_typet,
                    'values' => [
                        0 => \ForkBB\__('No feeds'),
                        1 => \ForkBB\__('RSS'), 
                        2 => \ForkBB\__('Atom'),
                    ],
                    'title'  => \ForkBB\__('Default feed label'),
                    'info'   => \ForkBB\__('Default feed help'),
                ],
                'o_feed_ttl' => [
                    'type'    => 'select',
                    'options' => [
                        0  => \ForkBB\__('No cache'),
                        5  => \ForkBB\__('%d Minutes', 5),
                        15 => \ForkBB\__('%d Minutes', 15),
                        30 => \ForkBB\__('%d Minutes', 30),
                        60 => \ForkBB\__('%d Minutes', 60),
                    ],
                    'value'   => $config->o_feed_ttl,
                    'title'   => \ForkBB\__('Feed TTL label'),
                    'info'    => \ForkBB\__('Feed TTL help'),
                ],

            ],
        ];

        $form['sets'][] = [
            'legend' => \ForkBB\__('Reports subhead'),
            'fields' => [
                'o_report_method' => [
                    'type'   => 'radio',
                    'value'  => $config->o_report_method,
                    'values' => [
                        0 => \ForkBB\__('Internal'),
                        1 => \ForkBB\__('By e-mail'), 
                        2 => \ForkBB\__('Both'),
                    ],
                    'title'  => \ForkBB\__('Reporting method label'),
                    'info'   => \ForkBB\__('Reporting method help'),
                ],
                'o_mailing_list' => [
                    'type'      => 'textarea',
                    'value'     => $config->o_mailing_list,
                    'title'     => \ForkBB\__('Mailing list label'),
                    'info'      => \ForkBB\__('Mailing list help'),
                ],
            ],
        ];

        $form['sets'][] = [
            'legend' => \ForkBB\__('Avatars subhead'),
            'fields' => [
                'o_avatars' => [
                    'type'   => 'radio',
                    'value'  => $config->o_avatars,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Use avatars label'),
                    'info'   => \ForkBB\__('Use avatars help'),
                ],
                'o_avatars_dir' => [ //????
                    'type'      => 'text',
                    'maxlength' => 255,
                    'value'     => $config->o_avatars_dir,
                    'title'     => \ForkBB\__('Upload directory label'),
                    'info'      => \ForkBB\__('Upload directory help'),
                    'required'  => true,
                ],
                'o_avatars_width' => [
                    'type'  => 'number',
                    'min'   => 50,
                    'max'   => 999,
                    'value' => $config->o_avatars_width,
                    'title' => \ForkBB\__('Max width label'),
                    'info'  => \ForkBB\__('Max width help'),
                ],
                'o_avatars_height' => [
                    'type'  => 'number',
                    'min'   => 50,
                    'max'   => 999,
                    'value' => $config->o_avatars_height,
                    'title' => \ForkBB\__('Max height label'),
                    'info'  => \ForkBB\__('Max height help'),
                ],
                'o_avatars_size' => [
                    'type'  => 'number',
                    'min'   => 0,
                    'max'   => 9999999,
                    'value' => $config->o_avatars_size,
                    'title' => \ForkBB\__('Max size label'),
                    'info'  => \ForkBB\__('Max size help'),
                ],
            ],
        ];

        $form['sets'][] = [
            'legend' => \ForkBB\__('E-mail subhead'),
            'fields' => [
                'o_admin_email' => [
                    'type'      => 'text',
                    'maxlength' => 80,
                    'value'     => $config->o_admin_email,
                    'title'     => \ForkBB\__('Admin e-mail label'),
                    'info'      => \ForkBB\__('Admin e-mail help'),
                    'required'  => true,
                ],
                'o_webmaster_email' => [
                    'type'      => 'text',
                    'maxlength' => 80,
                    'value'     => $config->o_webmaster_email,
                    'title'     => \ForkBB\__('Webmaster e-mail label'),
                    'info'      => \ForkBB\__('Webmaster e-mail help'),
                    'required'  => true,
                ],
                'o_forum_subscriptions' => [
                    'type'   => 'radio',
                    'value'  => $config->o_forum_subscriptions,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Forum subscriptions label'),
                    'info'   => \ForkBB\__('Forum subscriptions help'),
                ],
                'o_topic_subscriptions' => [
                    'type'   => 'radio',
                    'value'  => $config->o_topic_subscriptions,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Topic subscriptions label'),
                    'info'   => \ForkBB\__('Topic subscriptions help'),
                ],
                'o_smtp_host' => [
                    'type'      => 'text',
                    'maxlength' => 255,
                    'value'     => $config->o_smtp_host,
                    'title'     => \ForkBB\__('SMTP address label'),
                    'info'      => \ForkBB\__('SMTP address help'),
                ],
                'o_smtp_user' => [
                    'type'      => 'text',
                    'maxlength' => 255,
                    'value'     => $config->o_smtp_user,
                    'title'     => \ForkBB\__('SMTP username label'),
                    'info'      => \ForkBB\__('SMTP username help'),
                ],
                'o_smtp_pass' => [
                    'type'      => 'password',
                    'maxlength' => 255,
                    'value'     => $config->o_smtp_pass ? '          ' : null,
                    'title'     => \ForkBB\__('SMTP password label'),
                    'info'      => \ForkBB\__('SMTP password help'),
                ],
                'changeSmtpPassword' => [
                    'type'  => 'checkbox',
                    'value' => '1',
                    'title' => '',
                    'label' => \ForkBB\__('SMTP change password help'),
                ],
                'o_smtp_ssl' => [
                    'type'   => 'radio',
                    'value'  => $config->o_smtp_ssl,
                    'values' => $yn,
                    'title'  => \ForkBB\__('SMTP SSL label'),
                    'info'   => \ForkBB\__('SMTP SSL help'),
                ],
            ],
        ];

        $form['sets'][] = [
            'legend' => \ForkBB\__('Registration subhead'),
            'fields' => [
                'o_regs_allow' => [
                    'type'   => 'radio',
                    'value'  => $config->o_regs_allow,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Allow new label'),
                    'info'   => \ForkBB\__('Allow new help'),
                ],
                'o_regs_verify' => [
                    'type'   => 'radio',
                    'value'  => $config->o_regs_verify,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Verify label'),
                    'info'   => \ForkBB\__('Verify help'),
                ],
                'o_regs_report' => [
                    'type'   => 'radio',
                    'value'  => $config->o_regs_report,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Report new label'),
                    'info'   => \ForkBB\__('Report new help'),
                ],
                'o_rules' => [
                    'type'   => 'radio',
                    'value'  => $config->o_rules,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Use rules label'),
                    'info'   => \ForkBB\__('Use rules help'),
                ],
                'o_rules_message' => [
                    'type'      => 'textarea',
                    'value'     => $config->o_rules_message,
                    'title'     => \ForkBB\__('Rules label'),
                    'info'      => \ForkBB\__('Rules help'),
                ],
                'o_default_email_setting' => [
                    'type'   => 'radio',
                    'dl'     => 'block',
                    'value'  => $config->o_default_email_setting,
                    'values' => [
                        0 => \ForkBB\__('Display e-mail label'),
                        1 => \ForkBB\__('Hide allow form label'), 
                        2 => \ForkBB\__('Hide both label'),
                    ],
                    'title'  => \ForkBB\__('E-mail default label'),
                    'info'   => \ForkBB\__('E-mail default help'),
                ],
            ],
        ];

        $form['sets'][] = [
            'legend' => \ForkBB\__('Announcement subhead'),
            'fields' => [
                'o_announcement' => [
                    'type'   => 'radio',
                    'value'  => $config->o_announcement,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Display announcement label'),
                    'info'   => \ForkBB\__('Display announcement help'),
                ],
                'o_announcement_message' => [
                    'type'      => 'textarea',
                    'value'     => $config->o_announcement_message,
                    'title'     => \ForkBB\__('Announcement message label'),
                    'info'      => \ForkBB\__('Announcement message help'),
                ],

            ],
        ];

        $form['sets'][] = [
            'legend' => \ForkBB\__('Maintenance subhead'),
            'fields' => [
                'o_maintenance' => [
                    'type'   => 'radio',
                    'value'  => $config->o_maintenance,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Maintenance mode label'),
                    'info'   => \ForkBB\__('Maintenance mode help'),
                ],
                'o_maintenance_message' => [
                    'type'      => 'textarea',
                    'value'     => $config->o_maintenance_message,
                    'title'     => \ForkBB\__('Maintenance message label'),
                    'info'      => \ForkBB\__('Maintenance message help'),
                ],

            ],
        ];

        return $form;
    }
}
