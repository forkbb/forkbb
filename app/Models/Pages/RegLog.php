<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Image;
use ForkBB\Models\Page;
use ForkBB\Models\Provider\Driver;
use ForkBB\Models\User\User;
use function \ForkBB\__;

class RegLog extends Page
{
    const TIMEOUT = 5;

    /**
     * Обрабатывает нажатие одной из кнопок провайдеров
     */
    public function redirect(array $args): Page
    {
        if (
            1 !== $this->c->config->b_oauth_allow
            || empty($list = $this->c->providers->active())
        ) {
            return $this->c->Message->message('Bad request');
        }

        $rules = [
            'token' => 'token:RegLogRedirect',
        ];

        foreach ($list as $name) {
            $rules[$name] = 'string';
        }

        $v = $this->c->Validator->reset()->addRules($rules)->addArguments(['token' => $args]);

        if (
            ! $v->validation($_POST)
            || 1 !== \count($form = $v->getData(false, ['token']))
        ) {
            return $this->c->Message->message('Bad request');
        }

        return $this->c->Redirect->url($this->c->providers->init()->get(\array_key_first($form))->linkAuth($args['type']));
    }

    /**
     * Обрабатывает ответ сервера
     */
    public function callback(array $args): Page
    {
        if (
            1 !== $this->c->config->b_oauth_allow
            || empty($list = $this->c->providers->active())
            || empty($list[$args['name']])
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('admin_providers');

        $provider = $this->c->providers->init()->get($args['name']);
        $stages   = [1, 2, 3];

        foreach ($stages as $stage) {
            $result = match ($stage) {
                1 => $provider->verifyAuth(),
                2 => $provider->reqAccessToken(),
                3 => $provider->reqUserInfo(),
            };

            if (true !== $result) {
                return $this->c->Message->message($provider->error);
            }
        }

        $uid = $this->c->providerUser->findUser($provider);

        if ($this->user->isGuest) {
            return $this->byGuest($provider, $uid);
        } else {
            return $this->byUser($provider, $uid);
        }
    }

    /**
     * Обрабатыет ответ для пользователя
     */
    protected function byUser(Driver $provider, int $uid): Page
    {
        switch ($provider->stateType) {
            case 'add':
                return $this->addAccount($provider, $uid);
            default:
                return $this->c->Message->message('Bad request');
        }
    }

    /**
     * Обрабатыет ответ для гостя
     */
    protected function byGuest(Driver $provider, int $uid): Page
    {
        switch ($provider->stateType) {
            case 'reg':
            case 'auth':
                return $this->authOrReg($provider, $uid);
            default:
                return $this->c->Message->message('Bad request');
        }
    }

    /**
     * Обрабатывает добавление нового аккаунта для пользователя
     */
    protected function addAccount(Driver $provider, int $uid): Page
    {
        $redirect = $this->c->Redirect->page('EditUserOAuth', ['id' => $this->user->id]);

        // аккаунт есть и он привязан к текущему пользователю
        if ($uid === $this->user->id) {
            return $redirect->message('Already linked to you', FORK_MESS_SUCC, self::TIMEOUT);

        // аккаунт есть и он привязан к другому пользователю
        } elseif ($uid > 0) {
            return $redirect->message('Already linked to another', FORK_MESS_WARN, self::TIMEOUT);
        }

        $uid = $this->c->providerUser->findByEmail($provider->userEmail);

        // email принадлежит другому пользователю
        if (
            $uid
            && $uid !== $this->user->id
        ) {
            return $redirect->message(['Email registered by another', __($provider->name)], FORK_MESS_WARN, self::TIMEOUT);
        }

        $user = $this->c->users->loadByEmail($provider->userEmail);

        // email принадлежит другому пользователю
        if (
            $user instanceof User
            && $user !== $this->user
        ) {
            return $redirect->message(['Email registered by another', __($provider->name)], FORK_MESS_WARN, self::TIMEOUT);
        }

        if (true !== $this->c->providerUser->registration($this->user, $provider)) {
            throw new RuntimeException('Failed to insert data'); // ??????????????????????????????????????????
        }

        return $redirect->message('Account linked', FORK_MESS_SUCC);
    }

    /**
     * Обрабатывает вход/регистрацию гостя
     */
    protected function authOrReg(Driver $provider, int $uid): Page
    {
        // регистрация
        if (empty($uid)) {
            // на форуме есть пользователь с таким email
            if (
                $this->c->providerUser->findByEmail($provider->userEmail) > 0
                || $this->c->users->loadByEmail($provider->userEmail) instanceof User
            ) {
                $auth         = $this->c->Auth;
                $auth->fIswev = [FORK_MESS_INFO, ['Email message', __($provider->name)]];

                return $auth->forget([], 'GET', $provider->userEmail);
            }

            if (1 !== $this->c->config->b_regs_allow) {
                return $this->c->Message->message('No new regs');
            }

            $user = $this->c->users->create();

            $user->username        = $this->nameGenerator($provider);
            $user->password        = 'oauth_' . $this->c->Secury->randomPass(7);
            $user->group_id        = $this->c->config->i_default_user_group;
            $user->email           = $provider->userEmail;
            $user->email_confirmed = $provider->userEmailVerifed ? 1 : 0;
            $user->activate_string = '';
            $user->u_mark_all_read = \time();
            $user->email_setting   = $this->c->config->i_default_email_setting;
            $user->timezone        = $this->c->config->o_default_timezone;
            $user->language        = $this->user->language;
            $user->style           = $this->user->style;
            $user->registered      = \time();
            $user->registration_ip = $this->user->ip;
            $user->ip_check_type   = 0;
            $user->signature       = '';
            $user->location        = $provider->userLocation;
            $user->url             = $provider->userURL;

            if ($provider->userAvatar) {
                $image = $this->c->Files->uploadFromLink($provider->userAvatar);

                if ($image instanceof Image) {
                    $name   = $this->c->Secury->randomPass(8);
                    $path   = $this->c->DIR_PUBLIC . "{$this->c->config->o_avatars_dir}/{$name}.(webp|jpg|png|gif)";
                    $result = $image
                        ->rename(true)
                        ->rewrite(false)
                        ->resize($this->c->config->i_avatars_width, $this->c->config->i_avatars_height)
                        ->toFile($path, $this->c->config->i_avatars_size);

                    if (true === $result) {
                        $user->avatar = $image->name() . '.' . $image->ext();
                    } else {
                        $this->c->Log->warning('OAuth Failed image processing', [
                            'user'  => $user->fLog(),
                            'error' => $image->error(),
                        ]);
                    }
                } else {
                    $this->c->Log->warning('OAuth Avatar not image', [
                        'user'  => $user->fLog(),
                        'error' => $this->c->Files->error(),
                    ]);
                }
            }

            $this->c->users->insert($user);

            if (true !== $this->c->providerUser->registration($user, $provider)) {
                throw new RuntimeException('Failed to insert data'); // ??????????????????????????????????????????
            }

            $this->c->Log->info('OAuth Reg: ok', [
                'user'     => $user->fLog(),
                'provider' => $provider->name,
                'userInfo' => $provider->userInfo,
                'headers'  => true,
            ]);

        } else {
            $user = $this->c->users->load($uid);
        }

        // вход
        return $this->c->Auth->login([], 'POST', '', $user);
    }

    /**
     * Подбирает уникальное имя для регистрации пользователя
     */
    protected function nameGenerator(Driver $provider): string
    {
        $names = [];

        if ('' != $provider->userName) {
            $names[] = $provider->userName;
        }

        if ('' != $provider->userLogin) {
            $names[] = $provider->userLogin;
        }

        if ('' != ($tmp = (string) \strstr($provider->userEmail, '@', true))) {
            $names[] = $tmp;
        }

        $names[] = 'user' . \time();
        $v       = $this->c->Validator->reset()->addRules(['name' => 'required|string:trim|username|noURL:1']);
        $end     = '';
        $i       = 0;

        while ($i < 100) {
            foreach ($names as $name) {
                if ($v->validation(['name' => $name . $end])) {
                    return $v->name;
                }
            }

            $end = '_' . $this->c->Secury->randomHash(4);
            ++$i;
        }

        throw new RuntimeException('Failed to generate unique username');
    }
}
