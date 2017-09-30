<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\User;

class Register extends Page
{
    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'register';

    /**
     * Позиция для таблицы онлайн текущего пользователя
     * @var null|string
     */
    protected $onlinePos = 'register';

    /**
     * Указатель на активный пункт навигации
     * @var string
     */
    protected $index = 'register';

    /**
     * Переменная для meta name="robots"
     * @var string
     */
    protected $robots = 'noindex';

    /**
     * Обработчик регистрации
     * @retrun Page
     */
    public function reg()
    {
        $this->c->Lang->load('register');

        $v = $this->c->Validator->addValidators([
            'check_email'    => [$this, 'vCheckEmail'],
            'check_username' => [$this, 'vCheckUsername'],
        ])->setRules([
            'token'    => 'token:RegisterForm',
            'agree'    => 'required|token:Register',
            'on'       => 'integer',
            'email'    => ['required_with:on|string:trim,lower|email|check_email', __('Email')],
            'username' => ['required_with:on|string:trim|min:2|max:25|login|check_username', __('Username')],
            'password' => ['required_with:on|string|min:16|password', __('Passphrase')],
        ])->setMessages([
            'agree.required'    => ['cancel', 'cancel'],
            'agree.token'       => [__('Bad agree', $this->c->Router->link('Register')), 'w'],
            'password.password' => __('Pass format'),
            'username.login'    => __('Login format'),
        ]);

        // завершение регистрации
        if ($v->validation($_POST) && $v->on === 1) {
            return $this->regEnd($v);
        }

        $this->iswev = $v->getErrors();

        // нет согласия с правилами
        if (isset($this->iswev['cancel'])) {
            return $this->c->Redirect->setPage('Index')->setMessage(__('Reg cancel redirect'));
        }

        $this->titles[] = __('Register');
        $this->data = [
            'formAction' => $this->c->Router->link('RegisterForm'),
            'formToken' => $this->c->Csrf->create('RegisterForm'),
            'agree' => $v->agree,
            'on' => '1',
            'email' => $v->email,
            'username' => $v->username,
        ];

        return $this;
    }

    /**
     * Дополнительная проверка email
     * @param Validator $v
     * @param string $username
     * @return array
     */
    public function vCheckEmail(Validator $v, $email)
    {
        $error = false;
        // email забанен
        if ($this->c->CheckBans->isBanned(null, $email) > 0) {
            $error = __('Banned email');
        // найден хотя бы 1 юзер с таким же email
        } elseif (empty($v->getErrors()) && $this->c->UserMapper->getUser($email, 'email') !== 0) {
            $error = __('Dupe email');
        }
        return [$email, $error];
    }

    /**
     * Дополнительная проверка username
     * @param Validator $v
     * @param string $username
     * @return array
     */
    public function vCheckUsername(Validator $v, $username)
    {
        $username = preg_replace('%\s+%su', ' ', $username);
        $error = false;
        // username = Гость
        if (preg_match('%^(guest|' . preg_quote(__('Guest'), '%') . ')$%iu', $username)) {
            $error = __('Username guest');
        // цензура
        } elseif ($this->censor($username) !== $username) {
            $error = __('Username censor');
        // username забанен
        } elseif ($this->c->CheckBans->isBanned($username) > 0) {
            $error = __('Banned username');
        // есть пользователь с похожим именем
        } elseif (empty($v->getErrors()) && ! $this->c->UserMapper->isUnique($username)) {
            $error = __('Username not unique');
        }
        return [$username, $error];
    }

