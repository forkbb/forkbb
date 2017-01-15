<?php

namespace ForkBB\Core\Cookie;

class UserCookie
{
    const NAME = 'user';
    const KEY1 = 'key1';
    const KEY2 = 'key2';

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
            || ! preg_match('%^(\-)?(\d{1,10})_(\d{10})_([a-f\d]{32,})_([a-f\d]{32,})$%Di', $ckUser, $ms)
        ) {
            return;
        }

        if (2 > $ms[2]
            || time() > $ms[3]
            || ! hash_equals($this->secury->hmac($ms[1] . $ms[2] . $ms[3] . $ms[4], self::KEY1), $ms[5])
        ) {
            return;
        }

        $this->remember = empty($ms[1]);
        $this->uId      = (int) $ms[2];
        $this->expTime  = (int) $ms[3];
        $this->passHash = $ms[4];
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
        return $this->uId === (int) $id
               && hash_equals($this->passHash, $this->secury->hmac($hash . $this->expTime, self::KEY2));
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
                && $this->uId === (int) $id
                && $this->remember
            )
        ) {
            $expTime = time() + $this->timeMax;
            $expire = $expTime;
            $pfx = '';
        } else {
            $expTime = time() + $this->timeMin;
            $expire = 0;
            $pfx = '-';
        }
        $passHash = $this->secury->hmac($hash . $expTime, self::KEY2);
        $ckHash = $this->secury->hmac($pfx . $id . $expTime . $passHash, self::KEY1);

        return $this->cookie->set(self::NAME, $pfx . $id . '_' . $expTime . '_' . $passHash . '_' . $ckHash, $expire);
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
