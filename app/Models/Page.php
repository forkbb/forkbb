<?php

namespace ForkBB\Models;

use ForkBB\Core\Container;
use ForkBB\Models\Model;
use RuntimeException;

class Page extends Model
{
    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $container->Lang->load('common');

        $this->fIndex       = 'index'; # string      Указатель на активный пункт навигации
        $this->httpStatus   = 200;     # int         HTTP статус ответа для данной страницы
        $this->httpHeaders  = [];      # array       HTTP заголовки отличные от статуса
#       $this->nameTpl      = null;    # null|string Имя шаблона
#       $this->titles       = [];      # array       Массив титула страницы | setTitles()
        $this->fIswev       = [];      # array       Массив info, success, warning, error, validation информации
#       $this->onlinePos    = '';      # null|string Позиция для таблицы онлайн текущего пользователя
        $this->onlineDetail = false;   # bool        Формировать данные по посетителям online или нет
        $this->onlineFilter = true;    # bool        Посетители только по текущей странице или по всем
#       $this->robots       = '';      # string      Переменная для meta name="robots"
#       $this->canonical    = '';      # string      Переменная для link rel="canonical"

        $this->fTitle       = $container->config->o_board_title;
        $this->fDescription = $container->config->o_board_desc;
        $this->fRootLink    = $container->Router->link('Index');
        if ($container->config->o_announcement == '1') {
            $this->fAnnounce = $container->config->o_announcement_message;
        }
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare()
    {
        $this->fNavigation = $this->fNavigation();
        $this->maintenance();
    }

    /**
     * Возвращает массив ссылок с описанием для построения навигации
     *
     * @return array
     */
    protected function fNavigation()
    {
        $user = $this->c->user;
        $r = $this->c->Router;

        $nav = [
            'index' => [$r->link('Index'), \ForkBB\__('Index')]
        ];

        if ($user->g_read_board == '1' && $user->g_view_users == '1') {
            $nav['userlist'] = [$r->link('Userlist'), \ForkBB\__('User list')];
        }

        if ($this->c->config->o_rules == '1' && (! $user->isGuest || $user->g_read_board == '1' || $this->c->config->o_regs_allow == '1')) {
            $nav['rules'] = [$r->link('Rules'), \ForkBB\__('Rules')];
        }

        if ($user->g_read_board == '1' && $user->g_search == '1') {
            $nav['search'] = [$r->link('Search'), \ForkBB\__('Search')];
        }

        if ($user->isGuest) {
            $nav['register'] = [$r->link('Register'), \ForkBB\__('Register')];
            $nav['login'] = [$r->link('Login'), \ForkBB\__('Login')];
        } else {
            $nav['profile'] = [$r->link('User', [
                'id' => $user->id,
                'name' => $user->username,
            ]), \ForkBB\__('Profile')];
            // New PMS
            if ($this->c->config->o_pms_enabled == '1' && ($user->isAdmin || $user->messages_new > 0)) { //????
                $nav['pmsnew'] = ['pmsnew.php', \ForkBB\__('PM')]; //'<li id="nav"'.((PUN_ACTIVE_PAGE == 'pms_new' || $user['messages_new'] > 0) ? ' class="isactive"' : '').'><a href="pmsnew.php">'.\ForkBB\__('PM').(($user['messages_new'] > 0) ? ' (<span'.((empty($this->c->config->o_pms_flasher) || PUN_ACTIVE_PAGE == 'pms_new') ? '' : ' class="remflasher"' ).'>'.$user['messages_new'].'</span>)' : '').'</a></li>';
            }
            // New PMS

            if ($user->isAdmMod) {
                $nav['admin'] = [$r->link('Admin'), \ForkBB\__('Admin')];
            }

            $nav['logout'] = [$r->link('Logout', [
                'token' => $this->c->Csrf->create('Logout'),
            ]), \ForkBB\__('Logout')];
        }

        if ($user->g_read_board == '1' && $this->c->config->o_additional_navlinks != '') {
            // position|name|link[|id]\n
            if (preg_match_all('%^(\d+)\|([^\|\n\r]+)\|([^\|\n\r]+)(?:\|([^\|\n\r]+))?$%m', $this->c->config->o_additional_navlinks . "\n", $matches)) {
               $k = count($matches[0]);
               for ($i = 0; $i < $k; ++$i) {
                   if (empty($matches[4][$i])) {
                       $matches[4][$i] = 'extra' . $i;
                   }
                   if (isset($nav[$matches[4][$i]])) {
                       $nav[$matches[4][$i]] = [$matches[3][$i], $matches[2][$i]];
                   } else {
                       $nav = array_merge(
                           array_slice($nav, 0, $matches[1][$i]),
                           [$matches[4][$i] => [$matches[3][$i], $matches[2][$i]]],
                           array_slice($nav, $matches[1][$i])
                       );
                   }
               }
            }
        }
        return $nav;
    }

