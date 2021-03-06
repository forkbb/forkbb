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
                    'value' => __('Submit'),
                ],
                'preview' => [
                    'type'  => 'submit',
                    'value' => __('Preview'),
                    'class' => 'f-opacity',
                ],
            ],
        ];

        $fieldset = [];

        if ($this->user->isGuest) {
            $fieldset['username'] = [
                'class'     => 'w1',
                'type'      => 'text',
                'maxlength' => '25',
                'caption'   => 'Username',
                'required'  => true,
                'pattern'   => '^.{2,25}$',
                'value'     => $vars['username'] ?? null,
                'autofocus' => $autofocus,
            ];
            $fieldset['email'] = [
                'class'     => 'w2',
                'type'      => 'text',
                'maxlength' => '80',
                'caption'   => 'Email',
                'required'  => '1' == $this->c->config->p_force_guest_email,
                'pattern'   => '.+@.+',
                'value'     => $vars['email'] ?? null,
            ];
            $autofocus = null;
        }

        if ($first) {
            $fieldset['subject'] = [
                'class'     => 'w0',
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
            'class'    => 'w0',
            'type'     => 'textarea',
            'caption'  => 'Message',
            'required' => true,
            'value'    => $vars['message'] ?? null,
/* ????
            'bb'       => [
                ['link', __('BBCode'), __('1' == $this->c->config->p_message_bbcode ? 'on' : 'off')],
                ['link', __('url tag'), __('1' == $this->c->config->p_message_bbcode && '1' == $this->user->g_post_links ? 'on' : 'off')],
                ['link', __('img tag'), __('1' == $this->c->config->p_message_bbcode && '1' == $this->c->config->p_message_img_tag ? 'on' : 'off')],
                ['link', __('Smilies'), __('1' == $this->c->config->o_smilies ? 'on' : 'off')],
            ],
*/
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
                        'label'   => __('Stick topic'),
                        'value'   => '1',
                        'checked' => (bool) ($vars['stick_topic'] ?? false),
                    ];
                    $fieldset['stick_fp'] = [
                        'type'    => 'checkbox',
                        'label'   => __('Stick first post'),
                        'value'   => '1',
                        'checked' => (bool) ($vars['stick_fp'] ?? false),
                    ];
                } elseif (! $edit) {
                    $fieldset['merge_post'] = [
                        'type'    => 'checkbox',
                        'label'   => __('Merge posts'),
                        'value'   => '1',
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
                        'label'   => __('EditPost edit'),
                        'value'   => '1',
                        'checked' => (bool) ($vars['edit_post'] ?? false),
                    ];
                }
            }

            if (
                ! $edit
                && '1' == $this->c->config->o_topic_subscriptions
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
                        'label'   => $subscribed ? __('Stay subscribed') : __('New subscribe'),
                        'value'   => '1',
                        'checked' => (bool) ($vars['subscribe'] ?? ($subscribed || $this->user->auto_notify)),
                    ];
                }
            }
        }

        if (
            ! $quick
            && '1' == $this->c->config->o_smilies
        ) {
            $fieldset['hide_smilies'] = [
                'type'    => 'checkbox',
                'label'   => __('Hide smilies'),
                'value'   => '1',
                'checked' => (bool) ($vars['hide_smilies'] ?? false),
            ];
        }

        if ($fieldset) {
            $form['sets']['uesm-options'] = [
                'legend' => __('Options'),
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
                'label'    => __('Include poll'),
                'value'    => '1',
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
                'label'    => __(['Hide poll results up to %s voters', $term]),
                'value'    => '1',
                'checked'  => (bool) ($vars['poll']['hide_result'] ?? false),
                'disabled' => $vars['pollNoEdit'] ?? null,
            ];

            $form['sets']['uesm-poll'] = [
                'legend' => __('Poll legend'),
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
                    'legend' => __(['Question %s legend', $qid]),
                    'fields' => $fieldset,
                ];
            }

            $this->pageHeader('pollJS', 'script', 9000, [
                'src' => $this->publicLink('/js/poll.js'),
            ]);
        }

        return $form;
    }
}
