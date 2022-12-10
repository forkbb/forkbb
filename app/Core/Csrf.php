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
use SensitiveParameter;

class Csrf
{
    const TOKEN_LIFETIME = 1800;

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

    /**
     * @var int
     */
    protected $hashExpiration = 3600;

    public function __construct(
        Secury $secury,
        #[SensitiveParameter]
        string $key
    ) {
        $this->secury = $secury;
        $this->key    = \sha1($key);
    }

    /**
     * Устанавливает срок жизни хэша
     */
    public function setHashExpiration(int $exp): void
    {
        $this->hashExpiration = $exp > 0 ? $exp : 3600;
    }

    /**
     * Возвращает csrf токен
     */
    public function create(string $marker, array $args = [], /* string|int */ $time = null): string
    {
        $marker      = $this->argsToStr($marker, $args);
        $time        = $time ?: \time();

        return $this->secury->hmac($marker, $time . $this->key) . 's' . $time;
    }

    /**
     * Возвращает хэш
     */
    public function createHash(string $marker, array $args = [], /* string|int */ $time = null): string
    {
        $marker      = $this->argsToStr($marker, $args, ['hash']);
        $time        = $time ?: \time() + $this->hashExpiration;

        return $this->secury->hash($marker . $time) . 'e' . $time;
    }

    protected function argsToStr(string $marker, array $args, array $forDel = []): string
    {
        $marker .= '|';

        if (! empty($forDel)) {
            $args = \array_diff_key($args, \array_flip($forDel));
        }

        unset($args['token'], $args['#']);
        \ksort($args);

        foreach ($args as $key => $value) {
            if (null !== $value) {
                $marker .= "{$key}|{$value}|";
            }
        }

        return $marker;
    }

    /**
     * Проверка токена/хэша
     */
    public function verify($token, string $marker, array $args = []): bool
    {
        $this->error = 'Bad token';
        $now         = \time();
        $result      = false;

        if (
            \is_string($token)
            && \preg_match('%(e|s)(\d+)$%D', $token, $matches)
        ) {
            switch ($matches[1]) {
                // токен
                case 's':
                    if ($matches[2] + self::TOKEN_LIFETIME < $now) {
                        // просрочен
                        $this->error = 'Expired token';
                    } elseif (
                        $matches[2] + 0 <= $now
                        && \hash_equals($this->create($marker, $args, $matches[2]), $token)
                    ) {
                        $this->error = null;
                        $result      = true;
                    }

                    break;
                // хэш
                case 'e':
                    if ($matches[2] + 0 < $now) {
                        // просрочен
                        $this->error = 'Expired token';
                    } elseif (\hash_equals($this->createHash($marker, $args, $matches[2]), $token)) {
                        $this->error = null;
                        $result      = true;
                    }

                    break;
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
