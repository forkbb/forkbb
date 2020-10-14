<?php

declare(strict_types=1);

namespace ForkBB\Models\BanList;

use ForkBB\Models\Model as ParentModel;

class Model extends ParentModel
{
    /**
     * Загружает список банов из кеша/БД
     */
    public function init(): Model
    {
        $list = $this->c->Cache->get('banlist');

        if (isset($list['banList'], $list['userList'], $list['emailList'], $list['ipList'])) {
            $this->banList   = $list['banList'];
            $this->userList  = $list['userList'];
            $this->emailList = $list['emailList'];
            $this->ipList    = $list['ipList'];
        } else {
            $this->load();
        }

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
}
