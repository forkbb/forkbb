<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\User;
use RuntimeException;

class Current extends Action
{
    /**
     * Получает пользователя на основе куки авторизации
     * Обновляет куку аутентификации
     */
    public function current(): User
    {
        $ip   = \filter_var(FORK_ADDR, \FILTER_VALIDATE_IP) ?: '0.0.0.0';
        $ua   = \trim($this->c->Secury->replInvalidChars(FORK_UA));
        $id   = (int) $this->c->Cookie->uId;
        $bot  = $id > 0 ? false : $this->isBot($ua);
        $user = $this->load($id, $ip);

        if (! $user->isGuest) {
            if (! $this->c->Cookie->verifyUser($user)) {
                $user = $this->load(0, $ip);

            } elseif ($user->ip_check_type > 0) {
                $hexIp = \bin2hex(\inet_pton($ip));

                if (false === \strpos("|{$user->login_ip_cache}|", "|{$hexIp}|")) {
                    $user = $this->load(0, $ip);
                }
            }
        }

        $user->__ip        = $ip;
        $user->__userAgent = $ua;

        $this->c->Cookie->setUser($user);

        if ($user->isGuest) {
            $user->__isBot    = false === $bot ? $this->isBot($ua) : $bot;
            $user->__timezone = $this->c->config->o_default_timezone;
            $user->__language = 1 === $this->c->config->b_default_lang_auto ? $this->getLangFromHTTP() : $this->c->config->o_default_lang;
            $user->__locale   = $user->language;

        } else {
            $user->__isBot    = null;

            // Special case: We've timed out, but no other user has browsed the forums since we timed out
            if (
                $user->logged > 0
                && $user->logged < \time() - $this->c->config->i_timeout_visit
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

        if ($id > 0) {
            $vars = [
                ':id' => $id,
            ];
            $query = 'SELECT u.*, g.*, o.logged, o.o_position
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
            $query = 'SELECT o.logged, o.last_post, o.last_search, o.o_position
                FROM ::online AS o
                WHERE o.user_id=0 AND o.ident=?s:ip';

            $data = $this->c->DB->query($query, $vars)->fetch();

            return $this->manager->guest($data ?: []);

        } else {
            return $this->manager->create($data);
        }
    }

    protected array  $brStatus      = [null, 'Unknown', 'Unknown', 'Unknown'];
    protected string $defRegex      = '%(?:^|[ ()])\b([\w .-]*{}[\w/.!-]*)%i';
    protected array  $botSearchList = [
        'bot'        => ['%(?<!cu)bot(?!tle)%'],
        'crawl'      => [''],
        'spider'     => ['%spider(?![\w ]*build/)%'],
        'google'     => ['%google(?:\w| |;|\-(?!tr))%'],
        'wordpress'  => ['', '%(wordpress)%i'],
        'compatible' => ['%compatible(?!;\ msie)%', '%compatible[;) (]+([\w ./!-]+)%i']
    ];
    protected array $scup           = ['"Android"' => 'Android', '"Chrome OS"' => 'CrOS', '"Chromium OS"' => 'CrOS', '"iOS"' => 'like Mac OS X', '"Linux"' => 'Linux', '"macOS"' => 'Macintosh', '"Windows"' => 'Windows', '"Unknown"' => ''];

    /**
     * Определяет бота
     * Если бот, то возвращает вычисленное имя
     */
    protected function isBot(string $agent): ?string
    {
        $status = (int) (
            empty(FORK_ENC)
            || empty(FORK_LNG)
            || (
                ':' === $this->c->BASE_URL[5]
                && (
                    empty(FORK_SFD)
                    || empty(FORK_SFM)
                    || empty(FORK_SFS)
                )
            )
            || (
                empty(FORK_ACC)
                && (
                    empty(FORK_SFD)
                    || 'empty' !== FORK_SFD
                )
            )
            || (
                ! empty(FORK_REF)
                && ! \str_starts_with(FORK_REF, 'https://')
                && ! \str_starts_with(FORK_REF, 'http://')
            )
            || (
                null !== FORK_SCUP
                && (
                    ! isset($this->scup[FORK_SCUP])
                    || false === \strpos(FORK_UA, $this->scup[FORK_SCUP])
                )
            )
        );

        if ('' === $agent) {
            return $this->brStatus[$status];
        }

        $agentL = \strtolower($agent);

        if (
            false !== ($pos = \strpos($agentL, 'http:'))
            || false !== ($pos = \strpos($agentL, 'https:'))
            || false !== ($pos = \strpos($agentL, 'www.'))
        ) {
            $status = 2;
            $agent  = \substr($agent, 0, $pos);
            $agentL = \strtolower($agent);
        }

        foreach ($this->botSearchList as $needle => $regex) {
            if (
                false !== \strpos($agentL, $needle)
                && (
                    '' == $regex[0]
                    || \preg_match($regex[0], $agentL)
                )
                && \preg_match($regex[1] ?? \str_replace('{}', $needle, $this->defRegex), $agent, $match)
            ) {
                $status = 3;
                $agent  = $match[1];

                break;
            }
        }

        if (
            0 === $status
            && (
                ! \str_starts_with($agent, 'Mozilla/')
                || false === \strpos($agent, ' Gecko')
            )
        ) {
            $status = 2;
        }

        if ($status < 2) {
            return $this->brStatus[$status];
        }

        $reg = [
            '%Mozilla\S+%',
            '%[^\w/.-]+%',
            '%(?:_| |-|\b)bot(?:_| |-|\b)%i',
            '%(?<=^|\s)[^a-zA-Z\s]{1,2}(?:\s|$)%',
            '%/\S*+\K.+%',
        ];
        $rep = [
            '',
            ' ',
            '',
            '',
            '',
        ];

        $agent = \trim(\preg_replace($reg, $rep, $agent));

        if (
            empty($agent)
            || isset($agent[28])
        ) {
            return $this->brStatus[$status];

        } else {
            return $agent;
        }
    }

    /**
     * Возвращает имеющийся в наличии язык из HTTP_ACCEPT_LANGUAGE
     * или язык по умолчанию
     */
    protected function getLangFromHTTP(): string
    {
        if (! empty(FORK_LNG)) {
            $langs = $this->c->Func->getLangs();
            $main  = [];

            foreach ($this->c->Func->langParse(FORK_LNG) as $entry) {
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
