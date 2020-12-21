<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class Current extends Action
{
    /**
     * Получает пользователя на основе куки авторизации
     * Обновляет куку аутентификации
     */
    public function current(): User
    {
        $ip     = $this->getIp();
        $cookie = $this->c->Cookie;
        $user   = $this->load((int) $cookie->uId, $ip);

        if (! $user->isGuest) {
            if (! $cookie->verifyUser($user)) {
                $user = $this->load(1, $ip);
            } elseif ($user->ip_check_type > 0) {
                $hexIp = \bin2hex(\inet_pton($ip));

                if (false === \strpos("|{$user->login_ip_cache}|", "|{$hexIp}|")) {
                    $user = $this->load(1, $ip);
                }
            }
        }

        $user->__ip        = $ip;
        $user->__userAgent = $this->getUserAgent();

        $cookie->setUser($user);

        if ($user->isGuest) {
            $user->__isBot    = $this->isBot($user->userAgent);
            $user->__timezone = $this->c->config->o_default_timezone;
            $user->__dst      = $this->c->config->o_default_dst;
            $user->__language = $this->getLangFromHTTP();
        } else {
            $user->__isBot = false;
            // Special case: We've timed out, but no other user has browsed the forums since we timed out
            if (
                $user->logged > 0
                && $user->logged < \time() - $this->c->config->o_timeout_visit
            ) {
                $this->manager->updateLastVisit($user);
            }

            $this->manager->set($user->id, $user);
        }

        return $user;
    }

    /**
     * Загружает данные из базы в модель пользователя
     */
    protected function load(int $id, string $ip): User
    {
        $data = null;

        if ($id > 1) {
            $vars = [
                ':id' => $id,
            ];
            $query = 'SELECT u.*, g.*, o.logged
                FROM ::users AS u
                INNER JOIN ::groups AS g ON u.group_id=g.g_id
                LEFT JOIN ::online AS o ON o.user_id=u.id
                WHERE u.id=?i:id';

            $data = $this->c->DB->query($query, $vars)->fetch();
        }

        if (empty($data['id'])) {
            $vars = [
                ':ip' => $ip,
            ];
            $query = 'SELECT u.*, g.*, o.logged, o.last_post, o.last_search
                FROM ::users AS u
                INNER JOIN ::groups AS g ON u.group_id=g.g_id
                LEFT JOIN ::online AS o ON (o.user_id=1 AND o.ident=?s:ip)
                WHERE u.id=1';

            $data = $this->c->DB->query($query, $vars)->fetch();

            if (empty($data['id'])) {
                throw new RuntimeException('Unable to fetch guest information. Your database must contain both a guest user and a guest user group');
            }
        }

        return $this->manager->create($data);
    }

    /**
     * Возврат ip пользователя
     */
    protected function getIp(): string
    {
        $ip = \filter_var($_SERVER['REMOTE_ADDR'], \FILTER_VALIDATE_IP);

        if (empty($ip)) {
            throw new RuntimeException('Bad IP');
        }

        return $ip;
    }

    /**
     * Возврат юзер агента браузера пользователя
     */
    protected function getUserAgent(): string
    {
        return \trim($this->c->Secury->replInvalidChars($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    /**
     * Проверка на робота
     * Если робот, то возврат имени
     */
    protected function isBot(string $agent) /* string|false */
    {
        if ('' == $agent) {
            return false;
        }
        $agentL = \strtolower($agent);

        if (
            false !== \strpos($agentL, 'bot')
            || false !== \strpos($agentL, 'spider')
            || false !== \strpos($agentL, 'crawler')
            || false !== \strpos($agentL, 'http')
        ) {
            return $this->nameBot($agent, $agentL);
        }

        if (
            false !== \strpos($agent, 'Mozilla/')
            && (
                false !== \strpos($agent, 'Gecko')
                || (
                    false !== \strpos($agent, '(compatible; MSIE ')
                    && false !== \strpos($agent, 'Windows')
                )
            )
        ) {
            return false;
        } elseif (
            false !== \strpos($agent, 'Opera/')
            && false !== \strpos($agent, 'Presto/')
        ) {
            return false;
        }

        return $this->nameBot($agent, $agentL);
    }

    /**
     * Выделяет имя робота из юзерагента
     */
    protected function nameBot(string $agent, string $agentL): string
    {
        if (false !== \strpos($agentL, 'mozilla')) {
            $agent = \preg_replace('%Mozilla.*?compatible%i', ' ', $agent);
        }
        if (false !== \strpos($agentL, 'http') || false !== \strpos($agentL, 'www.')) {
            $agent = \preg_replace('%(?:https?://|www\.)[^\)]*(\)[^/]+$)?%i', ' ', $agent);
        }
        if (false !== \strpos($agent, '@')) {
            $agent = \preg_replace('%\b[a-z0-9_\.-]+@[^\)]+%i', ' ', $agent);
        }

        $agentL = \strtolower($agent);
        if (
            false !== \strpos($agentL, 'bot')
            || false !== \strpos($agentL, 'spider')
            || false !== \strpos($agentL, 'crawler')
            || false !== \strpos($agentL, 'engine')
        ) {
            $f = true;
            $p = '%(?<=[^a-z\d\.-])(?:robot|bot|spider|crawler)\b.*%i';
        } else {
            $f = false;
            $p = '%^$%';
        }

        if (
            $f
            && \preg_match('%\b(([a-z\d\.! _-]+)?(?:robot|(?<!ro)bot|spider|crawler|engine)(?(2)[a-z\d\.! _-]*|[a-z\d\.! _-]+))%i', $agent, $matches)
        ) {
            $agent = $matches[1];

            $pat = [
                $p,
                '%[^a-z\d\.!-]+%i',
                '%(?<=^|\s|-)v?\d+\.\d[^\s]*\s*%i',
                '%(?<=^|\s)\S{1,2}(?:\s|$)%',
            ];
            $rep = [
                '',
                ' ',
                '',
                '',
            ];
        } else {
            $pat = [
                '%\((?:KHTML|Linux|Mac|Windows|X11)[^\)]*\)?%i',
                $p,
                '%\b(?:AppleWebKit|Chrom|compatible|Firefox|Gecko|Mobile(?=[/ ])|Moz|Opera|OPR|Presto|Safari|Version)[^\s]*%i',
                '%\b(?:InfoP|Intel|Linux|Mac|MRA|MRS|MSIE|SV|Trident|Win|WOW|X11)[^;\)]*%i',
                '%\.NET[^;\)]*%i',
                '%/.*%',
                '%[^a-z\d\.!-]+%i',
                '%(?<=^|\s|-)v?\d+\.\d[^\s]*\s*%i',
                '%(?<=^|\s)\S{1,2}(?:\s|$)%',
            ];
            $rep = [
                ' ',
                '',
                '',
                '',
                '',
                '',
                ' ',
                '',
                '',
            ];
        }
        $agent = \trim(\preg_replace($pat, $rep, $agent), ' -');

        if (empty($agent)) {
            return 'Unknown';
        }

        $a     = \explode(' ', $agent);
        $agent = $a[0];
        if (
            \strlen($agent) < 20
            && ! empty($a[1])
            && \strlen($agent . ' ' . $a[1]) < 26
        ) {
            $agent .= ' ' . $a[1];
        } elseif (\strlen($agent) > 25) {
            $agent = 'Unknown';
        }

        return $agent;
    }

    /**
     * Возвращает имеющийся в наличии язык из HTTP_ACCEPT_LANGUAGE
     * или язык по умолчанию
     */
    protected function getLangFromHTTP(): string
    {
        if (! empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = $this->c->Func->getLangs();
            $main  = [];
            foreach ($this->c->Func->langParse($_SERVER['HTTP_ACCEPT_LANGUAGE']) as $entry) {
                $arr = \explode('-', $entry, 2);
                if (isset($arr[1])) {
                    $entry  = $arr[0] . '_' . \strtoupper($arr[1]);
                    $main[] = $arr[0];
                }
                if (isset($langs[$entry])) {
                    return $langs[$entry];
                }
            }
            if (! empty($main)) {
                foreach ($main as $entry) {
                    if (isset($langs[$entry])) {
                        return $langs[$entry];
                    }
                }
            }
        }

        return $this->c->config->o_default_lang;
    }
}
