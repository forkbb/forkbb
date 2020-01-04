<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Core\Validator;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class Bans extends Admin
{
    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->aIndex = 'bans';

        $this->c->Lang->load('admin_bans');
    }

    /**
     * Кодирует данные фильтра для url
     *
     * @param array $data
     *
     * @return string
     */
    protected function encodeData(array $data)
    {
        unset($data['token']);
        $data = \base64_encode(\json_encode($data));
        $hash = $this->c->Secury->hash($data);
        return "{$data}:{$hash}";
    }

    /**
     * Декодирует данные фильтра из url
     *
     * @param string $data
     *
     * @return mixed
     */
    protected function decodeData($data)
    {
        $data = \explode(':', $data);

        if (2 !== \count($data)) {
            return false;
        }

        if (! \hash_equals($data[1], $this->c->Secury->hash($data[0]))
            || ! \is_array($data = \json_decode(\base64_decode($data[0], true), true))
        ) {
            return false;
        }

        return $data;
    }

    /**
     * Подготавливает данные для шаблона
     *
     * @param array $args
     * @param string $method
     * @param array $data
     *
     * @return Page
     */
    public function view(array $args, $method, array $data = [])
    {
        $this->nameTpl        = 'admin/bans';
        $this->formBanPage    = 'AdminBansNew';
        $this->formBanHead    = \ForkBB\__('New ban head');
        $this->formBanSubHead = \ForkBB\__('Add ban subhead');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
            ->addValidators([
            ])->addRules([
                'token'           => 'token:AdminBans',
                'username'        => 'string:trim|max:25',
                'ip'              => 'string:trim|max:40',
                'email'           => 'string:trim|max:80',
                'message'         => 'string:trim|max:255',
                'expire_1'        => 'date',
                'expire_2'        => 'date',
                'order_by'        => 'required|string|in:id,username,ip,email,expire',
                'direction'       => 'required|string|in:ASC,DESC',
            ])->addAliases([
                'username'        => 'Username label',
                'ip'              => 'IP label',
                'email'           => 'E-mail label',
                'message'         => 'Message label',
                'expire_1'        => 'Expire date label',
                'expire_2'        => 'Expire date label',
                'order_by'        => 'Order by label',
#                        'direction'       => ,
            ])->addArguments([
            ])->addMessages([
            ]);

            if ($v->validation($_POST)) {
                return $this->c->Redirect->page('AdminBansResult', ['data' => $this->encodeData($v->getData())]);
            }

            $this->fIswev = $v->getErrors();
            $this->formSearch = $this->formSearch($v->getData());
        } else {
            $this->formSearch = $this->formSearch($data);
            if (empty($data)) {
                $this->formBan    = $this->formBan();
            }
        }

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     *
     * @param array $data
     *
     * @return array
     */
    protected function formSearch(array $data = [])
    {
        $form = [
            'action' => $this->c->Router->link('AdminBans'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminBans'),
            ],
            'sets'   => [],
            'btns'   => [
                'search' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Submit search'),
                    'accesskey' => 's',
                ],
            ],
        ];
        $form['sets']['search-info'] = [
            'info' => [
                'info1' => [
                    'type'  => '', //????
                    'value' => \ForkBB\__('Ban search info'),
                ],
            ],
        ];
        $fields = [];
        $fields['username'] = [
            'type'      => 'text',
            'maxlength' => 25,
            'caption'   => \ForkBB\__('Username label'),
            'value'     => isset($data['username']) ? $data['username'] : null,
        ];
        $fields['ip'] = [
            'type'      => 'text',
            'maxlength' => 40,
            'caption'   => \ForkBB\__('IP label'),
            'value'     => isset($data['ip']) ? $data['ip'] : null,
        ];
        $fields['email'] = [
            'type'      => 'text',
            'maxlength' => 80,
            'caption'   => \ForkBB\__('E-mail label'),
            'value'     => isset($data['email']) ? $data['email'] : null,
        ];
        $fields['message'] = [
            'type'      => 'text',
            'maxlength' => 255,
            'caption'   => \ForkBB\__('Message label'),
            'value'     => isset($data['message']) ? $data['message'] : null,
        ];
        $fields['between1'] = [
            'class' => 'between',
            'type'  => 'wrap',
        ];
        $fields['expire_1'] = [
            'class'     => 'bstart',
            'type'      => 'text',
            'maxlength' => 100,
            'value'     => isset($data['expire_1']) ? $data['expire_1'] : null,
            'caption'   => \ForkBB\__('Expire date label'),
        ];
        $fields['expire_2'] = [
            'class'     => 'bend',
            'type'      => 'text',
            'maxlength' => 100,
            'value'     => isset($data['expire_2']) ? $data['expire_2'] : null,
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $form['sets']['filters'] = [
            'legend' => \ForkBB\__('Ban search subhead'),
            'fields' => $fields,
        ];

        $fields = [];
        $fields['between5'] = [
            'class' => 'between',
            'type'  => 'wrap',
        ];
        $fields['order_by'] = [
            'class'   => 'bstart',
            'type'    => 'select',
            'options' => [
                'id'       => \ForkBB\__('Order by id'),
                'username' => \ForkBB\__('Order by username'),
                'ip'       => \ForkBB\__('Order by ip'),
                'email'    => \ForkBB\__('Order by e-mail'),
                'expire'   => \ForkBB\__('Order by expire'),
            ],
            'value'   => isset($data['order_by']) ? $data['order_by'] : 'id',
            'caption' => \ForkBB\__('Order by label'),
        ];
        $fields['direction'] = [
            'class'   => 'bend',
            'type'    => 'select',
            'options' => [
                'ASC'  => \ForkBB\__('Ascending'),
                'DESC' => \ForkBB\__('Descending'),
            ],
            'value'   => isset($data['direction']) ? $data['direction'] : 'DESC',
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $form['sets']['sorting'] = [
            'legend' => \ForkBB\__('Search results legend'),
            'fields' => $fields,
        ];

        return $form;
    }

    /**
     * Подготавливает массив данных для формы
     *
     * @param array $data
     * @param array $args
     *
     * @return array
     */
    protected function formBan(array $data = [], array $args = [])
    {
        $form = [
            'action' => $this->c->Router->link($this->formBanPage, $args),
            'hidden' => [
                'token' => $this->c->Csrf->create($this->formBanPage, $args),
            ],
            'sets'   => [],
            'btns'   => [
                'submit' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Submit'),
                    'accesskey' => 's',
                ],
            ],
        ];

        if ($this->banCount < 2) {
            $fields = [];
            $fields['username'] = [
                'type'      => $this->banCount < 1 ? 'text' : 'str',
                'maxlength' => 25,
                'caption'   => \ForkBB\__('Username label'),
                'info'      => $this->banCount < 1 ? \ForkBB\__('Username help') : null,
                'value'     => isset($data['username']) ? $data['username'] : null,
            ];
            $fields['ip'] = [
                'type'      => 'text',
                'maxlength' => 255,
                'caption'   => \ForkBB\__('IP label'),
                'info'      => \ForkBB\__('IP help'),
                'value'     => isset($data['ip']) ? $data['ip'] : null,
            ];
            $fields['email'] = [
                'type'      => 'text',
                'maxlength' => 80,
                'caption'   => \ForkBB\__('E-mail label'),
                'info'      => \ForkBB\__('E-mail help'),
                'value'     => isset($data['email']) ? $data['email'] : null,
            ];
            $form['sets']['ban-attrs'] = [
                'legend' => $this->formBanSubHead,
                'fields' => $fields,
            ];
        }

        $fields = [];
        $fields['message'] = [
            'type'      => 'text',
            'maxlength' => 255,
            'caption'   => \ForkBB\__('Ban message label'),
            'info'      => \ForkBB\__('Ban message help'),
            'value'     => isset($data['message']) ? $data['message'] : null,
        ];
        $fields['expire'] = [
            'type'      => 'text',
            'maxlength' => 100,
            'caption'   => \ForkBB\__('Expire date label'),
            'info'      => \ForkBB\__('Expire date help'),
            'value'     => isset($data['expire']) ? $data['expire'] : null,
        ];
/*
        $yn     = [1 => \ForkBB\__('Yes'), 0 => \ForkBB\__('No')];
        $fields['o_default_dst'] = [
            'type'      => 'radio',
            'value'     => $config->o_default_dst,
            'values'    => $yn,
            'caption'   => \ForkBB\__('DST label'),
            'info'      => \ForkBB\__('DST help'),
        ];
*/
        $form['sets']['ban-exp'] = [
            'legend' => \ForkBB\__('Message expiry subhead'),
            'fields' => $fields,
        ];

        return $form;
    }

    /**
     * Возвращает список id банов по фильтру
     *
     * @param array $data
     *
     * @return array
     */
    protected function forFilter(array $data)
    {
        $order = [
            $data['order_by'] => $data['direction'],
        ];
        $filters = [];

        foreach ($data as $field => $value) {
            if ('' == $value || 'order_by' === $field || 'direction' === $field) {
                continue;
            }

            $key  = 1;
            $type = '=';

            if (\preg_match('%^(.+?)_(1|2)$%', $field, $matches)) {
                $type  = 'BETWEEN';
                $field = $matches[1];
                $key   = $matches[2];

                if (\is_string($value)) {
                    $value = \strtotime($value . ' UTC');
                }
            } elseif (\is_string($value)) {
                $type  = 'LIKE';
            }

            $filters[$field][0]    = $type;
            $filters[$field][$key] = $value;
        }

        return $this->c->bans->filter($filters, $order);
    }

    /**
     * Подготавливает данные для шаблона найденных банов
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function result(array $args, $method)
    {
        $data = $this->decodeData($args['data']);
        if (false === $data) {
            return $this->c->Message->message('Bad request');
        }

        $idsN = $this->forFilter($data);

        $number = \count($idsN);
        if (0 == $number) {
            $this->fIswev = ['i', \ForkBB\__('No bans found')];
            return $this->view([], 'GET', $data);
        }

        $page  = isset($args['page']) ? (int) $args['page'] : 1;
        $pages = (int) \ceil(($number ?: 1) / $this->c->config->o_disp_users);

        if ($page > $pages) {
            return $this->c->Message->message('Bad request');
        }

        $startNum = ($page - 1) * $this->c->config->o_disp_users;
        $idsN     = \array_slice($idsN, $startNum, $this->c->config->o_disp_users);
        $banList  = $this->c->bans->getList($idsN);

        $this->nameTpl    = 'admin/bans_result';
        $this->mainSuffix = '-one-column';
        $this->aCrumbs[]  = [$this->c->Router->link('AdminBansResult', ['data' => $args['data']]), \ForkBB\__('Results head')];
        $this->formResult = $this->form($banList, $startNum, $args);
        $this->pagination = $this->c->Func->paginate($pages, $page, 'AdminBansResult', ['data' => $args['data']]);

        return $this;
    }

    /**
     * Создает массив данных для формы найденных по фильтру банов
     *
     * @param array $bans
     * @param int $number
     * @param array $args
     *
     * @return array
     */
    protected function form(array $bans, $number, array $args)
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
                'class' => 'main-result',
                'type'  => 'wrap',
            ];
            $fields["l{$number}-wrap2"] = [
                'class' => 'user-result',
                'type'  => 'wrap',
            ];
            $fields["l{$number}-username"] = [
                'class'   => '' == $ban['username'] ? ['result', 'username', 'no-data'] : ['result', 'username'],
                'type'    => 'str',
                'caption' => \ForkBB\__('Results username head'),
                'value'   => $ban['username'],
            ];
            $fields["l{$number}-email"] = [
                'class'   => '' == $ban['email'] ? ['result', 'email', 'no-data'] : ['result', 'email'],
                'type'    => 'str',
                'caption' => \ForkBB\__('Results e-mail head'),
                'value'   => $ban['email'],
            ];
            $fields[] = [
                'type' => 'endwrap',
            ];
            $fields["l{$number}-ips"] = [
                'class'   => '' == $ban['ip'] ? ['result', 'ips', 'no-data'] : ['result', 'ips'],
                'type'    => 'str',
                'caption' => \ForkBB\__('Results IP address head'),
                'value'   => $ban['ip'],
            ];
            $fields["l{$number}-expire"] = [
                'class'   => empty($ban['expire']) ? ['result', 'expire', 'no-data'] : ['result', 'expire'],
                'type'    => 'str',
                'caption' => \ForkBB\__('Results expire head'),
                'value'   => empty($ban['expire']) ? '' : \ForkBB\dt($ban['expire'], true),
            ];
            $fields["l{$number}-message"] = [
                'class'   => '' == $ban['message'] ? ['result', 'message', 'no-data'] : ['result', 'message'],
                'type'    => 'str',
                'caption' => \ForkBB\__('Results message head'),
                'value'   => $ban['message'],
            ];
            $fields["l{$number}-creator"] = [
                'class'   => ['result', 'creator'],
                'type'    => '1' == $this->c->user->g_view_users && $ban['id_creator'] > 1 ? 'link' : 'str',
                'caption' => \ForkBB\__('Results banned by head'),
                'value'   => $ban['name_creator'],
                'href'    => $this->c->Router->link('User', ['id' => $ban['id_creator'], 'name' => $ban['name_creator'],]), // ????
            ];
            $fields[] = [
                'type' => 'endwrap',
            ];
            $fields["l{$number}-wrap3"] = [
                'class' => 'btns-result',
                'type'  => 'wrap',
            ];

            $arr = [
                'id' => $ban['id'],
            ];
            $fields["edit-btn{$number}"] = [
                'class'   => ['result', 'btn-edit'],
                'type'    => $ban['id'] > 0 ? 'btn' : 'str',
                'value'   => '✎',
                'caption' => \ForkBB\__('Results actions head'),
                'title'   => \ForkBB\__('Edit'),
                'link'    => $this->c->Router->link('AdminBansEdit', $arr),
            ];
            $fields["delete-btn{$number}"] = [
                'class'   => ['result', 'btn-delete'],
                'type'    => $ban['id'] > 0 ? 'btn' : 'str',
                'value'   => '❌',
                'caption' => \ForkBB\__('Results actions head'),
                'title'   => \ForkBB\__('Delete'),
                'link'    => $this->c->Router->link('AdminBansDelete', [
                    'id'    => $ban['id'],
                    'token' => $this->c->Csrf->create('AdminBansDelete', $arr),
                ]),
            ];
            $fields[] = [
                'type' => 'endwrap',
            ];

            $form['sets']["l{$number}"] = [
                'class'  => 'result',
                'legend' => $number,
                'fields' => $fields,
            ];

            ++$number;
        }

        return $form;
    }

    /**
     * Создает новый бан
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function add(array $args, $method)
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
            $tmp = $this->c->users->load($ids);

            if (\is_array($tmp) && \count($tmp) === $this->banCount) {
                $userList = $tmp; // ???? проверка массива на User'ов?
            } else {
                return $this->c->Message->message('No user ID message');
            }

            foreach ($userList as $user) {
                if ($this->c->userRules->canBanUser($user)) {
                    continue;
                }

                if ($user->isAdmin) {
                    return $this->c->Message->message(\ForkBB\__('User is admin message', $user->username));
                } elseif ($user->isAdmMod) {
                    return $this->c->Message->message(\ForkBB\__('User is mod message', $user->username));
                } elseif ($user->isGuest) { // ???? O_o
                    return $this->c->Message->message('Cannot ban guest message');
                }
            }
        }

        $this->nameTpl        = 'admin/bans';
        $this->formBanPage    = 'AdminBansNew';
        $this->formBanHead    = \ForkBB\__('New ban head');
        $this->formBanSubHead = \ForkBB\__('Add ban subhead');

        return $this->ban(true, $args, $method, $userList);
    }

    /**
     * Радактирует бан
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function edit(array $args, $method)
    {
        $this->banCount = 1;

        $id     = (int) $args['id'];
        $data = $this->c->bans->getList([$id]);

        if (! \is_array($data[$id])) {
            return $this->c->Message->message('Bad request');
        }

        $ban           = $data[$id];
        $ban['expire'] = empty($ban['expire']) ? '' : \date('Y-m-d', $ban['expire']);
        $userList      = [
            $this->c->users->create(['username' => $ban['username']]),
        ];

        $this->nameTpl        = 'admin/bans';
        $this->formBanPage    = 'AdminBansEdit';
        $this->formBanHead    = \ForkBB\__('Edit ban head');
        $this->formBanSubHead = \ForkBB\__('Edit ban subhead');

        return $this->ban(false, $args, $method, $userList, $ban);
    }

    /**
     * Обрабатывает новый/редактируемый бан
     *
     * @param bool $isNew
     * @param array $args
     * @param string $method
     * @param array $userList
     * @param array $data
     *
     * @return Page
     */
    protected function ban($isNew, array $args, $method, array $userList, array $data = [])
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
                'username'        => $this->banCount < 1 ? 'string:trim|max:25|user_ban' : 'absent',
                'ip'              => $this->banCount < 2 ? 'string:trim,spaces|max:255|ip_ban' : 'absent',
                'email'           => $this->banCount < 2 ? 'string:trim|max:80|email_ban' : 'absent',
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
                $id      = $isNew ? null : (int) $args['id'];
                $message = (string) $v->message;
                $expire  = empty($v->expire) ? 0 : \strtotime($v->expire . ' UTC');

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

                $this->c->bans->load();

                $redirect = $this->c->Redirect;

                if ($isNew && ! empty($args['uid']) && 1 === $this->banCount) {
                    $user = \reset($userList);
                    $redirect->url($user->link);
                } else {
                    $redirect->page('AdminBans');
                }

                return $redirect->message($isNew ? 'Ban added redirect' : 'Ban edited redirect');
            }

            $data         = $v->getData();
            $this->fIswev = $v->getErrors();
        }

        if (1 === $this->banCount) {
            $user = \reset($userList);
            $data['username'] = $user->username;

            if ($isNew && 'POST' !== $method) {
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

        $this->aCrumbs[]      = [$this->c->Router->link($this->formBanPage, $args), $this->formBanSubHead];
        $this->formBan        = $this->formBan($data, $args);

        return $this;
    }

    /**
     * Проверяет имя пользователя для бана
     *
     * @param Validator $v
     * @param null|string $username
     *
     * @return null|string
     */
    public function vUserBan(Validator $v, $username)
    {
        if (empty($v->getErrors()) && '' != \trim($username)) {
            $user = $this->c->users->create(['username' => $username, 'ciNameSearch' => true]);
            $user = $this->c->users->load($user);

            if (! $user instanceof User) { // ???? может ли вернутся несколько юзеров?
                $v->addError('No user message');
            } elseif (! $this->c->userRules->canBanUser($user)) {
                if ($user->isGuest) { // ???? O_o
                    $v->addError('Cannot ban guest message');
                } elseif ($user->isAdmin) {
                    $v->addError(\ForkBB\__('User is admin message', $user->username));
                } elseif ($user->isAdmMod) {
                    $v->addError(\ForkBB\__('User is mod message', $user->username));
                }
            }
        }

        return $username;
    }

    /**
     * Проверяет ip для бана
     *
     * @param Validator $v
     * @param null|string $ips
     *
     * @return null|string
     */
    public function vIpBan(Validator $v, $ips)
    {
        if ('' != \trim($ips)) {
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

                $v->addError(\ForkBB\__('Invalid IP message (%s)', $address));
                break;
            }
        }

        return $ips;
    }

    /**
     * Проверяет email для бана
     *
     * @param Validator $v
     * @param null|string $email
     *
     * @return null|string
     */
    public function vEmailBan(Validator $v, $email)
    {
        if ('' != \trim($email)) {
            $error = true;

            if (false !== \strpos($email, '@') && false !== $this->c->Mail->valid($email)) {
                $error = false;
            } elseif ('.' === $email[0] && false !== $this->c->Mail->valid('test@sub' . $email)) {
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
     *
     * @param Validator $v
     * @param null|string $expire
     *
     * @return null|string
     */
    public function vExpireBan(Validator $v, $expire)
    {
        if ('' != \trim($expire)) {
            if (\strtotime($expire . ' UTC') - \time() < 86400) {
                $v->addError('Invalid date message');
            }
        }

        return $expire;
    }

    /**
     * Проверяет, что форма не пуста
     *
     * @param Validator $v
     * @param null|string $value
     *
     * @return null|string
     */
    public function vSubmitBan(Validator $v, $value)
    {
        if ($this->banCount < 1 && '' == $v->username && '' == $v->ip && '' == $v->email) {
            $v->addError('Must enter message');
        }

        return $value;
    }

    /**
     * Удаляет бан
     *
     * @param array $args
     * @param string $method
     *
     * @throws RuntimeException
     *
     * @return Page
     */
    public function delete(array $args, $method)
    {
        if (! $this->c->Csrf->verify($args['token'], 'AdminBansDelete', $args)) {
            return $this->c->Message->message('Bad token');
        }

        $ids = [
            (int) $args['id'],
        ];
        $this->c->bans->delete($ids);

        $redirect = $this->c->Redirect;

        if (empty($args['uid'])) {
            $redirect->page('AdminBans');
        } else {
            $user = $this->c->users->load((int) $args['uid']);

            if (! $user instanceof User) {
                throw new RuntimeException('User profile not found');
            }

            $redirect->url($user->link);
        }

        return $redirect->message('Ban removed redirect');
    }
}
