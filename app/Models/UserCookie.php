<?php

namespace ForkBB\Models;

use ForkBB\Core\Cookie;
use ForkBB\Core\Secury;

class UserCookie extends Cookie
{
    const NAME = 'user';
    const KEY1 = 'key1';
    const KEY2 = 'key2';

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
     * Время жизни куки без запоминания
     * @var int
     */
    protected $min;

    /**
     * Время жизни куки с запоминанием
     * @var int
     */
    protected $max;

    /**
     * Конструктор
     * @param Secury $secury
     * @param array $options
     * @param int $min
     * @param int $max
     */
    public function __construct(Secury $secury, array $options, $min, $max)
    {
        parent::__construct($secury, $options);
        $this->min = (int) $min;
        $this->max = (int) $max;
        $this->init();
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
     * Возвращает id юзера из куки
     * @return int|false
     */
    public function id()
    {
        return $this->uId ?: false;
    }

    /**
     * Проверка хэша пароля
     * @param int $id
     * @param string $hash
     * @return bool
     */
    public function verifyHash($id, $hash)
    {
        return $this->uId === (int) $id
               && hash_equals($this->passHash, $this->secury->hmac($hash . $this->expTime, self::KEY2));
    }

    /**
     * Установка куки аутентификации юзера
     * @param int $id
     * @param string $hash
     * @param bool $remember
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
            $expTime = time() + $this->max;
            $expire = $expTime;
            $pfx = '';
        } else {
            $expTime = time() + $this->min;
            $expire = 0;
            $pfx = '-';
        }
        $passHash = $this->secury->hmac($hash . $expTime, self::KEY2);
        $ckHash = $this->secury->hmac($pfx . $id . $expTime . $passHash, self::KEY1);

        return $this->set(self::NAME, $pfx . $id . '_' . $expTime . '_' . $passHash . '_' . $ckHash, $expire);
    }

    /**
     * Удаление куки аутентификации юзера
     * @return bool
     */
    public function deleteUserCookie()
    {
        if (null === $this->get(self::NAME)) {
            return true;
        } else {
            return $this->delete(self::NAME);
        }
    }
}
