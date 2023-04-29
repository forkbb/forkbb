<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\BanList;

use ForkBB\Models\Model;
use InvalidArgumentException;
use RuntimeException;

class BanList extends Model
{
    const CACHE_KEY = 'banlist';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'BanList';

    /**
     * Загружает список банов из кеша/БД
     * Создает кеш
     */
    public function init(): BanList
    {
        $list = $this->c->Cache->get(self::CACHE_KEY);

        if (! isset($list['banList'], $list['userList'], $list['emailList'], $list['ipList'], $list['firstExpire'])) {
            $list = $this->load();

            if (true !== $this->c->Cache->set(self::CACHE_KEY, $list)) {
                throw new RuntimeException('Unable to write value to cache - banlist');
            }
        }

        $this->banList     = $list['banList'];
        $this->userList    = $list['userList'];
        $this->emailList   = $list['emailList'];
        $this->ipList      = $list['ipList'];
        $this->firstExpire = $list['firstExpire'];

        return $this;
    }

    /**
     * Фильтрует значение
     */
    public function trimToNull(?string $val, bool $toLower = false): ?string
    {
        $val = \trim($val ?? '');

        if ('' == $val) {
            return null;
        } elseif ($toLower) {
            return \mb_strtolower($val, 'UTF-8');
        } else {
            return $val;
        }
    }

    /**
     * Переводит ip/ip-подсеть в hex представление
     */
    public function ip2hex(string $ip): string
    {
        $bin = \inet_pton($ip);

        if (false === $bin) {
            if (\preg_match('%[:a-fA-F]|\d{4}%', $ip)) {
                $result = '';
                // 0000-ffff
                foreach (\explode(':', $ip) as $cur) {
                    $result .= \substr('0000' . \strtolower($cur), -4);
                }
            } else {
                $result = '-';
                // 00-ff
                foreach (\explode('.', $ip) as $cur) {
                    $result .= \sprintf('%02x', $cur);
                }
            }

            return $result;
        } else {
            // The hyphen is needed for the joint sorting ipv4 and ipv6
            return (isset($bin[4]) ? '' : '-') . \bin2hex($bin);
        }
    }

    /**
     * Сбрасывает кеш банов
     */
    public function reset(): BanList
    {
        if (true !== $this->c->Cache->delete(self::CACHE_KEY)) {
            throw new RuntimeException('Unable to remove key from cache - banlist');
        }

        return $this;
    }

    /**
     * Выдает номер бана по имени или 0
     */
    public function banFromName(?string $name): int
    {
        $name = $this->trimToNull($name, true);

        if (null === $name) {
            throw new InvalidArgumentException('Expected username, not empty string');
        }

        return $this->userList[$name] ?? 0;
    }
}
