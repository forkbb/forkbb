<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Cookie;

use ForkBB\Core\Container;
use ForkBB\Models\Model as ParentModel;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class Model extends ParentModel
{
    const NAME = 'user';

    /**
     * Флаг запрета записи свойств
     * @var bool
     */
    protected $noSet = false;

    public function __construct(array $options, Container $container)
    {
        parent::__construct($container);
        $options = $options + [
            'prefix'   => '',
            'domain'   => '',
            'path'     => '/',
            'secure'   => false,
            'samesite' => 'Lax',
            'time'     => 31536000,
            'key1'     => 'key1',
            'key2'     => 'key2',
        ];
        $this->setAttrs($options);
        $this->init();
        $this->noSet = true;
    }

    /**
     * Устанавливает куку
     */
    public function set(
        string $name,
        string $value,
        int    $expire   = 0,
        string $path     = null,
        string $domain   = null,
        bool   $secure   = false,
        bool   $httponly = true,
        string $samesite = null
    ): bool {
        $name   = $this->prefix . $name;
        $result = \setcookie(
            $name,
            $value,
            [
                'expires'  => $expire,
                'path'     => $path ?? $this->path,
                'domain'   => $domain ?? $this->domain,
                'secure'   => (bool) $this->secure || $secure,
                'httponly' => $httponly,
                'samesite' => $samesite ?? $this->samesite,
            ]
        );

        if ($result) {
            $_COOKIE[$name] = $value;
        }

        return $result;
    }

    /**
     * Получает значение куки
     */
    public function get(string $name, /* mixed */ $default = null) /* : mixed */
    {
        $name = $this->prefix . $name;

        return isset($_COOKIE[$name])
            ? $this->c->Secury->replInvalidChars($_COOKIE[$name])
            : $default;
    }

    /**
     * Удаляет куку
     */
    public function delete(string $name, string $path = null, string $domain = null): bool
    {
        $result = $this->set($name, '', 1, $path, $domain);
        if ($result) {
            unset($_COOKIE[$this->prefix . $name]);
        }

        return $result;
    }

    /**
     * Выделяет данные из куки аутентификации пользователя
     */
    protected function init(): void
    {
        $ckUser = $this->get(self::NAME);

        if (
            null === $ckUser
            || ! \preg_match('%^(\-)?(\d{1,10})_(\d{10})_([a-f\d]{32,})_([a-f\d]{32,})$%Di', $ckUser, $ms)
        ) {
            return;
        }

        if (
            2 > $ms[2]
            || \time() > $ms[3]
            || ! \hash_equals(
                    $this->c->Secury->hmac($ms[1] . $ms[2] . $ms[3] . $ms[4], $this->key1),
                    $ms[5]
                )
        ) {
            return;
        }

        $this->uRemember = empty($ms[1]);
        $this->uId       = (int) $ms[2];
        $this->uExpire   = (int) $ms[3];
        $this->uHash     = $ms[4];
    }

    /**
     * Проверяет хэш пароля пользователя
     */
    public function verifyUser(User $user): bool
    {
        return $this->uId === (int) $user->id
            && \hash_equals(
                   (string) $this->uHash,
                   $this->c->Secury->hmac($user->password . $this->uExpire, $this->key2)
               );
    }

    /**
     * Устанавливает куку аутентификации пользователя
     */
    public function setUser(User $user, bool $remember = null): bool
    {
        if ($user->isGuest) {
            return $this->deleteUser();
        }

        if (
            $remember
            || (
                null === $remember
                && $this->uId === (int) $user->id
                && $this->uRemember
            )
        ) {
            $expTime = \time() + $this->time;
            $expire  = $expTime;
            $pfx     = '';
        } else {
            $expTime = \time() + $this->c->config->o_timeout_visit;
            $expire  = 0;
            $pfx     = '-';
        }
        $passHash = $this->c->Secury->hmac($user->password . $expTime, $this->key2);
        $ckHash   = $this->c->Secury->hmac($pfx . $user->id . $expTime . $passHash, $this->key1);

        return $this->set(
            self::NAME,
            $pfx . $user->id . '_' . $expTime . '_' . $passHash . '_' . $ckHash,
            $expire
        );
    }

    /**
     * Удаляет куку аутентификации пользователя
     */
    public function deleteUser(): bool
    {
        if (null === $this->get(self::NAME)) {
            return true;
        } else {
            return $this->delete(self::NAME);
        }
    }

    /**
     * Устанавливает значение для свойства модели
     */
    public function __set(string $name, /* mixed */ $val): void
    {
        if ($this->noSet) {
            throw new RuntimeException('Model attributes in read-only mode');
        }
        parent::__set($name, $val);
    }
}
