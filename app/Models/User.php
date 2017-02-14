<?php

namespace ForkBB\Models;

use ForkBB\Core\Model; //????
use R2\DependencyInjection\ContainerInterface;
use RuntimeException;

class User extends Model
{
    /**
     * Контейнер
     * @var ContainerInterface
     */
    protected $c;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var UserCookie
     */
    protected $userCookie;

    /**
     * @var DB
     */
    protected $db;

    /**
     * Адрес пользователя
     * @var string
     */
    protected $ip;

    /**
     * Конструктор
     */
    public function __construct(array $config, $cookie, $db, ContainerInterface $container)
    {
        $this->config = $config;
        $this->userCookie = $cookie;
        $this->db = $db;
        $this->c = $container;
        $this->ip = $this->getIpAddress();
    }

    /**
     * @return User
     */
    public function init()
    {
        if (($userId = $this->userCookie->id()) === false) {
            return $this->initGuest();
        }

        $result = $this->db->query('SELECT u.*, g.*, o.logged, o.idle FROM '.$this->db->prefix.'users AS u INNER JOIN '.$this->db->prefix.'groups AS g ON u.group_id=g.g_id LEFT JOIN '.$this->db->prefix.'online AS o ON o.user_id=u.id WHERE u.id='.$userId) or error('Unable to fetch user information', __FILE__, __LINE__, $this->db->error());
        $user = $this->db->fetch_assoc($result);
        $this->db->free_result($result);

        if (empty($user['id']) || ! $this->userCookie->verifyHash($user['id'], $user['password'])) {
            return $this->initGuest();
        }

        // проверка ip админа и модератора - Visman
        if ($this->config['o_check_ip'] == '1' && ($user['g_id'] == PUN_ADMIN || $user['g_moderator'] == '1') && $user['registration_ip'] != $this->ip) {
            return $this->initGuest();
        }

        $this->userCookie->setUserCookie($user['id'], $user['password']);

        // Set a default language if the user selected language no longer exists
        if (!file_exists(PUN_ROOT.'lang/'.$user['language'])) {
            $user['language'] = $this->config['o_default_lang'];
        }

        // Set a default style if the user selected style no longer exists
        if (!file_exists(PUN_ROOT.'style/'.$user['style'].'.css')) {
            $user['style'] = $this->config['o_default_style'];
        }

        if (!$user['disp_topics']) {
            $user['disp_topics'] = $this->config['o_disp_topics_default'];
        }
        if (!$user['disp_posts']) {
            $user['disp_posts'] = $this->config['o_disp_posts_default'];
        }

        $now = time();

        if (! $user['logged']) {
            $user['logged'] = $now;
            $user['is_logged'] = true;

            // Reset tracked topics
            set_tracked_topics(null);
        } else {
            $user['is_logged'] = false;

            // Special case: We've timed out, but no other user has browsed the forums since we timed out
            if ($user['logged'] < ($now - $this->config['o_timeout_visit']))
            {
                $this->db->query('UPDATE '.$this->db->prefix.'users SET last_visit='.$user['logged'].' WHERE id='.$user['id']) or error('Unable to update user visit data', __FILE__, __LINE__, $this->db->error());
                $user['last_visit'] = $user['logged'];
            }
            $cookie = $this->c->get('Cookie');
            $track = $cookie->get('track');
            // Update tracked topics with the current expire time
            if (isset($track)) {
                $cookie->set('track', $track, $now + $this->config['o_timeout_visit']);
            }
        }

        $user['is_guest'] = false;
        $user['is_admmod'] = $user['g_id'] == PUN_ADMIN || $user['g_moderator'] == '1';
        $user['is_bot'] = false;
        $user['ip'] = $this->ip;

        $this->current = $user;

        return $this;
    }

