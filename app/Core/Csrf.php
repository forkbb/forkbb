<?php

namespace ForkBB\Core;

use ForkBB\Core\Secury;

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
    public function __construct(Secury $secury, $key)
    {
        $this->secury = $secury;
        $this->key = \sha1($key);
    }

    /**
     * Возвращает csrf токен
     *
     * @param string $marker
     * @param array $args
     * @param string|int $time
     *
     * @return string
     */
    public function create($marker, array $args = [], $time = null)
    {
         unset($args['token'], $args['#']);
         \ksort($args);
         $marker .= '|';
         foreach ($args as $key => $value) {
             $marker .= $key . '|' . (string) $value . '|';
         }
         $time = $time ?: \time();
         return $this->secury->hmac($marker, $time . $this->key) . 'f' . $time;
    }

    /**
     * Проверка токена
     *
     * @param mixed $token
     * @param string $marker
     * @param array $args
     *
     * @return bool
     */
    public function verify($token, $marker, array $args = [])
    {
        return \is_string($token)
            && \preg_match('%f(\d+)$%D', $token, $matches)
            && $matches[1] < \time()
            && $matches[1] + 1800 > \time()
            && \hash_equals($this->create($marker, $args, $matches[1]), $token);
    }
}
