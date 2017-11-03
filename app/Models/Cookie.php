<?php

namespace ForkBB\Models;

use ForkBB\Models\Model;
use ForkBB\Models\User;
use ForkBB\Core\Container;
use RuntimeException;

class Cookie extends Model
{
    const NAME = 'user';

    /**
     * Флаг запрета записи свойств
     * @var bool
     */
    protected $noSet = false;

    /**
     * Конструктор
     *
     * @param array $options
     * @param Container $container
     */
    public function __construct(array $options, Container $container)
    {
        parent::__construct($container);
        $this->a = $options + [
            'prefix' => '',
            'domain' => '',
            'path'   => '',
            'secure' => false,
            'time'   => 31536000,
            'key1'   => 'key1',
            'key2'   => 'key2',
        ];
        $this->init();
        $this->noSet = true;
    }

    /**
     * Устанавливает куку
     *
     * @param string $name
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     *
     * @return bool
     */
    public function set($name, $value, $expire = 0, $path = null, $domain = null, $secure = false, $httponly = true)
    {
        $result = setcookie(
            $this->prefix . $name,
            $value,
            $expire,
            $path ?: $this->path,
            $domain ?: $this->domain,
            (bool) $this->secure || (bool) $secure,
            (bool) $httponly
        );
        if ($result) {
            $_COOKIE[$this->prefix . $name] = $value;
        }
        return $result;
    }

    /**
     * Получает значение куки
     *
     * @param string $name
     * @param mixed $default
     *
     * @return mixed
     */
    public function get($name, $default = null)
    {
        $name = $this->prefix . $name;
        return isset($_COOKIE[$name]) ? $this->c->Secury->replInvalidChars($_COOKIE[$name]) : $default;
    }

    /**
     * Удаляет куку
     *
     * @param string $name
     * @param string $path
     * @param string $domain
     *
     * @return bool
     */
    public function delete($name, $path = null, $domain = null)
    {
        $result = $this->set($name, '', 1, $path, $domain);
        if ($result) {
            unset($_COOKIE[$this->prefix . $name]);
        }
        return $result;
    }

    /**
     * Выделение данных из куки аутентификации
     */
    protected function init()
    {
        $ckUser = $this->get(self::NAME);

        if (null === $ckUser
            || ! preg_match('%^(\-)?(\d{1,10})_(\d{10})_([a-f\d]{32,})_([a-f\d]{32,})$%Di', $ckUser, $ms)
        ) {
            return;
        }

        if (2 > $ms[2]
            || time() > $ms[3]
            || ! hash_equals(
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
     * Проверка хэша пароля
     *
     * @param User $user
     *
     * @return bool
     */
    public function verifyUser(User $user)
    {
        return $this->uId === (int) $user->id
            && hash_equals(
                   (string) $this->uHash,
                   $this->c->Secury->hmac($user->password . $this->uExpire, $this->key2)
               );
    }

    /**
     * Установка куки аутентификации юзера
     *
     * @param User $user
     * @param bool $remember
     *
     * @return bool
     */
    public function setUser(User $user, $remember = null)
    {
        if ($user->isGuest) {
            return $this->deleteUser();
        }

        if ($remember
            || (null === $remember
                && $this->uId === (int) $user->id
                && $this->uRemember
            )
        ) {
            $expTime = time() + $this->time;
            $expire = $expTime;
            $pfx = '';
        } else {
            $expTime = time() + $this->c->config->o_timeout_visit;
            $expire = 0;
            $pfx = '-';
        }
        $passHash = $this->c->Secury->hmac($user->password . $expTime, $this->key2);
        $ckHash = $this->c->Secury->hmac($pfx . $user->id . $expTime . $passHash, $this->key1);

        return $this->set(self::NAME, $pfx . $user->id . '_' . $expTime . '_' . $passHash . '_' . $ckHash, $expire);
    }

    /**
     * Удаление куки аутентификации юзера
     *
     * @return bool
     */
    public function deleteUser()
    {
        if (null === $this->get(self::NAME)) {
            return true;
        } else {
            return $this->delete(self::NAME);
        }
    }

    /**
     * Устанавливает значение для свойства
     *
     * @param string $name
     * @param mixed $val
     *
     * @throws RuntimeException
     */
    public function __set($name, $val)
    {
        if ($this->noSet) {
            throw new RuntimeException('Model attributes in read-only mode');
        }
        parent::__set($name, $val);
    }
}