    /**
     * @throws \RuntimeException
     * @return User
     */
    protected function initGuest()
    {
        $result = $this->db->query('SELECT u.*, g.*, o.logged, o.last_post, o.last_search FROM '.$this->db->prefix.'users AS u INNER JOIN '.$this->db->prefix.'groups AS g ON u.group_id=g.g_id LEFT JOIN '.$this->db->prefix.'online AS o ON (o.user_id=1 AND o.ident=\''.$this->db->escape($this->ip).'\') WHERE u.id=1') or error('Unable to fetch guest information', __FILE__, __LINE__, $this->db->error());
        $user = $this->db->fetch_assoc($result);
        $this->db->free_result($result);

        if (empty($user['id'])) {
            throw new RuntimeException('Unable to fetch guest information. Your database must contain both a guest user and a guest user group.');
        }

        $this->userCookie->deleteUserCookie();

        // этого гостя нет в таблице online
        if (! $user['logged']) {
            $user['logged'] = time();
            $user['is_logged'] = true;
        } else {
            $user['is_logged'] = false;
        }
        $user['disp_topics'] = $this->config['o_disp_topics_default'];
        $user['disp_posts'] = $this->config['o_disp_posts_default'];
        $user['timezone'] = $this->config['o_default_timezone'];
        $user['dst'] = $this->config['o_default_dst'];
        $user['language'] = $this->config['o_default_lang'];
        $user['style'] = $this->config['o_default_style'];
        $user['is_guest'] = true;
        $user['is_admmod'] = false;
        $user['is_bot'] = $this->isBot();
        $user['ip'] = $this->ip;

        // быстрое переключение языка - Visman
        $language = $this->c->get('Cookie')->get('glang');
        if (null !== $language)
        {
            $language = preg_replace('%[^\w]%', '', $language);
            $languages = forum_list_langs();
            if (in_array($language, $languages))
                $user['language'] = $language;
        }

        $this->current = $user;
        return $this;
    }

    /**
     * Возврат адреса пользователя
     * @return string
     */
    protected function getIpAddress()
    {
       return filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ?: 'unknow';
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
            $agent = preg_replace('%\b[\w\.-]+@[^\)]+%', ' ', $agent);
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

    /**
     * Выход
     */
    public function logout()
    {
        if ($this->current['is_guest']) {
            return;
        }

        $this->userCookie->deleteUserCookie();
        $this->c->get('Online')->delete($this);
        // Update last_visit (make sure there's something to update it with)
        if (isset($this->current['logged'])) {
            $this->db->query('UPDATE '.$this->db->prefix.'users SET last_visit='.$this->current['logged'].' WHERE id='.$this->current['id']) or error('Unable to update user visit data', __FILE__, __LINE__, $this->db->error());
        }
    }

    /**
     * Вход
     * @param string $name
     * @param string $password
     * @param bool $save
     * @return mixed
     */
    public function login($name, $password, $save)
    {
        $result = $this->db->query('SELECT u.id, u.group_id, u.username, u.password, u.registration_ip, g.g_moderator FROM '.$this->db->prefix.'users AS u LEFT JOIN '.$this->db->prefix.'groups AS g ON u.group_id=g.g_id WHERE u.username=\''.$this->db->escape($name).'\'') or error('Unable to fetch user info', __FILE__, __LINE__, $this->db->error());
        $user = $this->db->fetch_assoc($result);
        $this->db->free_result($result);

        if (empty($user['id'])) {
            return false;
        }

        $authorized = false;
        // For FluxBB by Visman 1.5.10.74 and above
        if (strlen($user['password']) == 40) {
            if (hash_equals($user['password'], sha1($password . $this->c->getParameter('SALT1')))) {
                $authorized = true;

                $user['password'] = password_hash($password, PASSWORD_DEFAULT);
                $this->db->query('UPDATE '.$this->db->prefix.'users SET password=\''.$this->db->escape($user['password']).'\' WHERE id='.$user['id']) or error('Unable to update user password', __FILE__, __LINE__, $this->db->error());
            }
        } else {
            $authorized = password_verify($password, $user['password']);
        }

        if (! $authorized) {
            return false;
        }

        // Update the status if this is the first time the user logged in
        if ($user['group_id'] == PUN_UNVERIFIED)
        {
            $this->db->query('UPDATE '.$this->db->prefix.'users SET group_id='.$this->config['o_default_user_group'].' WHERE id='.$user['id']) or error('Unable to update user status', __FILE__, __LINE__, $this->db->error());

            $this->c->get('users_info update');
        }

        // перезаписываем ip админа и модератора - Visman
        if ($this->config['o_check_ip'] == '1' && $user['registration_ip'] != $this->current['ip'])
        {
            if ($user['g_id'] == PUN_ADMIN || $user['g_moderator'] == '1')
                $this->db->query('UPDATE '.$this->db->prefix.'users SET registration_ip=\''.$this->db->escape($this->current['ip']).'\' WHERE id='.$user['id']) or error('Unable to update user IP', __FILE__, __LINE__, $this->db->error());
        }

        $this->c->get('Online')->delete($this);

        $this->c->get('UserCookie')->setUserCookie($user['id'], $user['password'], $save);

        return $user['id'];
    }

}
