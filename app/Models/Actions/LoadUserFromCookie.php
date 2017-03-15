<?php

namespace ForkBB\Models\Actions;

use ForkBB\Models\UserCookie;
use ForkBB\Models\UserMapper;
use RuntimeException;

class LoadUserFromCookie
{
    protected $mapper;
    protected $cookie;
    protected $config;

    /**
     * Конструктор
     *
     * @param UserMapper $mapper
     * @param UserCookie $cookie
     * @param array $config
     */
    public function __construct(UserMapper $mapper, UserCookie $cookie, array $config)
    {
        $this->mapper = $mapper;
        $this->cookie = $cookie;
        $this->config = $config;
    }

    /**
     * Получение юзера на основе куки авторизации
     * Обновление куки аутентификации
     *
     * @return User
     */
    public function load()
    {
        $id = $this->cookie->id() ?: 1;
        $user = $this->mapper->getCurrent($id);

        if (! $user->isGuest) {
            if (! $this->cookie->verifyHash($user->id, $user->password)) {
                $user = $this->mapper->getCurrent(1);
            } elseif ($this->config['o_check_ip'] == '1'
                && $user->isAdmMod
                && $user->registrationIp != $user->ip
            ) {
                $user = $this->mapper->getCurrent(1);
            }
        }

        $this->cookie->setUserCookie($user->id, $user->password);

        if ($user->isGuest) {
            $user->isBot = $this->isBot();
            $user->dispTopics = $this->config['o_disp_topics_default'];
            $user->dispPosts = $this->config['o_disp_posts_default'];
            $user->timezone = $this->config['o_default_timezone'];
            $user->dst = $this->config['o_default_dst'];
            $user->language = $this->config['o_default_lang'];
            $user->style = $this->config['o_default_style'];

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
            $user->isBot = false;
            if (! $user->dispTopics) {
                $user->dispTopics = $this->config['o_disp_topics_default'];
            }
            if (! $user->dispPosts) {
                $user->dispPosts = $this->config['o_disp_posts_default'];
            }
            // Special case: We've timed out, but no other user has browsed the forums since we timed out
            if ($user->isLogged && $user->logged < time() - $this->config['o_timeout_visit']) {
                $this->mapper->updateLastVisit($user);
                $user->lastVisit = $user->logged;
            }
        }

        return $user;
    }

    /**
     * Проверка на робота
     * Если робот, то возврат имени
     * @return false|string
     */
    protected function isBot()
    {
        $agent = trim($_SERVER['HTTP_USER_AGENT']);
        if ($agent == '') {
            return false;
        }
        $agentL = strtolower($agent);

        if (strpos($agentL, 'bot') !== false
            || strpos($agentL, 'spider') !== false
            || strpos($agentL, 'crawler') !== false
            || strpos($agentL, 'http') !== false
        ) {
            return $this->nameBot($agent, $agentL);
        }

        if (strpos($agent, 'Mozilla/') !== false
            && (strpos($agent, 'Gecko') !== false
                || (strpos($agent, '(compatible; MSIE ') !== false
                    && strpos($agent, 'Windows') !== false
                )
            )
        ) {
            return false;
        } elseif (strpos($agent, 'Opera/') !== false
            && strpos($agent, 'Presto/') !== false
        ) {
            return false;
        }
        return $this->nameBot($agent, $agentL);
    }

    /**
     * Выделяет имя робота из юзерагента
     * @param string $agent
     * @param string $agentL
     * @retrun string
     */
    protected function nameBot($agent, $agentL)
    {
        if (strpos($agentL, 'mozilla') !== false) {
            $agent = preg_replace('%Mozilla.*?compatible%i', ' ', $agent);
        }
        if (strpos($agentL, 'http') !== false || strpos($agentL, 'www.') !== false) {
            $agent = preg_replace('%(?:https?://|www\.)[^\)]*(\)[^/]+$)?%i', ' ', $agent);
        }
        if (strpos($agent, '@') !== false) {
            $agent = preg_replace('%\b[a-z0-9_\.-]+@[^\)]+%i', ' ', $agent);
        }

        $agentL = strtolower($agent);
        if (strpos($agentL, 'bot') !== false
            || strpos($agentL, 'spider') !== false
            || strpos($agentL, 'crawler') !== false
            || strpos($agentL, 'engine') !== false
        ) {
            $f = true;
            $p = '%(?<=[^a-z\d\.-])(?:robot|bot|spider|crawler)\b.*%i';
        } else {
            $f = false;
            $p = '%^$%';
        }

        if ($f && preg_match('%\b(([a-z\d\.! _-]+)?(?:robot|(?<!ro)bot|spider|crawler|engine)(?(2)[a-z\d\.! _-]*|[a-z\d\.! _-]+))%i', $agent, $matches))
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
        $agent = trim(preg_replace($pat, $rep, $agent), ' -');

        if (empty($agent)) {
            return 'Unknown';
        }

        $a = explode(' ', $agent);
        $agent = $a[0];
        if (strlen($agent) < 20
            && ! empty($a[1])
            && strlen($agent . ' ' . $a[1]) < 26
        ) {
            $agent .= ' ' . $a[1];
        } elseif (strlen($agent) > 25) {
            $agent = 'Unknown';
        }
        return $agent;
    }
}
