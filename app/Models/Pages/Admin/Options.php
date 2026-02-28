<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Image;
use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\Pages\TimeZoneTrait;
use ForkBB\Models\Config\Config;
use DateTimeZone;
use function \ForkBB\__;

class Options extends Admin
{
    use TimeZoneTrait;

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
                    'check_type'    => [$this, 'vCheckType'],
                ])->addRules([
                    'token'                   => 'token:AdminOptions',
                    'o_board_title'           => 'required|string:trim|max:255',
                    'o_board_desc'            => 'exist|string:trim,empty|max:65000 bytes|html',
                    's_meta_desc'             => 'exist|string:trim,empty|max:255',
                    'o_default_timezone'      => [
                        'required',
                        'string:trim',
                        'in' => DateTimeZone::listIdentifiers(),
                    ],
                    'o_default_lang'          => [
                        'required',
                        'string:trim',
                        'in' => $this->c->Func->getLangs(),
                    ],
                    'b_default_lang_auto'     => 'required|integer|in:0,1',
                    'o_default_style'         => [
                        'required',
                        'string:trim',
                        'in' => $this->c->Func->getStyles(),
                    ],
                    'i_timeout_visit'         => 'required|integer|min:0|max:99999',
                    'i_timeout_online'        => 'required|integer|min:0|max:99999|check_timeout',
                    'i_redirect_delay'        => 'required|integer|min:0|max:99999',
                    'i_search_ttl'            => 'required|integer|min:300|max:99999',
                    'b_show_user_info'        => 'required|integer|in:0,1',
                    'b_show_post_count'       => 'required|integer|in:0,1',
                    'i_topic_review'          => 'required|integer|min:0|max:50',
                    'i_disp_topics_default'   => 'required|integer|min:10|max:50',
                    'i_disp_posts_default'    => 'required|integer|min:10|max:50',
                    'i_disp_users'            => 'required|integer|min:10|max:50',
                    'b_quickpost'             => 'required|integer|in:0,1',
                    'b_users_online'          => 'required|integer|in:0,1',
                    'b_show_dot'              => 'required|integer|in:0,1',
                    'b_topic_views'           => 'required|integer|in:0,1',
                    'o_additional_navlinks'   => 'exist|string:trim|max:65000 bytes',
                    'i_feed_type'             => 'required|integer|in:0,1,2',
                    'i_feed_ttl'              => 'required|integer|in:0,5,15,30,60',
                    'i_report_method'         => 'required|integer|in:0,1,2',
                    'o_mailing_list'          => 'exist|string:trim|max:65000 bytes', // ???? проверка списка email
                    'b_avatars'               => 'required|integer|in:0,1',
                    'o_avatars_dir'           => 'required|string:trim|max:255|check_dir',
                    'i_avatars_width'         => 'required|integer|min:50|max:999',
                    'i_avatars_height'        => 'required|integer|min:50|max:999',
                    'i_avatars_size'          => 'required|integer|min:0|max:9999999',
                    'i_avatars_quality'       => 'required|integer|min:0|max:100',
                    'o_admin_email'           => 'required|string:trim|email',
                    'o_webmaster_email'       => 'required|string:trim|email',
                    'b_forum_subscriptions'   => 'required|integer|in:0,1',
                    'b_topic_subscriptions'   => 'required|integer|in:0,1',
                    'i_email_max_recipients'  => 'required|integer|min:1|max:99999',
                    'o_smtp_host'             => 'exist|string:trim|max:255',
                    'o_smtp_user'             => 'exist|string:trim|max:255',
                    'o_smtp_pass'             => 'exist|string:trim|max:255',
                    'changeSmtpPassword'      => 'checkbox',
                    'b_smtp_ssl'              => 'required|integer|in:0,1',
                    'b_email_use_cron'        => 'required|integer|in:0,1',
                    'b_regs_allow'            => 'required|integer|in:0,1',
                    'b_regs_verify'           => 'required|integer|in:0,1',
                    'b_regs_disable_email'    => 'required|integer|in:0,1',
                    'b_regs_report'           => 'required|integer|in:0,1',
                    'b_rules'                 => 'required|integer|in:0,1|check_empty:o_rules_message',
                    'o_rules_message'         => 'exist|string:trim|max:65000 bytes|html',
                    'i_default_email_setting' => 'required|integer|in:0,1,2',
                    'b_announcement'          => 'required|integer|in:0,1|check_empty:o_announcement_message',
                    'o_announcement_message'  => 'exist|string:trim|max:65000 bytes|html',
                    'b_message_all_caps'      => 'required|integer|in:0,1',
                    'b_subject_all_caps'      => 'required|integer|in:0,1',
                    'b_force_guest_email'     => 'required|integer|in:0,1',
                    'b_hide_guest_email_fld'  => 'required|integer|in:0,1',
                    'b_sig_all_caps'          => 'required|integer|in:0,1',
                    'b_poll_enabled'          => 'required|integer|in:0,1',
                    'i_poll_max_questions'    => 'required|integer|min:1|max:99',
                    'i_poll_max_fields'       => 'required|integer|min:2|max:99',
                    'i_poll_time'             => 'required|integer|min:0|max:999999',
                    'i_poll_term'             => 'required|integer|min:0|max:99',
                    'b_poll_guest'            => 'required|integer|in:0,1',
                    'b_pm'                    => 'required|integer|in:0,1',
                    'b_oauth_allow'           => 'required|integer|in:0,1',
                    'upload_og_image'         => 'image',
                    'delete_og_image'         => 'checkbox',
                    'b_reaction'              => 'required|integer|in:0,1',
                    'a_reaction_types'        => 'required|array|check_type',
                    'b_show_user_reaction'    => 'required|integer|in:0,1',
                    'b_colored_subjects'      => 'required|integer|in:0,1',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                    'o_board_title'     => 'Must enter title message',
                    'o_admin_email'     => 'Invalid e-mail message',
                    'o_webmaster_email' => 'Invalid webmaster e-mail message',
                ]);

            $valid = $v->validation($_FILES + $_POST);
            $data  = $v->getData();

            if (empty($data['changeSmtpPassword'])) {
                unset($data['o_smtp_pass']);
            }

            unset($data['changeSmtpPassword'], $data['token'], $data['upload_og_image'], $data['delete_og_image']);

            foreach ($data as $attr => $value) {
                $config->$attr = $value;
            }

            if ($valid) {
                if (
                    $v->delete_og_image
                    || $v->upload_og_image instanceof Image
                ) {
                    $folder = $this->c->DIR_PUBLIC . '/img/og/';

                    $this->deleteOgImage($folder);

                    $config->a_og_image = [];
                }

                if ($v->upload_og_image instanceof Image) {
                    $path = $folder . $this->c->Secury->randomPass(8) . '.webp';

                    $result = $v->upload_og_image
                        ->rename(true)
                        ->rewrite(false)
                        ->setQuality($this->c->config->i_avatars_quality ?? 75)
                        ->toFile($path);

                    if (true === $result) {
                        $config->a_og_image = [
                            'file'   => $v->upload_og_image->name() . '.' . $v->upload_og_image->ext(),
                            'width'  => $v->upload_og_image->width(),
                            'height' => $v->upload_og_image->height(),
                        ];

                    } else {
                        $config->a_og_image = [];

                        $this->c->Log->warning('og:image Failed image processing', [
                            'user'  => $this->user->fLog(),
                            'error' => $v->upload_og_image->error(),
                        ]);
                    }
                }

                if (1 === $config->b_force_guest_email) {
                    $config->b_hide_guest_email_fld = 0;
                }

                if (1 === $config->b_regs_verify) {
                    $config->b_regs_disable_email = 0;
                }

                $config->save();

                return $this->c->Redirect->page('AdminOptions')->message('Options updated redirect', FORK_MESS_SUCC);
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
     * Удаляет текущую картинку Open Graph
     */
    protected function deleteOgImage(string $folder): void
    {
        if (! empty($this->c->config->a_og_image['file'])) {
            $path = $folder . $this->c->config->a_og_image['file'];

            if (\is_file($path)) {
                \unlink($path);
            }
        }
    }

    /**
     * Дополнительная проверка времени online
     */
    public function vCheckTimeout(Validator $v, int $timeout): int
    {
        if ($timeout >= $v->i_timeout_visit) {
            $v->addError('Timeout error message');
        }

        return $timeout;
    }

    /**
     * Дополнительная проверка каталога аватарок
     */
    public function vCheckDir(Validator $v, string $dir): string
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
    public function vCheckEmpty(Validator $v, int $value, string $attr): int
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
     * Проверка и преобразование для типов реакций
     */
    public function vCheckType(Validator $v, array $types): array
    {
        $types  = \array_flip($types);
        $result = $this->c->config->a_reaction_types;

        foreach ($result as &$cur) {
            $cur[1] = isset($types[$cur[0]]);
        }

        unset($cur);

        return $result;
    }

    /**
     * Формирует данные для формы
     */
    protected function formEdit(Config $config): array
    {
        $form = [
            'action'  => $this->c->Router->link('AdminOptions'),
            'hidden'  => [
                'token' => $this->c->Csrf->create('AdminOptions'),
            ],
            'enctype' => 'multipart/form-data',
            'sets'    => [],
            'btns'    => [
                'save'  => [
                    'type'  => 'submit',
                    'value' => __('Save changes'),
                ],
            ],
        ];

        $yn     = [1 => __('Yes'), 0 => __('No')];
        $langs  = $this->c->Func->getNameLangs();
        $styles = $this->c->Func->getStyles();

        if (isset($config->a_og_image['file'])) {
            $this->ogImageUrl = $this->c->PUBLIC_URL . '/img/og/' . $config->a_og_image['file'];
        }

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
                ],
                'o_board_desc' => [
                    'type'    => 'textarea',
                    'value'   => $config->o_board_desc,
                    'caption' => 'Board desc label',
                    'help'    => 'Board desc help',
                ],
                's_meta_desc' => [
                    'type'      => 'text',
                    'maxlength' => '255',
                    'value'     => $config->s_meta_desc,
                    'caption'   => 'Meta desc label',
                    'help'      => 'Meta desc help',
                ],
                'o_default_timezone' => [
                    'type'    => 'select',
                    'options' => $this->createTimeZoneOptions(),
                    'value'   => $config->o_default_timezone,
                    'caption' => 'Timezone label',
                    'help'    => 'Timezone help',
                ],
                'o_default_lang' => [
                    'type'    => 'select',
                    'options' => $langs,
                    'value'   => $config->o_default_lang,
                    'caption' => 'Language label',
                    'help'    => 'Language help',
                ],
                'b_default_lang_auto' => [
                    'type'    => 'radio',
                    'value'   => $config->b_default_lang_auto,
                    'values'  => $yn,
                    'caption' => 'Auto language detection label',
                    'help'    => 'Auto language detection help',
                ],
                'o_default_style' => [
                    'type'    => 'select',
                    'options' => $styles,
                    'value'   => $config->o_default_style,
                    'caption' => 'Default style label',
                    'help'    => 'Default style help',
                ],
                'a_og_image' => [
                    'type'    => empty($this->ogImageUrl) ? 'str' : 'yield',
                    'caption' => 'Og image label',
                    'value'   => empty($this->ogImageUrl) ? __('Not uploaded') : 'og:image',
                    'help'    => empty($this->ogImageUrl) ? null : ['Og image help', $config->a_og_image['width'], $config->a_og_image['height']],
                ],
                'delete_og_image' => [
                    'type'    => 'checkbox',
                    'label'   => 'Delete og image',
                    'checked' => false,
                ],
                'upload_og_image' => [
                    'type'    => 'file',
                    'caption' => 'New og image label',
                    'help'    => 'New og image help',
                    'accept'  => 'image/*',
                ],
            ],
        ];

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
                'i_search_ttl' => [
                    'type'    => 'number',
                    'min'     => '300',
                    'max'     => '99999',
                    'value'   => $config->i_search_ttl,
                    'caption' => 'Search cache lifetime label',
                    'help'    => 'Search cache lifetime help',
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
                'b_message_all_caps' => [
                    'type'    => 'radio',
                    'value'   => $config->b_message_all_caps,
                    'values'  => $yn,
                    'caption' => 'All caps message label',
                    'help'    => 'All caps message help',
                ],
                'b_subject_all_caps' => [
                    'type'    => 'radio',
                    'value'   => $config->b_subject_all_caps,
                    'values'  => $yn,
                    'caption' => 'All caps subject label',
                    'help'    => 'All caps subject help',
                ],
                'b_sig_all_caps' => [
                    'type'    => 'radio',
                    'value'   => $config->b_sig_all_caps,
                    'values'  => $yn,
                    'caption' => 'All caps sigs label',
                    'help'    => 'All caps sigs help',
                ],
                'b_force_guest_email' => [
                    'type'    => 'radio',
                    'value'   => $config->b_force_guest_email,
                    'values'  => $yn,
                    'caption' => 'Require e-mail label',
                    'help'    => 'Require e-mail help',
                ],
                'b_hide_guest_email_fld' => [
                    'type'    => 'radio',
                    'value'   => $config->b_hide_guest_email_fld,
                    'values'  => $yn,
                    'caption' => 'Hide e-mail label',
                    'help'    => 'Hide e-mail help',
                ],
                'b_colored_subjects' => [
                    'type'    => 'radio',
                    'value'   => $config->b_colored_subjects,
                    'values'  => $yn,
                    'caption' => 'Colored topic titles label',
                    'help'    => 'Colored topic titles help',
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
                'b_show_dot' => [
                    'type'    => 'radio',
                    'value'   => $config->b_show_dot,
                    'values'  => $yn,
                    'caption' => 'User has posted label',
                    'help'    => 'User has posted help',
                ],
                'b_topic_views' => [
                    'type'    => 'radio',
                    'value'   => $config->b_topic_views,
                    'values'  => $yn,
                    'caption' => 'Topic views label',
                    'help'    => 'Topic views help',
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
                'b_avatars' => [
                    'type'    => 'radio',
                    'value'   => $config->b_avatars,
                    'values'  => $yn,
                    'caption' => 'Use avatars label',
                    'help'    => 'Use avatars help',
                ],
                'o_avatars_dir' => [
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
                'i_avatars_quality' => [
                    'type'    => 'number',
                    'min'     => '0',
                    'max'     => '100',
                    'value'   => $config->i_avatars_quality,
                    'caption' => 'Avatar quality label',
                    'help'    => 'Avatar quality help',
                ],
            ],
        ];

        $form['sets']['email'] = [
            'legend' => 'E-mail subhead',
            'fields' => [
                'o_admin_email' => [
                    'type'           => 'text',
                    'maxlength'      => (string) $this->c->MAX_EMAIL_LENGTH,
                    'value'          => $config->o_admin_email,
                    'caption'        => 'Admin e-mail label',
                    'help'           => 'Admin e-mail help',
                    'required'       => true,
                    'pattern'        => '.+@.+',
                    'autocapitalize' => 'off',
                ],
                'o_webmaster_email' => [
                    'type'           => 'text',
                    'maxlength'      => (string) $this->c->MAX_EMAIL_LENGTH,
                    'value'          => $config->o_webmaster_email,
                    'caption'        => 'Webmaster e-mail label',
                    'help'           => 'Webmaster e-mail help',
                    'required'       => true,
                    'pattern'        => '.+@.+',
                    'autocapitalize' => 'off',
                ],
                'b_forum_subscriptions' => [
                    'type'    => 'radio',
                    'value'   => $config->b_forum_subscriptions,
                    'values'  => $yn,
                    'caption' => 'Forum subscriptions label',
                    'help'    => 'Forum subscriptions help',
                ],
                'b_topic_subscriptions' => [
                    'type'    => 'radio',
                    'value'   => $config->b_topic_subscriptions,
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
                'b_smtp_ssl' => [
                    'type'    => 'radio',
                    'value'   => $config->b_smtp_ssl,
                    'values'  => $yn,
                    'caption' => 'SMTP SSL label',
                    'help'    => 'SMTP SSL help',
                ],
                'b_email_use_cron' => [
                    'type'    => 'radio',
                    'value'   => $config->b_email_use_cron,
                    'values'  => $yn,
                    'caption' => 'Cron for email label',
                    'help'    => 'Cron for email help',
                ],
            ],
        ];

        $form['sets']['registration'] = [
            'legend' => 'Registration subhead',
            'fields' => [
                'b_regs_allow' => [
                    'type'    => 'radio',
                    'value'   => $config->b_regs_allow,
                    'values'  => $yn,
                    'caption' => 'Allow new label',
                    'help'    => 'Allow new help',
                ],
                'b_regs_verify' => [
                    'type'    => 'radio',
                    'value'   => $config->b_regs_verify,
                    'values'  => $yn,
                    'caption' => 'Verify label',
                    'help'    => 'Verify help',
                ],
                'b_regs_disable_email' => [
                    'type'    => 'radio',
                    'value'   => $config->b_regs_disable_email,
                    'values'  => $yn,
                    'caption' => 'Disable email label',
                    'help'    => 'Disable email help',
                ],
                'b_regs_report' => [
                    'type'    => 'radio',
                    'value'   => $config->b_regs_report,
                    'values'  => $yn,
                    'caption' => 'Report new label',
                    'help'    => 'Report new help',
                ],
                'b_oauth_allow' => [
                    'type'    => 'radio',
                    'value'   => $config->b_oauth_allow,
                    'values'  => $yn,
                    'caption' => 'Allow oauth label',
                    'help'    => 'Allow oauth help',
                ],
                'configure_providers' => [
                    'type'  => 'link',
                    'value' => __('Configure providers'),
                    'href'  => $this->c->Router->link('AdminProviders'),
                ],
                'b_rules' => [
                    'type'    => 'radio',
                    'value'   => $config->b_rules,
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
                'b_announcement' => [
                    'type'    => 'radio',
                    'value'   => $config->b_announcement,
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

        $options = [];
        $values  = [];

        foreach ($config->a_reaction_types as $key => $cur) {
            $options[$cur[0]] = __(":{$cur[0]}:");

            if ($cur[1]) {
                $values[] = $cur[0];
            }
        }

        $form['sets']['reaction'] = [
            'legend' => 'Reaction subhead',
            'fields' => [
                'b_reaction' => [
                    'type'    => 'radio',
                    'value'   => $config->b_reaction,
                    'values'  => $yn,
                    'caption' => 'Allow reaction label',
                ],
                'b_show_user_reaction' => [
                    'type'    => 'radio',
                    'value'   => $config->b_show_user_reaction,
                    'values'  => $yn,
                    'caption' => 'User has reactioned label',
                    'help'    => 'User has reactioned help',
                ],
                'a_reaction_types' => [
                    'cprefix'  => 'f-retype f-retype-',
                    'type'     => 'select',
                    'options'  => $options,
                    'value'    => $values,
                    'size'     => 10,
                    'multiple' => true,
                    'caption'  => 'Reaction type label',
                    'help'     => 'Reaction type help',
                ],
            ],
        ];

        return $form;
    }
}
