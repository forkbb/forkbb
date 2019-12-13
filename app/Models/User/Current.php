<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use RuntimeException;

class Current extends Action
{
    /**
     * Получение юзера на основе куки авторизации
     * Обновление куки аутентификации
     *
     * @return User
     */
    public function current()
    {
        $cookie = $this->c->Cookie;
        $user = $this->load((int) $cookie->uId);

        if (! $user->isGuest) {
            if (! $cookie->verifyUser($user)) {
                $user = $this->load(1);
            } elseif ($this->c->config->o_check_ip == '1'
                && $user->isAdmMod
                && $user->registration_ip !== $user->ip
            ) {
                $user = $this->load(1);
            }
        }

        $cookie->setUser($user);

        if ($user->isGuest) {
            $user->__isBot = $this->isBot();
#            $user->__disp_topics = $this->c->config->o_disp_topics_default;
#            $user->__disp_posts = $this->c->config->o_disp_posts_default;
            $user->__timezone = $this->c->config->o_default_timezone;
            $user->__dst = $this->c->config->o_default_dst;
#            $user->language = $this->c->config->o_default_lang;
#            $user->style = $this->c->config->o_default_style;
            $user->__language = $this->getLangFromHTTP();

            // быстрое переключение языка - Visman
/*            $language = $this->cookie->get('glang');
            if (null !== $language) {
                $language = preg_replace('%[^a-zA-Z0-9_]%', '', $language);
                $languages = forum_list_langs();
                if (in_array($language, $languages)) {
                    $user->language = $language;
                }
            } */
        } else {
            $user->__isBot = false;
#            if (! $user->disp_topics) {
#                $user->__disp_topics = $this->c->config->o_disp_topics_default;
#            }
#            if (! $user->disp_posts) {
#                $user->__disp_posts = $this->c->config->o_disp_posts_default;
#            }
            // Special case: We've timed out, but no other user has browsed the forums since we timed out
            if ($user->isLogged && $user->logged < time() - $this->c->config->o_timeout_visit) {
                $this->manager->updateLastVisit($user); //????
            }

            $this->manager->set($user->id, $user);
        }

        return $user;
    }

    /**
     * Загрузка данных в модель пользователя из базы
     *
     * @param int $id
     *
     * @throws RuntimeException
     *
     * @return User;
     */
    protected function load($id)
    {
        $data = null;
        $ip = $this->getIp();
        if ($id > 1) {
            $data = $this->c->DB->query('SELECT u.*, g.*, o.logged FROM ::users AS u INNER JOIN ::groups AS g ON u.group_id=g.g_id LEFT JOIN ::online AS o ON o.user_id=u.id WHERE u.id=?i:id', [':id' => $id])->fetch();
        }
        if (empty($data['id'])) {
            $data = $this->c->DB->query('SELECT u.*, g.*, o.logged, o.last_post, o.last_search FROM ::users AS u INNER JOIN ::groups AS g ON u.group_id=g.g_id LEFT JOIN ::online AS o ON (o.user_id=1 AND o.ident=?s:ip) WHERE u.id=1', [':ip' => $ip])->fetch();
            if (empty($data['id'])) {
                throw new RuntimeException('Unable to fetch guest information. Your database must contain both a guest user and a guest user group');
            }
        }

        $user = $this->manager->create($data);
        $user->__ip = $ip;
        $user->__userAgent = $this->getUserAgent();
        return $user;
    }

    /**
     * Возврат ip пользователя
     *
     * @return string
     */
    protected function getIp()
    {
       return \filter_var($_SERVER['REMOTE_ADDR'], \FILTER_VALIDATE_IP) ?: 'unknow';
    }

    /**
     * Возврат юзер агента браузера пользователя
     *
     * @return string
     */
    protected function getUserAgent()
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        return \is_string($ua) ? \trim($ua) : '';
    }

    /**
     * Проверка на робота
     * Если робот, то возврат имени
     *
     * @return false|string
     */
    protected function isBot()
    {
        $agent = $this->getUserAgent();
        if ($agent == '') {
            return false;
        }
        $agentL = \strtolower($agent);

        if (\strpos($agentL, 'bot') !== false
            || \strpos($agentL, 'spider') !== false
            || \strpos($agentL, 'crawler') !== false
            || \strpos($agentL, 'http') !== false
        ) {
            return $this->nameBot($agent, $agentL);
        }

        if (\strpos($agent, 'Mozilla/') !== false
            && (\strpos($agent, 'Gecko') !== false
                || (\strpos($agent, '(compatible; MSIE ') !== false
                    && \strpos($agent, 'Windows') !== false
                )
            )
        ) {
            return false;
        } elseif (\strpos($agent, 'Opera/') !== false
            && \strpos($agent, 'Presto/') !== false
        ) {
            return false;
        }
        return $this->nameBot($agent, $agentL);
    }

    /**
     * Выделяет имя робота из юзерагента
     *
     * @param string $agent
     * @param string $agentL
     *
     * @return string
     */
    protected function nameBot($agent, $agentL)
    {
        if (\strpos($agentL, 'mozilla') !== false) {
            $agent = \preg_replace('%Mozilla.*?compatible%i', ' ', $agent);
        }
        if (\strpos($agentL, 'http') !== false || \strpos($agentL, 'www.') !== false) {
            $agent = \preg_replace('%(?:https?://|www\.)[^\)]*(\)[^/]+$)?%i', ' ', $agent);
        }
        if (\strpos($agent, '@') !== false) {
            $agent = \preg_replace('%\b[a-z0-9_\.-]+@[^\)]+%i', ' ', $agent);
        }

        $agentL = \strtolower($agent);
        if (\strpos($agentL, 'bot') !== false
            || \strpos($agentL, 'spider') !== false
            || \strpos($agentL, 'crawler') !== false
            || \strpos($agentL, 'engine') !== false
        ) {
            $f = true;
            $p = '%(?<=[^a-z\d\.-])(?:robot|bot|spider|crawler)\b.*%i';
        } else {
            $f = false;
            $p = '%^$%';
        }

        if ($f && \preg_match('%\b(([a-z\d\.! _-]+)?(?:robot|(?<!ro)bot|spider|crawler|engine)(?(2)[a-z\d\.! _-]*|[a-z\d\.! _-]+))%i', $agent, $matches))
        {
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

        $a = \explode(' ', $agent);
        $agent = $a[0];
        if (\strlen($agent) < 20
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
     *
     * @return string
     */
    protected function getLangFromHTTP()
    {
        if (! empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = $this->c->Func->getLangs();
            $main = [];
            foreach ($this->c->Func->langParse($_SERVER['HTTP_ACCEPT_LANGUAGE']) as $entry) {
                $arr = \explode('-', $entry, 2);
                if (isset($arr[1])) {
                    $entry = $arr[0] . '_' . \strtoupper($arr[1]);
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