    /**
     * Вывод информации о режиме обслуживания для админа
     */
    protected function maintenance()
    {
        if ($this->c->config->o_maintenance == '1' && $this->c->user->isAdmin) {
            $this->a['fIswev']['w']['maintenance'] = \ForkBB\__('Maintenance mode enabled', $this->c->Router->link('AdminOptions', ['#' => 'Maintenance']));
        }
    }

    /**
     * Возвращает title страницы
     * $this->pageTitle
     *
     * @param array $titles
     *
     * @return string
     */
    protected function getpageTitle(array $titles = [])
    {
        if (empty($titles)) {
            $titles = $this->titles;
        }
        $titles[] = $this->c->config->o_board_title;
        return implode(\ForkBB\__('Title separator'), $titles);
    }

    /**
     * Возвращает массива заголовков страницы
     * $this->pageHeaders
     *
     * @return array
     */
    protected function getpageHeaders()
    {
        $headers = [['link', 'rel="stylesheet" type="text/css" href="' . $this->c->PUBLIC_URL . '/style/' . $this->c->user->style . '/style.css' . '"']];
        if ($this->canonical) {
            $headers[] = ['link', 'rel="canonical" href="' . $this->canonical . '"'];
        }
        if ($this->robots) {
            $headers[] = ['meta', 'name="robots" content="' . $this->robots . '"'];
        }
        if (isset($this->a['pageHeaders']['style'])) {
            foreach ($this->a['pageHeaders']['style'] as $style) {
                $headers[] = ['style', $style];
            }
        }
        return $headers;
    }

    /**
     * Добавляет стиль на страницу
     * 
     * @param string $name
     * @param string $val
     * 
     * @return Page
     */
    public function addStyle($name, $val) 
    {
        $this->a['pageHeaders']['style'][$name] = $val;
        return $this;
    }

    /**
     * Возвращает HTTP заголовки страницы
     * $this->httpHeaders
     *
     * @return array
     */
    protected function gethttpHeaders()
    {
        $headers = $this->a['httpHeaders'];
        if (! empty($status = $this->httpStatus())) {
            $headers[] = $status;
        }
#       $headers[] = 'X-Frame-Options: DENY';
        return $headers;
    }

    /**
     * Возвращает HTTP статус страницы или null
     *
     * @return null|string
     */
    protected function httpStatus()
    {
        $list = [
            403 => '403 Forbidden',
            404 => '404 Not Found',
            405 => '405 Method Not Allowed',
            501 => '501 Not Implemented',
            503 => '503 Service Unavailable',
        ];

        if (isset($list[$this->httpStatus])) {
            $status = 'HTTP/1.0 ';

            if (isset($_SERVER['SERVER_PROTOCOL'])
                && preg_match('%^HTTP/([12]\.[01])%', $_SERVER['SERVER_PROTOCOL'], $match)
            ) {
                $status = 'HTTP/' . $match[1] . ' ';
            }

            return $status . $list[$this->httpStatus];
        }
    }

    /**
     * Дописывает в массив титула страницы новый элемент
     * $this->titles
     *
     * @param string $val
     */
    public function settitles($val)
    {
        if (empty($this->a['titles'])) {
            $this->a['titles'] = [$val];
        } else {
            $this->a['titles'][] = $val;
        }
    }
}
