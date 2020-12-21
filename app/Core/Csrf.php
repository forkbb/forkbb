<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

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
     * @var ?string
     */
    protected $error;

    public function __construct(Secury $secury, string $key)
    {
        $this->secury = $secury;
        $this->key = \sha1($key);
    }

    /**
     * Возвращает csrf токен
     */
    public function create(string $marker, array $args = [], /* string|int */ $time = null): string
    {
        $this->error = null;

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
     */
    public function verify($token, string $marker, array $args = []): bool
    {
        $this->error = null;
        $now         = \time();
        $matches     = null;

        $result = \is_string($token)
            && \preg_match('%f(\d+)$%D', $token, $matches)
            && $matches[1] + 0 < $now
            && $matches[1] + 1800 >= $now
            && \hash_equals($this->create($marker, $args, $matches[1]), $token);

        if (! $result) {
            if (
                isset($matches[1])
                && $matches[1] + 1800 < $now
            ) {
                $this->error = 'Expired token';
            } else {
                $this->error = 'Bad token';
            }
        }

        return $result;
    }

    /**
     * Возвращает ошибку из метода verify
     */
    public function getError(): ?string
    {
        return $this->error;
    }
}
