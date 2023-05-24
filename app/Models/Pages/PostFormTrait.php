<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Models\Model;
use function \ForkBB\__;

trait PostFormTrait
{
    /**
     * Возвращает данные для построения формы создания темы/сообщения
     */
    protected function messageForm(?Model $model, string $marker, array $args, bool $edit, bool $first, bool $quick): array
    {
        $vars = $args['_vars'] ?? null;

        unset($args['_vars']);

        $notPM     = $this->fIndex !== self::FI_PM;
        $autofocus = $quick ? null : true;
        $form      = [
            'action' => $this->c->Router->link($marker, $args),
            'hidden' => [
                'token' => $this->c->Csrf->create($marker, $args),
            ],
            'sets'   => [],
            'btns'   => [
                'submit' => [
                    'type'  => 'submit',
                    'value' => __('NewTopic' === $marker ? 'Create topic' : 'Submit'),
                ],
                'preview' => [
                    'type'  => 'submit',
                    'value' => __('Preview'),
                    'class' => ['f-opacity'],
                ],
            ],
        ];

        $fieldset = [];

        if ($this->user->isGuest) {
            $fieldset['username'] = [
                'class'     => ['w1'],
                'type'      => 'text',
                'maxlength' => '25',
                'caption'   => 'Username',
                'required'  => true,
                'pattern'   => '^.{2,25}$',
                'value'     => $vars['username'] ?? null,
                'autofocus' => $autofocus,
            ];
            $fieldset['email'] = [
                'class'          => ['w2'],
                'type'           => 'text',
                'maxlength'      => (string) $this->c->MAX_EMAIL_LENGTH,
                'caption'        => 'Email',
                'required'       => 1 === $this->c->config->b_force_guest_email,
                'pattern'        => '.+@.+',
                'value'          => $vars['email'] ?? null,
                'autocapitalize' => 'off',
            ];
            $autofocus = null;
        }

        if ($first) {
            $fieldset['subject'] = [
                'class'     => ['w0'],
                'type'      => 'text',
                'maxlength' => '70',
                'caption'   => 'Subject',
                'required'  => true,
                'value'     => $vars['subject'] ?? null,
                'autofocus' => $autofocus,
            ];
            $autofocus = null;
        }

        $fieldset['message'] = [
            'class'     => ['w0'],
            'type'      => 'textarea',
            'caption'   => 'Message',
            'required'  => true,
            'value'     => $vars['message'] ?? null,
            'autofocus' => $autofocus,
        ];
        $form['sets']['uesm'] = [
            'fields' => $fieldset,
        ];

        $autofocus = null;
        $fieldset  = [];

        if ($notPM) {
            if (
                $this->user->isAdmin
                || $this->user->isModerator($model)
            ) {
                if ($first) {
                    $fieldset['stick_topic'] = [
                        'type'    => 'checkbox',
                        'label'   => 'Stick topic',
                        'checked' => (bool) ($vars['stick_topic'] ?? false),
                    ];
                    $fieldset['stick_fp'] = [
                        'type'    => 'checkbox',
                        'label'   => 'Stick first post',
                        'checked' => (bool) ($vars['stick_fp'] ?? false),
                    ];
                } elseif (! $edit) {
                    $fieldset['merge_post'] = [
                        'type'    => 'checkbox',
                        'label'   => 'Merge posts',
                        'checked' => (bool) ($vars['merge_post'] ?? true),
                    ];
                }

                if (
                    $edit
                    && ! $model->user->isGuest
                    && ! $model->user->isAdmin
                ) {
                    $fieldset['edit_post'] = [
                        'type'    => 'checkbox',
                        'label'   => 'EditPost edit',
                        'checked' => (bool) ($vars['edit_post'] ?? false),
                    ];
                }
            }

            if (
                ! $edit
                && 1 === $this->c->config->b_topic_subscriptions
                && $this->user->email_confirmed
            ) {
                $subscribed = ! $first && $model->is_subscribed;

                if ($quick) {
                    if (
                        $subscribed
                        || $this->user->auto_notify
                    ) {
                        $form['hidden']['subscribe'] = '1';
                    }
                } else {
                    $fieldset['subscribe'] = [
                        'type'    => 'checkbox',
                        'label'   => $subscribed ? 'Stay subscribed' : 'New subscribe',
                        'checked' => (bool) ($vars['subscribe'] ?? ($subscribed || $this->user->auto_notify)),
                    ];
                }
            }
        }

        if (
            ! $quick
            && 1 === $this->c->config->b_smilies
        ) {
            $fieldset['hide_smilies'] = [
                'type'    => 'checkbox',
                'label'   => 'Hide smilies',
                'checked' => (bool) ($vars['hide_smilies'] ?? false),
            ];
        }

        if ($fieldset) {
            $form['sets']['uesm-options'] = [
                'legend' => 'Options',
                'fields' => $fieldset,
            ];
        }

        if (
            $first
            && $notPM
            && $this->user->usePoll
        ) {
            $term = $edit && $model->parent->poll_term
                ? $model->parent->poll_term
                : $this->c->config->i_poll_term;

            $fieldset = [];

            $fieldset['poll_enable'] = [
                'type'     => 'checkbox',
                'label'    => 'Include poll',
                'checked'  => (bool) ($vars['poll_enable'] ?? false),
                'disabled' => $vars['pollNoEdit'] ?? null,
            ];
            $fieldset["poll[duration]"] = [
                'type'     => 'number',
                'min'      => '0',
                'max'      => '366',
                'value'    => $vars['poll']['duration'] ?? 0,
                'caption'  => 'Poll duration label',
                'help'     => 'Poll duration help',
                'disabled' => $vars['pollNoEdit'] ?? null,
            ];
            $fieldset['poll[hide_result]'] = [
                'type'     => 'checkbox',
                'label'    => ['Hide poll results up to %s voters', $term],
                'checked'  => (bool) ($vars['poll']['hide_result'] ?? false),
                'disabled' => $vars['pollNoEdit'] ?? null,
            ];

            $form['sets']['uesm-poll'] = [
                'legend' => 'Poll legend',
                'fields' => $fieldset,
            ];

            for ($qid = 1; $qid <= $this->c->config->i_poll_max_questions; $qid++) {
                $fieldset = [];

                $fieldset["poll[question][{$qid}]"] = [
                    'type'      => 'text',
                    'maxlength' => '240',
                    'caption'   => 'Question text label',
                    'value'     => $vars['poll']['question'][$qid] ?? null,
                    'disabled'  => $vars['pollNoEdit'] ?? null,
                ];
                $fieldset["poll[type][{$qid}]"] = [
                    'type'     => 'number',
                    'min'      => '1',
                    'max'      => (string) $this->c->config->i_poll_max_fields,
                    'value'    => $vars['poll']['type'][$qid] ?? 1,
                    'caption'  => 'Answer type label',
                    'help'     => 'Answer type help',
                    'disabled' => $vars['pollNoEdit'] ?? null,
                ];

                for ($fid = 1; $fid <= $this->c->config->i_poll_max_fields; $fid++) {
                    $fieldset["poll[answer][{$qid}][{$fid}]"] = [
                        'type'      => 'text',
                        'maxlength' => '240',
                        'caption'   => ['Answer %s label', $fid],
                        'value'     => $vars['poll']['answer'][$qid][$fid] ?? null,
                        'disabled'  => $vars['pollNoEdit'] ?? null,
                    ];
                }

                $form['sets']["uesm-q-{$qid}"] = [
                    'legend' => ['Question %s legend', $qid],
                    'fields' => $fieldset,
                ];
            }

            $this->pageHeader('pollJS', 'script', 9000, [
                'src' => $this->publicLink('/js/poll.js'),
            ]);
        }

        if (1 === $this->c->config->b_message_bbcode) {
            $form = $this->setSCEditor($form, 'message');
        }

        return $form;
    }

