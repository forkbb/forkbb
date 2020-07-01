<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Model;
use function \ForkBB\__;

trait PostFormTrait
{
    /**
     * Возвращает данные для построения формы создания темы/сообщения
     *
     * @param array $args
     * @param Model $model
     * @param string $marker
     * @param bool $editPost
     * @param bool $editSubject
     * @param bool $quickReply
     *
     * @return array
     */
    protected function messageForm(array $args, Model $model, string $marker, bool $editPost = false, bool $editSubject = false, bool $quickReply = false): array
    {
        $vars = $args['_vars'] ?? null;
        unset($args['_vars']);

        $autofocus = $quickReply ? null : true;
        $form = [
            'action' => $this->c->Router->link($marker, $args),
            'hidden' => [
                'token' => $this->c->Csrf->create($marker, $args),
            ],
            'sets'   => [],
            'btns'   => [
                'submit' => [
                    'type'      => 'submit',
                    'value'     => __('Submit'),
                    'accesskey' => 's',
                ],
                'preview' => [
                    'type'      => 'submit',
                    'value'     => __('Preview'),
                    'accesskey' => 'p',
                    'class'     => 'f-minor',
                ],
            ],
        ];

        $fieldset = [];
        if ($this->user->isGuest) {
            $fieldset['username'] = [
                'class'     => 'w1',
                'type'      => 'text',
                'maxlength' => 25,
                'caption'   => __('Username'),
                'required'  => true,
                'pattern'   => '^.{2,25}$',
                'value'     => $vars['username'] ?? null,
                'autofocus' => $autofocus,
            ];
            $fieldset['email'] = [
                'class'     => 'w2',
                'type'      => 'text',
                'maxlength' => 80,
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
                'maxlength' => 70,
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
            'bb'       => [
                ['link', __('BBCode'), __('1' == $this->c->config->p_message_bbcode ? 'on' : 'off')],
                ['link', __('url tag'), __('1' == $this->c->config->p_message_bbcode && '1' == $this->user->g_post_links ? 'on' : 'off')],
                ['link', __('img tag'), __('1' == $this->c->config->p_message_bbcode && '1' == $this->c->config->p_message_img_tag ? 'on' : 'off')],
                ['link', __('Smilies'), __('1' == $this->c->config->o_smilies ? 'on' : 'off')],
            ],
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
            $form['sets']['sett'] = [
                'legend' => __('Options'),
                'fields' => $fieldset,
            ];
        }

        return $form;
    }
}
