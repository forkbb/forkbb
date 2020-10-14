<?php

declare(strict_types=1);

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;
use InvalidArgumentException;
use RuntimeException;

class UpdateLoginIpCache extends Action
{
    /**
     * Обновляет поле login_ip_cache пользователя
     */
    public function updateLoginIpCache(User $user, bool $isLogin = false): void
    {
        if ($user->isGuest) {
            throw new InvalidArgumentException('Expected user, not guest');
        }

        if (0 === $user->ip_check_type) {
            $user->login_ip_cache = '';

            return;
        }

        if (false === $isLogin) {
            $user->login_ip_cache = \bin2hex(
                \inet_pton(
                    $user->id === $this->c->user->id
                    ? $this->c->user->ip
                    : $user->registration_ip
                )
            );

            return;
        }

        $hexIp = \bin2hex(\inet_pton($this->c->user->ip)); // ???? проверка на пустоту?

        if (1 === $user->ip_check_type) {
            $ipStr = \trim(\str_replace("|{$hexIp}|", "|", "|{$user->login_ip_cache}|"), '|');
            $ipStr = \trim("{$hexIp}|{$ipStr}", '|');

            while (
                \strlen($ipStr) > 255
                && false !== ($pos = \strrpos($ipStr, '|'))
            ) {
                $ipStr = \substr($ipStr, 0, $pos);
            }

            $user->login_ip_cache = $ipStr;

            return;
        }

        if (2 === $user->ip_check_type) {
            $user->login_ip_cache = $hexIp;

            return;
        }

        throw new InvalidArgumentException('Unexpected ip check type');
    }
}
