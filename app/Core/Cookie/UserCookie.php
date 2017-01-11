<?php

namespace ForkBB\Core\Cookie;

class UserCookie
{
    const NAME = 'user';

    /**
     * @var Secury
     */
    protected $secury;

    /**
     * @var Cookie
     */
    protected $cookie;

    /**
     * Флаг указывающий на режим "запомнить меня"
     * @var bool
     */
    protected $remember;

    /**
     * Номер юзера из куки аутентификации
     * @var int
     */
    protected $uId;

    /**
     * Время "протухания" куки аутентификации
     * @var int
     */
    protected $expTime;

    /**
     * Хэш хэша пароля юзера из куки аутентификации
     * @var string
     */
    protected $passHash;

    /**
     * Период действия куки аутентификации в секундах для режима "не запоминать меня"
     * @var int
     */
    protected $timeMin;

    /**
     * Период действия куки аутентификации в секундах для режима "запомнить меня"
     * @var int
     */
    protected $timeMax;

    /**
     * Конструктор
     *
     * @param Secury $secury
     * @param Cookie $cookie
     * @param int $timeMin
     * @param int $timeMax
     */
    public function __construct($secury, $cookie, $timeMin, $timeMax)
    {
        $this->secury = $secury;
        $this->cookie = $cookie;
        $this->timeMin = $timeMin;
        $this->timeMax = $timeMax;

        $this->init();
    }

    /**
     * Выделение данных из куки аутентификации
     */
    protected function init()
    {
        $ckUser = $this->cookie->get(self::NAME);

        if (null === $ckUser
            || ! preg_match('%^([\+\-])(\d{1,10})\-(\d{10})\-([a-f\d]{32,})\-([a-f\d]{32,})$%Di', $ckUser, $matches)
        ) {
            return;
        }

        $remember = $matches[1] === '+';
        $uId = (int) $matches[2];
        $expTime = (int) $matches[3];
        $passHash = $matches[4];
        $ckHash = $matches[5];

        if ($uId < 2
            || $expTime < time()
            || ! hash_equals($this->secury->hmac($uId . $expTime . $passHash, 'cookie'), $ckHash)
        ) {
            return;
        }

        $this->remember = $remember;
        $this->uId = $uId;
        $this->expTime = $expTime;
        $this->passHash = $passHash;
    }

    /**
     * Возвращает id юзера из печеньки
     *
     * @return int|false
     */
    public function id()
    {
        return $this->uId ?: false;
    }

    /**
     * Проверка хэша пароля
     *
     * @param int $id
     * @param string $hash
     *
     * @return bool
     */
    public function verifyHash($id, $hash)
    {
        return (int) $id === $this->uId
               && hash_equals($this->passHash, $this->secury->hmac($hash . $this->expTime, 'password'));

    }

    /**
     * Установка печеньки аутентификации юзера
     *
     * @param int $id
     * @param string $hash
     * @param bool $remember
     *
     * @return bool
     */
    public function setUserCookie($id, $hash, $remember = null)
    {
        if ($id < 2) {
            return $this->deleteUserCookie();
        }

        if ($remember
            || (null === $remember
                && (int) $id === $this->uId
                && $this->remember
            )
        ) {
            $expTime = time() + $this->timeMax;
            $expire = $expTime;
            $prefix = '+';
        } else {
            $expTime = time() + $this->timeMin;
            $expire = 0;
            $prefix = '-';
        }
        $passHash = $this->secury->hmac($hash . $expTime, 'password');
        $ckHash = $this->secury->hmac($id . $expTime . $passHash, 'cookie');

        return $this->cookie->set(self::NAME, $prefix . $id . '-' . $expTime . '-' . $passHash . '-' . $ckHash, $expire);
    }

    /**
     * Удаление печеньки аутентификации юзера
     *
     * @return bool
     */
    public function deleteUserCookie()
    {
        if (null === $this->cookie->get(self::NAME)) {
            return true;
        } else {
            return $this->cookie->delete(self::NAME);
        }
    }
}
