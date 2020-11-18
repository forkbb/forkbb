<?php

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Models\Model;
use function \ForkBB\__;

trait PostFormTrait
{
    /**
     * Возвращает данные для построения формы создания темы/сообщения
     */
    protected function messageForm(array $args, Model $model, string $marker, bool $editPost = false, bool $editSubject = false, bool $quickReply = false): array
    {
        $vars = $args['_vars'] ?? null;
        unset($args['_vars']);

        $autofocus = $quickReply ? null : true;
        $form = [
            'action' => $this->c->Router->link(
                $marker,
                $args
            ),
            'hidden' => [
                'token' => $this->c->Csrf->create(
                    $marker,
                    $args
                ),
            ],
            'sets'   => [],
            'btns'   => [
                'submit' => [
                    'type'      => 'submit',
                    'value'     => __('Submit'),
//                    'accesskey' => 's',
                ],
                'preview' => [
                    'type'      => 'submit',
                    'value'     => __('Preview'),
//                    'accesskey' => 'p',
                    'class'     => 'f-opacity',
                ],
            ],
        ];

        $fieldset = [];
        if ($this->user->isGuest) {
            $fieldset['username'] = [
                'class'     => 'w1',
                'type'      => 'text',
                'maxlength' => '25',
                'caption'   => __('Username'),
                'required'  => true,
                'pattern'   => '^.{2,25}$',
                'value'     => $vars['username'] ?? null,
                'autofocus' => $autofocus,
            ];
            $fieldset['email'] = [
                'class'     => 'w2',
                'type'      => 'text',
                'maxlength' => '80',
                'caption'   => __('Email'),
                'required'  => '1' == $this->c->config->p_force_guest_email,
                'pattern'   => '.+@.+',
                'value'     => $vars['email'] ?? null,
            ];
            $autofocus = null;
        }

        if ($editSubject) {
            $fieldset['subject'] = [
                'class'     => 'w0',
                'type'      => 'text',
                'maxlength' => '70',
                'caption'   => __('Subject'),
                'required'  => true,
                'value'     => $vars['subject'] ?? null,
                'autofocus' => $autofocus,
            ];
            $autofocus = null;
        }

        $fieldset['message'] = [
            'class'    => 'w0',
            'type'     => 'textarea',
            'caption'  => __('Message'),
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

        $fieldset = [];
        if (
            $this->user->isAdmin
            || $this->user->isModerator($model)
        ) {
            if ($editSubject) {
                $fieldset['stick_topic'] = [
                    'type'    => 'checkbox',
                    'label'   => __('Stick topic'),
                    'value'   => '1',
                    'checked' => isset($vars['stick_topic']) ? (bool) $vars['stick_topic'] : false,
                ];
                $fieldset['stick_fp'] = [
                    'type'    => 'checkbox',
                    'label'   => __('Stick first post'),
                    'value'   => '1',
                    'checked' => isset($vars['stick_fp']) ? (bool) $vars['stick_fp'] : false,
                ];
            } elseif (! $editPost) {
                $fieldset['merge_post'] = [
                    'type'    => 'checkbox',
                    'label'   => __('Merge posts'),
                    'value'   => '1',
                    'checked' => isset($vars['merge_post']) ? (bool) $vars['merge_post'] : true,
                ];
            }

            if (
                $editPost
                && ! $model->user->isGuest
                && ! $model->user->isAdmin
            ) {
                $fieldset['edit_post'] = [
                    'type'    => 'checkbox',
                    'label'   => __('EditPost edit'),
                    'value'   => '1',
                    'checked' => isset($vars['edit_post']) ? (bool) $vars['edit_post'] : false,
                ];
            }
        }

        if (
            ! $editPost
            && '1' == $this->c->config->o_topic_subscriptions
            && $this->user->email_confirmed
        ) {
            $subscribed = ! $editSubject && $model->is_subscribed;

            if ($quickReply) {
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
                    'checked' => isset($vars['subscribe'])
                        ? (bool) $vars['subscribe']
                        : ($subscribed || $this->user->auto_notify),
                ];
            }
        }

        if (
            ! $quickReply
            && '1' == $this->c->config->o_smilies
        ) {
            $fieldset['hide_smilies'] = [
                'type'    => 'checkbox',
                'label'   => __('Hide smilies'),
                'value'   => '1',
                'checked' => isset($vars['hide_smilies']) ? (bool) $vars['hide_smilies'] : false,
            ];
        }

        if ($fieldset) {
            $form['sets']['uesm-options'] = [
                'legend' => __('Options'),
                'fields' => $fieldset,
            ];
        }

        if (
            $editSubject
            && '1' == $this->c->config->b_poll_enabled
        ) {
            $fieldset = [];

            $fieldset['poll_enable'] = [
                'type'    => 'checkbox',
                'label'   => __('Include poll'),
                'value'   => '1',
                'checked' => (bool) ($vars['poll_enable'] ?? false),
            ];
            $fieldset['poll[hide_result]'] = [
                'type'    => 'checkbox',
                'label'   => __('Hide poll results up to %s voters', $this->c->config->i_poll_term), // ???? при редактировании взять данные из топика?
                'value'   => '1',
                'checked' => (bool) ($vars['poll']['hide_result'] ?? false),
            ];

            $form['sets']['uesm-poll'] = [
                'legend' => __('Poll legend'),
                'fields' => $fieldset,
            ];

            for ($qid = 1; $qid <= $this->c->config->i_poll_max_questions; $qid++) {
                $fieldset = [];

                $fieldset["poll[question][{$qid}]"] = [
                    'type'      => 'text',
                    'maxlength' => '255',
                    'caption'   => __('Question text label'),
                    'value'     => $vars['poll']['question'][$qid] ?? null,
                ];
                $fieldset["poll[type][{$qid}]"] = [
                    'type'    => 'number',
                    'min'     => '1',
                    'max'     => (string) $this->c->config->i_poll_max_fields,
                    'value'   => $vars['poll']['type'][$qid] ?? 1,
                    'caption' => __('Answer type label'),
                    'info'    => __('Answer type help'),
                ];

                for ($fid = 1; $fid <= $this->c->config->i_poll_max_fields; $fid++) {
                    $fieldset["poll[answer][{$qid}][{$fid}]"] = [
                        'type'      => 'text',
                        'maxlength' => '255',
                        'caption'   => __('Answer %s label', $fid),
                        'value'     => $vars['poll']['answer'][$qid][$fid] ?? null,
                    ];
                }

                $form['sets']["uesm-q-{$qid}"] = [
                    'legend' => __('Question %s legend', $qid),
                    'fields' => $fieldset,
                ];
            }
        }

        return $form;
    }
}