    protected function setSCEditor(array $form, string $field)
    {
        foreach ($form['sets'] as &$section) {
            if (empty($section['fields'])) {
                continue;
            }

            foreach ($section['fields'] as $key => &$cur) {
                if (
                    $key === $field
                    && isset($cur['type'])
                    && 'textarea' === $cur['type']
                ) {
                    $smilies = $hidden = [];
                    $smiliesEnabled    = '0';

                    if (1 === $this->c->config->b_smilies) {
                        $smiliesEnabled = '1';

                        foreach ($this->c->smilies->list as $sm) {
                            if (isset($smilies[$sm['sm_image']])) {
                                $hidden[$sm['sm_code']] = $sm['sm_image'];
                            } else {
                                $smilies[$sm['sm_image']] = $sm['sm_code'];
                            }
                        }
                    }

                    $scConfig = \json_encode([
                        'style'         => $this->publicLink("/style/{$this->user->style}/sccontent.css", true)
                                            ?: $this->publicLink('/style/sc/themes/content/default.css'),
                        'locale'        => __('lang_identifier'),
                        'emoticonsRoot' => $this->c->PUBLIC_URL . '/img/sm/',
                        'emoticons'     => [
                            'dropdown' => \array_flip($smilies),
                            'hidden'   => $hidden,
                        ],
                        'plugins' => 'alternative-lists',
                    ]);
                    $cur['data'] = [
                        'SCEditorConfig' => $scConfig,
                        'smiliesEnabled' => $smiliesEnabled,
                        'linkEnabled'    => $this->c->user->g_post_links,
                    ];

                    $this->pageHeader('sceditor', 'script', 9600, [
                        'src' => $this->publicLink('/js/sc/sceditor.js'),
                    ]);
                    $this->pageHeader('scmonocons', 'script', 9550, [
                        'src' => $this->publicLink('/js/sc/icons/monocons.js'),
                    ]);
                    $this->pageHeader('scbbcode', 'script', 9550, [
                        'src' => $this->publicLink('/js/sc/formats/bbcode.js'),
                    ]);
                    $this->pageHeader('scalternative-lists', 'script', 9550, [
                        'src' => $this->publicLink('/js/sc/plugins/alternative-lists.js'),
                    ]);
                    $this->pageHeader('sclanguage', 'script', 9550, [
                        'src' => $this->publicLink('/js/sc/languages/' . __('lang_identifier') . '.js'),
                    ]);
                    $this->pageHeader('scloader', 'script', 9500, [
                        'src' => $this->publicLink('/js/scloader.js'),
                    ]);
                    $this->pageHeader('scdefaultstyle', 'link', 9500, [
                        'rel'  => 'stylesheet',
                        'type' => 'text/css',
                        'href' => $this->publicLink("/style/sc/themes/default.css"),
                    ]);

                    unset($cur);

                    break 2;
                }
            }
        }

        return $form;
    }
}
