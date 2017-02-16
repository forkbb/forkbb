<?php

namespace ForkBB\Models;

use ForkBB\Core\Secury;
use ForkBB\Models\User;

class Csrf
{
    /**
     * @var Secury
     */
    protected $secury;

    /**
     * @var string
     */
    protected $key;

    /**
     * Конструктор
     *
     * @param Secury $secury
     * @param User $user
     */
    public function __construct(Secury $secury, User $user)
    {
        $this->secury = $secury;
        $this->key = sha1($user->password . $user->ip . $user->id);
    }

    /**
     * Возвращает csrf токен
     * @param string $marker
     * @param array $args
     * @param string|int $time
     * @return string
     */
    public function create($marker, array $args = [], $time = null)
    {
         unset($args['token'], $args['#']);
         $data = $marker . '|' . json_encode($args);
         $time = $time ?: time();
         return $this->secury->hmac($data, $time . $this->key) . 'f' . $time;
    }

    /**
     * Проверка токена
     * @param mixed $token
     * @param string $marker
     * @param array $args
     * @return bool
     */
    public function check($token, $marker, array $args = [])
    {
        return is_string($token)
            && preg_match('%f(\d+)$%D', $token, $matches)
            && $matches[1] < time()
            && $matches[1] + 1800 > time()
            && hash_equals($this->create($marker, $args, $matches[1]), $token);
    }

}
