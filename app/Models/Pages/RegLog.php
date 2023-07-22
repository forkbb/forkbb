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
use ForkBB\Models\Pages\RegLogTrait;
use ForkBB\Models\Provider\Driver;
use ForkBB\Models\User\User;
use function \ForkBB\__;

class RegLog extends Page
{
    use RegLogTrait;

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
            case 'auth':
            case 'reg':
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

        $user = $this->c->users->create(['email' => $provider->userEmail]);

        // этот email забанен
        if (1 === $this->c->bans->isBanned($user)) {
            return $redirect->message(['Email banned', __($provider->name)], FORK_MESS_WARN, self::TIMEOUT);
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
        // вход
        if ($uid > 0) {
            $user = $this->c->users->load($uid);

            return $this->c->Auth->login([], 'POST', '', $user);
        }

        // на форуме есть пользователь с таким email
        if (
            $this->c->providerUser->findByEmail($provider->userEmail) > 0
            || $this->c->users->loadByEmail($provider->userEmail) instanceof User
        ) {
            $auth         = $this->c->Auth;
            $auth->fIswev = [FORK_MESS_INFO, ['Email message', __($provider->name)]];

            return $auth->forget([], 'GET', $provider->userEmail);
        }

        // регистрация закрыта
        if (1 !== $this->c->config->b_regs_allow) {
            return $this->c->Message->message('No new regs');
        }

        $user = $this->c->users->create(['email' => $provider->userEmail]);

        // этот email забанен
        if (1 === $this->c->bans->isBanned($user)) {
            return $this->c->Message->message(['Email banned', __($provider->name)], false);
        }

        // продолжение регистрации начиная с согласия с правилами
        if ('reg' !== $provider->stateType) {
            $page = $this->c->Rules->confirmation();
            $form = $page->form;

            $form['hidden']['oauth'] = $this->providerToString($provider);

            $page->form   = $form;
            $page->fIswev = [FORK_MESS_INFO, 'First time Register?'];

            return $page;
        }

        return $this->c->Register->reg([], 'POST', $provider);
    }
}