    /**
     * Завершение регистрации
     * @param array @data
     * @return Page
     */
    protected function regEnd(Validator $v)
    {
        if ($this->config['o_regs_verify'] == '1') {
            $groupId = $this->c->GROUP_UNVERIFIED;
            $key = 'w' . $this->c->Secury->randomPass(79);
        } else {
            $groupId = $this->config['o_default_user_group'];
            $key = null;
        }

        $newUserId = $this->c->UserMapper->newUser(new User([
            'group_id' => $groupId,
            'username' => $v->username,
            'password' => password_hash($v->password, PASSWORD_DEFAULT),
            'email' => $v->email,
            'email_confirmed' => 0,
            'activate_string' => $key,
            'u_mark_all_read' => time(),
        ], $this->c));

        // обновление статистики по пользователям
        if ($this->config['o_regs_verify'] != '1') {
            $this->c->{'users_info update'};
        }

        // уведомление о регистрации
        if ($this->config['o_regs_report'] == '1' && $this->config['o_mailing_list'] != '') {
            $tplData = [
                'fTitle' => $this->config['o_board_title'],
                'fRootLink' => $this->c->Router->link('Index'),
                'fMailer' => __('Mailer', $this->config['o_board_title']),
                'username' => $v->username,
                'userLink' => $this->c->Router->link('User', ['id' => $newUserId, 'name' => $v->username]),
            ];

            try {
                $this->c->Mail
                    ->reset()
                    ->setFolder($this->c->DIR_LANG)
                    ->setLanguage($this->config['o_default_lang'])
                    ->setTo($this->config['o_mailing_list'])
                    ->setFrom($this->config['o_webmaster_email'], __('Mailer', $this->config['o_board_title']))
                    ->setTpl('new_user.tpl', $tplData)
                    ->send();
            } catch (MailException $e) {
            //????
            }
        }

        $this->c->Lang->load('register');

        // отправка письма активации аккаунта
        if ($this->config['o_regs_verify'] == '1') {
            $hash = $this->c->Secury->hash($newUserId . $key);
            $link = $this->c->Router->link('RegActivate', ['id' => $newUserId, 'key' => $key, 'hash' => $hash]);
            $tplData = [
                'fTitle' => $this->config['o_board_title'],
                'fRootLink' => $this->c->Router->link('Index'),
                'fMailer' => __('Mailer', $this->config['o_board_title']),
                'username' => $v->username,
                'link' => $link,
            ];

            try {
                $isSent = $this->c->Mail
                    ->reset()
                    ->setFolder($this->c->DIR_LANG)
                    ->setLanguage($this->c->user->language)
                    ->setTo($v->email)
                    ->setFrom($this->config['o_webmaster_email'], __('Mailer', $this->config['o_board_title']))
                    ->setTpl('welcome.tpl', $tplData)
                    ->send();
            } catch (MailException $e) {
                $isSent = false;
            }

            // письмо активации аккаунта отправлено
            if ($isSent) {
                return $this->c->Message->message(__('Reg email', $this->config['o_admin_email']), false, 200);
            // форма сброса пароля
            } else {
                return $this->c->Auth->setIswev([
                    'w' => [
                        __('Error welcom mail', $this->config['o_admin_email']),
                    ],
                ])->forget([
                    '_email' => $v->email,
                ]);
            }
        // форма логина
        } else {
            return $this->c->Auth->setIswev([
                's' => [
                    __('Reg complete'),
                ],
            ])->login([
                '_username' => $v->username,
            ]);
        }
    }

    /**
     * Активация аккаунта
     * @param array $args
     * @return Page
     */
    public function activate(array $args)
    {
        if (! hash_equals($args['hash'], $this->c->Secury->hash($args['id'] . $args['key']))
            || ! ($user = $this->c->UserMapper->getUser($args['id'])) instanceof User
            || empty($user->activateString)
            || $user->activateString{0} !== 'w'
            || ! hash_equals($user->activateString, $args['key'])
        ) {
            return $this->c->Message->message(__('Bad request'), false);
        }

        $this->c->UserMapper->updateUser($user->id, ['group_id' => $this->config['o_default_user_group'], 'email_confirmed' => 1, 'activate_string' => null]);
        $this->c->{'users_info update'};

        $this->c->Lang->load('register');

        return $this->c->Auth->setIswev([
            's' => [
                __('Reg complete'),
            ],
        ])->login([
            '_username' => $user->username,
        ]);
    }
}
