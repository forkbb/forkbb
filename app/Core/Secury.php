<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use Normalizer;
use RuntimeException;
use UnexpectedValueException;
use InvalidArgumentException;

class Secury
{
    /**
     * Algorithm and salt for hash_hmac
     * @var array
     */
    protected $hmac;

    public function __construct(array $hmac)
    {
        if (
            empty($hmac['salt'])
            || empty($hmac['algo'])
        ) {
            throw new InvalidArgumentException('Algorithm and salt can not be empty');
        }
        if (! \in_array($hmac['algo'], \hash_hmac_algos())) {
            throw new UnexpectedValueException('Algorithm not supported');
        }
        $this->hmac = $hmac;
    }

    /**
     * Обертка для hash_hmac
     */
    public function hash(string $data): string
    {
        return $this->hmac($data, \md5(__DIR__));
    }

    /**
     * Обертка для hash_hmac
     */
    public function hmac(string $data, string $key): string
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Key can not be empty');
        }

        return \hash_hmac($this->hmac['algo'], $data, $key . $this->hmac['salt']);
    }

    /**
     * Возвращает случайную строку заданной длины состоящую из символов 0-9 и a-f
     */
    public function randomHash(int $len): string
    {
        return \substr(\bin2hex(\random_bytes(\intdiv($len, 2) + 1)), 0, $len);
    }

    /**
     * Возвращает случайную строку заданной длины состоящую из цифр, латиницы,
     * знака минус и символа подчеркивания
     */
    public function randomPass(int $len): string
    {
        $key = \random_bytes($len);
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $result = '';
        for ($i = 0; $i < $len; ++$i) {
            $result .= $chars[\ord($key[$i]) % 64];
        }

        return $result;
    }

    /**
     * Replacing invalid UTF-8 characters and remove control characters
     */
    public function replInvalidChars(/* mixed */ $data) /* : mixed */
    {
        if (\is_array($data)) {
            return \array_map([$this, 'replInvalidChars'], $data);
        } elseif (\is_int($data)) {
            return $data;
        }
        // Replacing invalid UTF-8 characters
        // slow, small memory
        //$data = mb_convert_encoding((string) $data, 'UTF-8', 'UTF-8');
        // fast, large memory
        $data = \htmlspecialchars_decode(\htmlspecialchars((string) $data, \ENT_SUBSTITUTE, 'UTF-8'));
        // Canonical Decomposition followed by Canonical Composition
        $data = Normalizer::normalize($data, Normalizer::FORM_C);
        // Remove control characters
        return \preg_replace('%(?:[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\xC2[\x80-\x9F])%', '', $data);
    }
}
