<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\User\User;
use RuntimeException;
use function \ForkBB\__;
use function \ForkBB\dt;

class Bans extends Admin
{
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->aIndex = 'bans';

        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_bans');
    }

    /**
     * Кодирует данные фильтра для url
     */
    protected function encodeData(array $data): string
    {
        unset($data['token']);

        $data = \base64_encode(\json_encode($data, FORK_JSON_ENCODE));
        $hash = $this->c->Secury->hash($data);

        return "{$data}:{$hash}";
    }

    /**
     * Декодирует данные фильтра из url
     */
    protected function decodeData(string $data): array|false
    {
        $data = \explode(':', $data);

        if (2 !== \count($data)) {
            return false;
        }

        if (
            ! \hash_equals($data[1], $this->c->Secury->hash($data[0]))
            || ! \is_array($data = \json_decode(\base64_decode($data[0], true), true))
        ) {
            return false;
        }

        return $data;
    }

    /**
     * Подготавливает данные для шаблона
     */
    public function view(array $args, string $method, array $data = []): Page
    {
        $this->nameTpl        = 'admin/bans';
        $this->formBanPage    = 'AdminBansNew';
        $this->formBanHead    = 'New ban head';
        $this->formBanSubHead = 'Add ban subhead';

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
            ->addValidators([
            ])->addRules([
                'token'      => 'token:AdminBans',
                's_username' => 'string:trim,null|max:190',
                's_ip'       => 'string:trim,null|max:40',
                's_email'    => 'string:trim,null|max:' . $this->c->MAX_EMAIL_LENGTH,
                's_message'  => 'string:trim,null|max:255',
                's_expire_1' => 'date',
                's_expire_2' => 'date',
                'order_by'   => 'required|string|in:id,username,ip,email,expire',
                'direction'  => 'required|string|in:ASC,DESC',
            ])->addAliases([
                's_username' => 'Username label',
                's_ip'       => 'IP label',
                's_email'    => 'E-mail label',
                's_message'  => 'Message label',
                's_expire_1' => 'Expire date label',
                's_expire_2' => 'Expire date label',
                'order_by'   => 'Order by label',
            ])->addArguments([
            ])->addMessages([
            ]);

            if ($v->validation($_POST)) {
                return $this->c->Redirect->page('AdminBansResult', ['data' => $this->encodeData($v->getData())]);
            }

            $this->fIswev     = $v->getErrors();
            $this->formSearch = $this->formSearch($v->getData());

        } else {
            $this->formSearch = $this->formSearch($data);

            if (empty($data)) {
                $this->formBan = $this->formBan();
            }
        }

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formSearch(array $data = []): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminBans'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminBans'),
            ],
            'sets'   => [],
            'btns'   => [
                'search' => [
                    'type'  => 'submit',
                    'value' => __('Submit search'),
                ],
            ],
        ];
        $form['sets']['search-info'] = [
            'inform' => [
                [
                    'message' => 'Ban search info',
                ],
            ],
        ];
        $fields = [];
        $fields['s_username'] = [
            'type'      => 'text',
            'maxlength' => '190',
            'caption'   => 'Username label',
            'value'     => $data['s_username'] ?? null,
        ];
        $fields['s_ip'] = [
            'type'      => 'text',
            'maxlength' => '40',
            'caption'   => 'IP label',
            'value'     => $data['s_ip'] ?? null,
        ];
        $fields['s_email'] = [
            'type'      => 'text',
            'maxlength' => (string) $this->c->MAX_EMAIL_LENGTH,
            'caption'   => 'E-mail label',
            'value'     => $data['s_email'] ?? null,
        ];
        $fields['s_message'] = [
            'type'      => 'text',
            'maxlength' => '255',
            'caption'   => 'Message label',
            'value'     => $data['s_message'] ?? null,
        ];
        $fields['between1'] = [
            'class' => ['between'],
            'type'  => 'wrap',
        ];
        $fields['s_expire_1'] = [
            'class'     => ['bstart'],
            'type'      => 'datetime-local',
            'value'     => $data['s_expire_1'] ?? null,
            'caption'   => 'Expire date label',
            'step'      => '1',
        ];
        $fields['s_expire_2'] = [
            'class'     => ['bend'],
            'type'      => 'datetime-local',
            'value'     => $data['s_expire_2'] ?? null,
            'step'      => '1',
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $form['sets']['filters'] = [
            'legend' => 'Ban search subhead',
            'fields' => $fields,
        ];

        $fields = [];
        $fields['between5'] = [
            'class' => ['between'],
            'type'  => 'wrap',
        ];
        $fields['order_by'] = [
            'class'   => ['bstart'],
            'type'    => 'select',
            'options' => [
                'id'       => __('Order by id'),
                'username' => __('Order by username'),
                'ip'       => __('Order by ip'),
                'email'    => __('Order by e-mail'),
                'expire'   => __('Order by expire'),
            ],
            'value'   => $data['order_by'] ?? 'id',
            'caption' => 'Order by label',
        ];
        $fields['direction'] = [
            'class'   => ['bend'],
            'type'    => 'select',
            'options' => [
                'ASC'  => __('Ascending'),
                'DESC' => __('Descending'),
            ],
            'value'   => $data['direction'] ?? 'DESC',
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $form['sets']['sorting'] = [
            'legend' => 'Search results legend',
            'fields' => $fields,
        ];

        return $form;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formBan(array $data = [], array $args = []): array
    {
        $form = [
            'action' => $this->c->Router->link($this->formBanPage, $args),
            'hidden' => [
                'token' => $this->c->Csrf->create($this->formBanPage, $args),
            ],
            'sets'   => [],
            'btns'   => [
                'submit' => [
                    'type'  => 'submit',
                    'value' => __('AdminBansNew' === $this->formBanPage ? 'Add' : 'Update'),
                ],
            ],
        ];

        if ($this->banCount < 2) {
            $fields = [];
            $fields['username'] = [
                'type'      => $this->banCount < 1 ? 'text' : 'str',
                'maxlength' => '190',
                'caption'   => 'Username label',
                'help'      => $this->banCount < 1 ? 'Username help' : null,
                'value'     => $data['username'] ?? null,
            ];
            $fields['ip'] = [
                'type'      => 'text',
                'maxlength' => '255',
                'caption'   => 'IP label',
                'help'      => 'IP help',
                'value'     => $data['ip'] ?? null,
            ];
            $fields['email'] = [
                'type'      => 'text',
                'maxlength' => (string) $this->c->MAX_EMAIL_LENGTH,
                'caption'   => 'E-mail label',
                'help'      => 'E-mail help',
                'value'     => $data['email'] ?? null,
            ];
            $form['sets']['ban-attrs'] = [
                'legend' => $this->formBanSubHead,
                'fields' => $fields,
            ];
        }

        $fields = [];
        $fields['message'] = [
            'type'      => 'text',
            'maxlength' => '255',
            'caption'   => 'Ban message label',
            'help'      => 'Ban message help',
            'value'     => $data['message'] ?? null,
        ];
        $fields['expire'] = [
            'type'      => 'datetime-local',
            'caption'   => 'Expire date label',
            'help'      => 'Expire date help',
            'value'     => $data['expire'] ?? null,
            'step'      => '1',
        ];
        $form['sets']['ban-exp'] = [
            'legend' => 'Message expiry subhead',
            'fields' => $fields,
        ];

        return $form;
    }

    /**
     * Возвращает список id банов по фильтру
     */
    protected function forFilter(array $data): array
    {
        $order = [
            $data['order_by'] => $data['direction'],
        ];
        $filters  = [];
        $usedLike = false;

        foreach ($data as $field => $value) {
            if (
                '' == $value
                || 'order_by' === $field
                || 'direction' === $field
            ) {
                continue;
            }

            $field = \substr($field, 2);

            $key  = 1;
            $type = '=';

            if (\preg_match('%^(.+?)_(1|2)$%', $field, $matches)) {
                $type  = 'BETWEEN';
                $field = $matches[1];
                $key   = $matches[2];

                if (\is_string($value)) {
                    $value = $this->c->Func->dateToTime($value);
                }

            } elseif (\is_string($value)) {
                $type     = 'LIKE';
                $usedLike = true;
            }

            $filters[$field][0]    = $type;
            $filters[$field][$key] = $value;
        }

        if (
            $usedLike
            && ! $this->c->config->insensitive()
        ) {
            $this->fIswev = [FORK_MESS_INFO, 'The search may be case sensitive'];
        }

        return $this->c->bans->filter($filters, $order);
    }

    /**
     * Подготавливает данные для шаблона найденных банов
     */
    public function result(array $args, string $method): Page
    {
        $data = $this->decodeData($args['data']);

        if (false === $data) {
            return $this->c->Message->message('Bad request');
        }

        $idsN   = $this->forFilter($data);
        $number = \count($idsN);

        if (0 == $number) {
            $this->fIswev = [FORK_MESS_INFO, 'No bans found'];

            return $this->view([], 'GET', $data);
        }

        $page  = $args['page'] ?? 1;
        $pages = (int) \ceil(($number ?: 1) / $this->c->config->i_disp_users);

        if ($page > $pages) {
            return $this->c->Message->message('Not Found', true, 404);
        }

        $startNum = ($page - 1) * $this->c->config->i_disp_users;
        $idsN     = \array_slice($idsN, $startNum, $this->c->config->i_disp_users);
        $banList  = $this->c->bans->getList($idsN);

        $this->nameTpl    = 'admin/bans_result';
        $this->mainSuffix = '-one-column';
        $this->aCrumbs[]  = [
            $this->c->Router->link(
                'AdminBansResult',
                [
                    'data' => $args['data'],
                ]
            ),
            'Results head',
        ];
        $this->formResult = $this->form($banList, $startNum, $args);
        $this->pagination = $this->c->Func->paginate(
            $pages,
            $page,
            'AdminBansResult',
            [
                'data' => $args['data'],
            ]
        );

        return $this;
    }

    /**
     * Создает массив данных для формы найденных по фильтру банов
     */
    protected function form(array $bans, int $number, array $args): array
    {
        $form = [
            'sets'   => [],
        ];
        // пустой бан для заголовка
        \array_unshift($bans, [
            'id'           => 0,
            'username'     => '',
            'ip'           => '',
            'email'        => '',
            'message'      => '',
            'expire'       => 0,
            'id_creator'   => -1,
            'name_creator' => '',
        ]);

        foreach ($bans as $ban) {
            if (! \is_array($ban)) {
                continue; // ????
            }

            $fields = [];
            $fields["l{$number}-wrap1"] = [
                'class' => ['main-result'],
                'type'  => 'wrap',
            ];
            $fields["l{$number}-wrap2"] = [
                'class' => ['user-result'],
                'type'  => 'wrap',
            ];
            $fields["l{$number}-username"] = [
                'class'   => '' == $ban['username'] ? ['result', 'username', 'no-data'] : ['result', 'username'],
                'type'    => 'str',
                'caption' => 'Results username head',
                'value'   => $ban['username'],
            ];
            $fields["l{$number}-email"] = [
                'class'   => '' == $ban['email'] ? ['result', 'email', 'no-data'] : ['result', 'email'],
                'type'    => 'str',
                'caption' => 'Results e-mail head',
                'value'   => $ban['email'],
            ];
            $fields[] = [
                'type' => 'endwrap',
            ];
            $fields["l{$number}-ips"] = [
                'class'   => '' == $ban['ip'] ? ['result', 'ips', 'no-data'] : ['result', 'ips'],
                'type'    => 'str',
                'caption' => 'Results IP address head',
                'value'   => $ban['ip'],
            ];
            $fields["l{$number}-expire"] = [
                'class'   => empty($ban['expire']) ? ['result', 'expire', 'no-data'] : ['result', 'expire'],
                'type'    => 'str',
                'caption' => 'Results expire head',
                'value'   => empty($ban['expire']) ? '' : dt($ban['expire']),
            ];
            $fields["l{$number}-message"] = [
                'class'   => '' == $ban['message'] ? ['result', 'message', 'no-data'] : ['result', 'message'],
                'type'    => 'str',
                'caption' => 'Results message head',
                'value'   => $ban['message'],
            ];
            $fields["l{$number}-creator"] = [
                'class'   => ['result', 'creator'],
                'type'    => 1 === $this->user->g_view_users && $ban['id_creator'] > 0 ? 'link' : 'str',
                'caption' => 'Results banned by head',
                'value'   => $ban['name_creator'],
                'href'    => $this->c->Router->link(
                    'User',
                    [
                        'id'   => $ban['id_creator'],
                        'name' => $this->c->Func->friendly($ban['name_creator']),
                    ]
                ),
            ];
            $fields[] = [
                'type' => 'endwrap',
            ];
            $fields["l{$number}-wrap3"] = [
                'class' => ['btns-result'],
                'type'  => 'wrap',
            ];

            $arr = [
                'id' => $ban['id'],
            ];
            $fields["edit-btn{$number}"] = [
                'class'   => ['result', 'btn-edit'],
                'type'    => $ban['id'] > 0 ? 'btn' : 'str',
                'value'   => '✎',
                'caption' => 'Results actions head',
                'title'   => __('Edit'),
                'href'    => $this->c->Router->link(
                    'AdminBansEdit',
                    $arr
                ),
            ];
            $fields["delete-btn{$number}"] = [
                'class'   => ['result', 'btn-delete'],
                'type'    => $ban['id'] > 0 ? 'btn' : 'str',
                'value'   => '❌',
                'caption' => 'Results actions head',
                'title'   => __('Delete'),
                'href'    => $this->c->Router->link(
                    'AdminBansDelete',
                    [
                        'id' => $ban['id'],
                    ]
                ),
            ];
            $fields[] = [
                'type' => 'endwrap',
            ];

            $form['sets']["l{$number}"] = [
                'class'  => ['result'],
                'legend' => (string) $number,
                'fields' => $fields,
            ];

            ++$number;
        }

        return $form;
    }

    /**
     * Создает новый бан
     */
    public function add(array $args, string $method): Page
    {
        $this->banCount = 0;
        $userList       = [];

        if (! empty($args['ids'])) {
            $ids = \explode('-', $args['ids']);

            foreach ($ids as &$id) {
                if (! \preg_match('%^([2-9]|[1-9]\d+)$%D', $id)) {
                    return $this->c->Message->message('Bad request');
                }

                $id = (int) $id;
            }

            unset($id);

            $this->banCount = \count($ids);
            $tmp = $this->c->users->loadByIds($ids);

            if (
                \is_array($tmp)
                && \count($tmp) === $this->banCount
            ) {
                $userList = $tmp; // ???? проверка массива на User'ов?

            } else {
                return $this->c->Message->message('No user ID message');
            }

            foreach ($userList as $user) {
                if ($this->c->bans->banFromName($user->username) > 0) {
                    return $this->c->Message->message(['User is ban', $user->username]);
                }

                if ($this->userRules->canBanUser($user)) {
                    continue;
                }

                if ($user->isAdmin) {
                    return $this->c->Message->message(['User is admin message', $user->username]);

                } elseif ($user->isAdmMod) {
                    return $this->c->Message->message(['User is mod message', $user->username]);

                } elseif ($user->isGuest) {
                    return $this->c->Message->message('Cannot ban guest message');
                }
            }
        }

        $this->nameTpl        = 'admin/bans';
        $this->formBanPage    = 'AdminBansNew';
        $this->formBanHead    = 'New ban head';
        $this->formBanSubHead = 'Add ban subhead';

        return $this->ban(true, $args, $method, $userList);
    }

    /**
     * Радактирует бан
     */
    public function edit(array $args, string $method): Page
    {
        $this->banCount = 1;

        $id   = $args['id'];
        $data = $this->c->bans->getList([$id]);

        if (! \is_array($data[$id])) {
            return $this->c->Message->message('Bad request');
        }

        $ban           = $data[$id];
        $ban['expire'] = empty($ban['expire']) ? '' : $this->c->Func->timeToDate($ban['expire']);
        $userList      = [
            $this->c->users->create(['username' => $ban['username']]),
        ];

        $this->nameTpl        = 'admin/bans';
        $this->formBanPage    = 'AdminBansEdit';
        $this->formBanHead    = 'Edit ban head';
        $this->formBanSubHead = 'Edit ban subhead';

        return $this->ban(false, $args, $method, $userList, $ban);
    }

    /**
     * Обрабатывает новый/редактируемый бан
     */
    protected function ban(bool $isNew, array $args, string $method, array $userList, array $data = []): Page
    {
        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
            ->addValidators([
                'user_ban'        => [$this, 'vUserBan'],
                'ip_ban'          => [$this, 'vIpBan'],
                'email_ban'       => [$this, 'vEmailBan'],
                'expire_ban'      => [$this, 'vExpireBan'],
                'submit_ban'      => [$this, 'vSubmitBan'],
            ])->addRules([
                'token'           => 'token:' . $this->formBanPage,
                'username'        => $this->banCount < 1 ? 'string:trim|max:190|user_ban' : 'absent',
                'ip'              => $this->banCount < 2 ? 'string:trim,spaces|max:255|ip_ban' : 'absent',
                'email'           => $this->banCount < 2 ? 'string:trim|max:' . $this->c->MAX_EMAIL_LENGTH . '|email_ban' : 'absent',
                'message'         => 'string:trim|max:255',
                'expire'          => 'date|expire_ban',
                'submit'          => 'required|submit_ban',
            ])->addAliases([
                'username'        => 'Username label',
                'ip'              => 'IP label',
                'email'           => 'E-mail label',
                'message'         => 'Ban message label',
                'expire'          => 'Expire date label',
            ])->addArguments([
                'token'           => $args,
            ])->addMessages([
            ]);

            if ($v->validation($_POST)) {
                $action  = $isNew ? 'insert' : 'update';
                $id      = $isNew ? null : $args['id'];
                $message = (string) $v->message;
                $expire  = empty($v->expire) ? 0 : $this->c->Func->dateToTime($v->expire);

                if ($this->banCount < 1) {
                    $userList = [false];
                }

                foreach ($userList as $user) {
                    $this->c->bans->$action([
                        'id'       => $id,
                        'username' => $this->banCount < 1 ? (string) $v->username : $user->username,
                        'ip'       => $this->banCount < 2 ? (string) $v->ip : '',
                        'email'    => $this->banCount < 2 ? (string) $v->email : $user->email,
                        'message'  => $message,
                        'expire'   => $expire,
                    ]);
                }

                $this->c->bans->reset();

                $redirect = $this->c->Redirect;

                if (
                    $isNew
                    && ! empty($args['uid'])
                    && 1 === $this->banCount
                ) {
                    $user = \reset($userList);
                    $redirect->url($user->link);

                } else {
                    $redirect->page('AdminBans');
                }

                return $redirect->message($isNew ? 'Ban added redirect' : 'Ban edited redirect', FORK_MESS_SUCC);
            }

            $data         = $v->getData();
            $this->fIswev = $v->getErrors();
        }

        if (1 === $this->banCount) {
            $user = \reset($userList);
            $data['username'] = $user->username;

            if (
                $isNew
                && 'POST' !== $method
            ) {
                $data['email'] = (string) $user->email;

                $ip  = (string) $user->registration_ip;
                $ips = $this->c->posts->userStat($user->id);

                unset($ips[$ip]);

                foreach ($ips as $curIp => $cur) {
                    if (\strlen($ip . ' ' . $curIp) > 255) {
                        break;
                    }

                    $ip .= ' ' . $curIp;
                }

                $data['ip'] = $ip;
            }
        }

        $this->aCrumbs[] = [$this->c->Router->link($this->formBanPage, $args), $this->formBanSubHead];
        $this->formBan   = $this->formBan($data, $args);

        return $this;
    }

    /**
     * Проверяет имя пользователя для бана
     */
    public function vUserBan(Validator $v, string $username): string
    {
        if (
            empty($v->getErrors())
            && '' !== \trim($username)
        ) {
            $user = $this->c->users->loadByName($username, true);

            if (! $user instanceof User) { // ???? может ли вернутся несколько юзеров?
                $v->addError('No user message');

            } elseif ($this->c->bans->banFromName($user->username) > 0) {
                $v->addError(['User is ban', $user->username]);

            } elseif (! $this->userRules->canBanUser($user)) {
                if ($user->isGuest) {
                    $v->addError('Cannot ban guest message');

                } elseif ($user->isAdmin) {
                    $v->addError(['User is admin message', $user->username]);

                } elseif ($user->isAdmMod) {
                    $v->addError(['User is mod message', $user->username]);
                }
            }
        }

        return $username;
    }

    /**
     * Проверяет ip для бана
     */
    public function vIpBan(Validator $v, string $ips): string
    {
        if ('' !== \trim($ips)) {
            $ending6   = ['', '::'];
            $ending4   = ['', '.255', '.255.255', '.255.255.255'];
            $addresses = \explode(' ', $ips);

            foreach ($addresses as $address) {
                if (\preg_match('%[:a-fA-F]|\d{4}%', $address)) {
                    foreach ($ending6 as $ending) {
                        if (false !== \filter_var($address . $ending, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
                            continue 2;
                        }
                    }

                } else {
                    foreach ($ending4 as $ending) {
                        if (false !== \filter_var($address . $ending, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
                            continue 2;
                        }
                    }
                }

                $v->addError(['Invalid IP message (%s)', $address]);

                break;
            }
        }

        return $ips;
    }

    /**
     * Проверяет email для бана
     */
    public function vEmailBan(Validator $v, string $email): string
    {
        if ('' !== \trim($email)) {
            $error = true;

            if (
                false !== \strpos($email, '@')
                && false !== $this->c->Mail->valid($email)
            ) {
                $error = false;

            } elseif (
                '.' === $email[0]
                && false !== $this->c->Mail->valid('test@sub' . $email)
            ) {
                $error = false;

            } elseif (false !== $this->c->Mail->valid('test@' . $email)) {
                $error = false;
            }

            if ($error) {
                $v->addError('Invalid e-mail message');
            }
        }

        return $email;
    }

    /**
     * Проверяет дату окончания для бана
     */
    public function vExpireBan(Validator $v, ?string $expire): ?string
    {
        if (
            null !== $expire
            && '' !== \trim($expire)
        ) {
            if ($this->c->Func->dateToTime($expire) - \time() < 86400) {
                $v->addError('Invalid date message');
            }
        }

        return $expire;
    }

    /**
     * Проверяет, что форма не пуста
     */
    public function vSubmitBan(Validator $v, $value)
    {
        if (
            $this->banCount < 1
            && '' == $v->username
            && '' == $v->ip
            && '' == $v->email
        ) {
            $v->addError('Must enter message');
        }

        return $value;
    }

    /**
     * Удаляет бан
     */
    public function delete(array $args, string $method): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'AdminBansDelete', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
        }

        $this->c->bans->delete($args['id']);

        $redirect = $this->c->Redirect;

        if (empty($args['uid'])) {
            $redirect->page('AdminBans');

        } else {
            $user = $this->c->users->load($args['uid']);

            if (! $user instanceof User) {
                throw new RuntimeException('User profile not found');
            }

            $redirect->url($user->link);
        }

        return $redirect->message('Ban removed redirect', FORK_MESS_SUCC);
    }
}
