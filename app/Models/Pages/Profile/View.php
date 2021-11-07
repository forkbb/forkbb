<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Models\Page;
use ForkBB\Models\Pages\Profile;
use ForkBB\Models\PM\Cnst;
use function \ForkBB\__;

class View extends Profile
{
    /**
     * Подготавливает данные для шаблона просмотра профиля
     */
    public function view(array $args, string $method): Page
    {
        if (false === $this->initProfile($args['id'])) {
            return $this->c->Message->message('Bad request');
        }

        $this->hhsLevel   = 'common'; // для остальных страниц профиля уровень задан в initProfile()
        $this->canonical  = $this->curUser->link;
        $this->robots     = null;
        $this->crumbs     = $this->crumbs();

        $this->c->Online->calc($this); // для $this->curUser->lastVisit

        $this->form       = $this->form($args);
        $this->actionBtns = $this->btns('view');

        return $this;
    }

    /**
     * Создает массив данных для формы
     */
    protected function form(array $args): array
    {
        $form = [
            'sets' => []
        ];

        // имя, титул и аватара
        $fields = [];
        $fields['usertitle'] = [
            'class' => ['usertitle'],
            'type'  => 'wrap',
        ];
        $fields['username'] = [
            'class'   => ['pline'],
            'type'    => 'str',
            'caption' => 'Username',
            'value'   => $this->curUser->username,
        ];
        $fields['title'] = [
            'class'   => ['pline'],
            'type'    => 'str',
            'caption' => 'Title',
            'value'   => $this->curUser->title(),
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        if (
            $this->rules->useAvatar
            && $this->curUser->avatar
        ) {
            $fields['avatar'] = [
                'type'    => 'yield',
                'caption' => 'Avatar',
                'value'   => 'avatar',
            ];
        }
        $form['sets']['header'] = [
            'class'  => ['header'],
#            'legend' => __('Options'),
            'fields' => $fields,
        ];

        // примечание администрации
        if (
            $this->user->isAdmMod
            && '' != $this->curUser->admin_note
        ) {
            $form['sets']['note'] = [
                'class'  => ['data'],
                'legend' => __('Admin note'),
                'fields' => [
                    'admin_note' => [
                        'class'     => ['pline'],
                        'type'      => 'str',
                        'caption'   => 'Admin note',
                        'value'     => $this->curUser->admin_note,
                    ],
                ],
            ];
        }

        // личное
        $fields = [];
        if ('' != $this->curUser->realname) {
            $fields['realname'] = [
                'class'   => ['pline'],
                'type'    => 'str',
                'caption' => 'Realname',
                'value'   => $this->curUser->censorRealname,
            ];
        }
        $genders = [
            1 => __('Male'),
            2 => __('Female'),
        ];
        if (isset($genders[$this->curUser->gender])) {
            $fields['gender'] = [
                'class'   => ['pline'],
                'type'    => 'str',
                'value'   => $genders[$this->curUser->gender],
                'caption' => 'Gender',
            ];
        }
        if ('' != $this->curUser->location) {
            $fields['location'] = [
                'class'   => ['pline'],
                'type'    => 'str',
                'caption' => 'Location',
                'value'   => $this->curUser->censorLocation,
            ];
        }
        if (! empty($fields)) {
            $form['sets']['personal'] = [
                'class'  => ['data'],
                'legend' => __('Personal information'),
                'fields' => $fields,
            ];
        }

        // контактная информация
        $fields = [];
        if ($this->rules->sendPM) {
            $this->c->Csrf->setHashExpiration(3600);

            $pmArgs = [
                'action' => Cnst::ACTION_SEND,
                'more1'  => $this->curUser->id,
            ];
            $pmArgs += [
                'more2' => $this->c->Csrf->createHash('PMAction', $pmArgs),
            ];

            $fields['pm'] = [
                'class'   => ['pline'],
                'type'    => 'link',
                'caption' => 'PM',
                'value'   => __('Send PM'),
                'href'    => $this->c->Router->link('PMAction', $pmArgs),
            ];
        }
        if ($this->rules->viewEmail) {
            if (0 === $this->curUser->email_setting) {
                $fields['email'] = [
                    'class'   => ['pline'],
                    'type'    => 'link',
                    'caption' => 'Email info',
                    'value'   => $this->curUser->censorEmail,
                    'href'    => 'mailto:' . $this->curUser->censorEmail,
                ];
            } elseif ($this->rules->sendEmail) {
                $this->c->Csrf->setHashExpiration(3600);

                $fields['email'] = [
                    'class'   => ['pline'],
                    'type'    => 'link',
                    'caption' => 'Email info',
                    'value'   => __('Send email'),
                    'href'    => $this->c->Router->link('SendEmail', ['id' => $this->curUser->id]),
                ];
            }
        }
        if (
            $this->rules->viewWebsite
            && $this->curUser->url
        ) {
            $fields['url'] = [
                'id'      => 'website',
                'class'   => ['pline'],
                'type'    => 'link',
                'caption' => 'Website',
                'value'   => $this->curUser->censorUrl,
                'href'    => $this->curUser->censorUrl,
                'rel'     => 'ugc',
            ];
        }
        if (! empty($fields)) {
            $form['sets']['contacts'] = [
                'class'  => ['data'],
                'legend' => __('Contact details'),
                'fields' => $fields,
            ];
        }

        // подпись
        if ($this->rules->useSignature) {
            $fields = [];
            if ($this->curUser->isSignature) {
                $fields['signature'] = [
                    'type'    => 'yield',
                    'caption' => 'Signature',
                    'value'   => 'signature',
                ];

                $this->signatureSection = true;
            }
            if (! empty($fields)) {
                $form['sets']['signature'] = [
                    'class'  => ['data'],
                    'legend' => __('Signature'),
                    'fields' => $fields,
                ];
            }
        }

        // активность
        $fields = [];
        $fields['registered'] = [
            'class'   => ['pline'],
            'type'    => 'str',
            'value'   => \ForkBB\dt($this->curUser->registered, true),
            'caption' => 'Registered info',
        ];
        $fields['lastpost'] = [
            'class'   => ['pline'],
            'type'    => 'str',
            'value'   => \ForkBB\dt($this->curUser->last_post, true),
            'caption' => 'Last post info',
        ];
        if ($this->curUser->last_post > 0) {
            if ('1' == $this->user->g_search) {
                $fields['posts'] = [
                    'class'   => ['pline'],
                    'type'    => 'link',
                    'caption' => 'Posts info',
                    'value'   => $this->user->showPostCount ? \ForkBB\num($this->curUser->num_posts) : __('Show posts'),
                    'href'    => $this->c->Router->link(
                        'SearchAction',
                        [
                            'action' => 'posts',
                            'uid'    => $this->curUser->id,
                        ]
                    ),
                    'title'   => __('Show posts'),
                ];
                $fields['topics'] = [
                    'class'   => ['pline'],
                    'type'    => 'link',
                    'caption' => 'Topics info',
                    'value'   => $this->user->showPostCount ? \ForkBB\num($this->curUser->num_topics) : __('Show topics'),
                    'href'    => $this->c->Router->link(
                        'SearchAction',
                        [
                            'action' => 'topics',
                            'uid'    => $this->curUser->id,
                        ]
                    ),
                    'title'   => __('Show topics'),
                ];
            } elseif ($this->user->showPostCount) {
                $fields['posts'] = [
                    'class'   => ['pline'],
                    'type'    => 'str',
                    'caption' => 'Posts info',
                    'value'   => \ForkBB\num($this->curUser->num_posts),
                ];
                $fields['topics'] = [
                    'class'   => ['pline'],
                    'type'    => 'str',
                    'caption' => 'Topics info',
                    'value'   => \ForkBB\num($this->curUser->num_topics),
                ];
            }
        }
        if ($this->rules->viewSubscription) {
            $subscr     = $this->c->subscriptions;
            $subscrInfo = $subscr->info($this->curUser);
            $isLink     = '1' == $this->user->g_search;
            if (! empty($subscrInfo[$subscr::FORUMS_DATA])) {
                $fields['forums_subscr'] = [
                    'class'   => ['pline'],
                    'type'    => $isLink ? 'link' : 'str',
                    'caption' => 'Total forums subscriptions',
                    'value'   => \ForkBB\num(\count($subscrInfo[$subscr::FORUMS_DATA])),
                    'href'    => $this->c->Router->link(
                        'SearchAction',
                        [
                            'action' => 'forums_subscriptions',
                            'uid'    => $this->curUser->id,
                        ]
                    ),
                    'title'   => __('Show forums subscriptions'),
                ];
            }
            if (! empty($subscrInfo[$subscr::TOPICS_DATA])) {
                $fields['topics_subscr'] = [
                    'class'   => ['pline'],
                    'type'    => $isLink ? 'link' : 'str',
                    'caption' => 'Total topics subscriptions',
                    'value'   => \ForkBB\num(\count($subscrInfo[$subscr::TOPICS_DATA])),
                    'href'    => $this->c->Router->link(
                        'SearchAction',
                        [
                            'action' => 'topics_subscriptions',
                            'uid'    => $this->curUser->id,
                        ]
                    ),
                    'title'   => __('Show topics subscriptions'),
                ];
            }
        }
        $form['sets']['activity'] = [
            'class'  => ['data'],
            'legend' => __('User activity'),
            'fields' => $fields,
        ];

        // приватная информация
        $fields = [];
        if ($this->rules->viewLastVisit) {
            $fields['lastvisit'] = [
                'class'   => ['pline'],
                'type'    => 'str',
                'value'   => $this->rules->my
                    ? \ForkBB\dt($this->curUser->last_visit)
                    : \ForkBB\dt($this->curUser->currentVisit, true),
                'caption' => 'Last visit info',
            ];
        }
        if ($this->rules->viewOEmail) {
            $fields['open-email'] = [
                'class'   => ['pline'],
                'type'    => 2 === $this->curUser->email_setting ? 'str' : 'link',
                'caption' => 'Email info',
                'value'   => $this->curUser->censorEmail,
                'href'    => 'mailto:' . $this->curUser->censorEmail,
            ];
        }
        if (
            $this->rules->viewIP
            && false !== \filter_var($this->curUser->registration_ip, \FILTER_VALIDATE_IP)
        ) {
            $fields['ip'] = [
                'class'   => ['pline'],
                'type'    => 'link',
                'caption' => 'IP',
                'value'   => $this->curUser->registration_ip,
                'href'    => $this->c->Router->link(
                    'AdminHost',
                    [
                        'ip' => $this->curUser->registration_ip,
                    ]
                ),
                'title'   => __('IP title'),
            ];
        }
        $form['sets']['private'] = [
            'class'  => ['data'],
            'legend' => __('Private information'),
            'fields' => $fields,
        ];


        return $form;
    }
}
