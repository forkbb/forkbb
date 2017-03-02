<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Container;
use RuntimeException;

abstract class Page
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Конфигурация форума
     * @var array
     */
    protected $config;

    /**
     * HTTP статус ответа для данной страницы
     * @var int
     */
    protected $httpStatus = 200;

    /**
     * HTTP заголовки отличные от статуса
     * @var array
     */
    protected $httpHeaders = [];

    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl;

    /**
     * Указатель на активный пункт навигации
     * @var string
     */
    protected $index = 'index';

    /**
     * Массив титула страницы
     * @var array
     */
    protected $titles;

    /**
     * Подготовленные данные для шаблона
     * @var array
     */
    protected $data;

    /**
     * Массив info, success, warning, error, validation информации
     * @var array
     */
    protected $iswev = [];

    /**
     * Позиция для таблицы онлайн текущего пользователя
     * @var null|string
     */
    protected $onlinePos = '';

    /**
     * Тип обработки пользователей онлайн
     * Если false, то идет обновление данных
     * Если true, то идет возврат данных (смотрите $onlineFilter)
     * @var bool
     */
    protected $onlineType = false;

    /**
     * Тип возврата данных при onlineType === true
     * Если true, то из online должны вернутся только пользователи находящиеся на этой же странице
     * Если false, то все пользователи online
     * @var bool
     */
    protected $onlineFilter = true;

    /**
     * Конструктор
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
        $this->config = $container->config;
        $container->Lang->load('common');
    }

    /**
     * Возвращает HTTP заголовки страницы
     * @return array
     */
    public function getHeaders()
    {
        $headers = $this->httpHeaders;
        if (! empty($status = $this->getStatus())) {
            $headers[] = $status;
        }
        return $headers;
    }


    /**
     * Возвращает HTTP статус страницы или null
     * @return null|string
     */
    protected function getStatus()
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
     * Возвращает флаг готовности данных
     * @return bool
     */
    public function isReady()
    {
        return is_array($this->data);
    }

    /**
     * Возвращает имя шаблона
     * @return string
     */
    public function getNameTpl()
    {
        return $this->nameTpl;
    }

    /**
     * Возвращает данные для шаблона
     * @return array
     */
    public function getData()
    {
        if (empty($this->data)) {
            $this->data = [];
        }
        return $this->data + [
            'pageTitle' => $this->pageTitle(),
            'pageHeads' => $this->pageHeads(),
            'fLang' => __('lang_identifier'),
            'fDirection' => __('lang_direction'),
            'fTitle' => $this->config['o_board_title'],
            'fDescription' => $this->config['o_board_desc'],
            'fNavigation' => $this->fNavigation(),
            'fIndex' => $this->index,
            'fAnnounce' => $this->fAnnounce(),
            'fRootLink' => $this->c->Router->link('Index'),
            'fIswev' => $this->getIswev(),
        ];
    }

    /**
     * Возврат info, success, warning, error, validation информации
     * @return array
     */
    protected function getIswev()
    {
        if ($this->config['o_maintenance'] == '1') {
            if ($this->c->user->isAdmin) {
                $this->iswev['w'][] = __('Maintenance mode enabled', $this->c->Router->link('AdminOptions', ['#' => 'maintenance']));
            }
        }
        return $this->iswev;
    }

    /**
     * Установка info, success, warning, error, validation информации из вне
     * @param array $iswev
     * @return Page
     */
    public function setIswev(array $iswev)
    {
        $this->iswev = $iswev;
        return $this;
    }

    /**
     * Формирует title страницы
     * @return string
     */
    protected function pageTitle()
    {
        $arr = empty($this->titles) ? [] : array_reverse($this->titles);
        $arr[] = $this->config['o_board_title'];
        return implode(__('Title separator'), $arr);
    }

    /**
     * Генерация массива заголовков страницы
     * @return array
     */
    protected function pageHeads()
    {
        return []; //????
    }

    /**
     * Возвращает текст объявления или null
     * @return null|string
     */
    protected function fAnnounce()
    {
        return empty($this->config['o_announcement']) ? null : $this->config['o_announcement_message'];
    }

    /**
     * Возвращает массив ссылок с описанием для построения навигации
     * @return array
     */
    protected function fNavigation()
    {
        $user = $this->c->user;
        $r = $this->c->Router;

        $nav = [
            'index' => [$r->link('Index'), __('Index')]
        ];

        if ($user->gReadBoard == '1' && $user->gViewUsers == '1') {
            $nav['userlist'] = [$r->link('Userlist'), __('User list')];
        }

        if ($this->config['o_rules'] == '1' && (! $user->isGuest || $user->gReadBoard == '1' || $this->config['o_regs_allow'] == '1')) {
            $nav['rules'] = [$r->link('Rules'), __('Rules')];
        }

        if ($user->gReadBoard == '1' && $user->gSearch == '1') {
            $nav['search'] = [$r->link('Search'), __('Search')];
        }

        if ($user->isGuest) {
            $nav['register'] = [$r->link('Register'), __('Register')];
            $nav['login'] = [$r->link('Login'), __('Login')];
        } else {
            $nav['profile'] = [$r->link('User', [
                'id' => $user->id,
                'name' => $user->username,
            ]), __('Profile')];
            // New PMS
            if ($this->config['o_pms_enabled'] == '1' && ($user->isAdmin || $user->messagesNew > 0)) { //????
                $nav['pmsnew'] = ['pmsnew.php', __('PM')]; //'<li id="nav"'.((PUN_ACTIVE_PAGE == 'pms_new' || $user['messages_new'] > 0) ? ' class="isactive"' : '').'><a href="pmsnew.php">'.__('PM').(($user['messages_new'] > 0) ? ' (<span'.((empty($this->config['o_pms_flasher']) || PUN_ACTIVE_PAGE == 'pms_new') ? '' : ' class="remflasher"' ).'>'.$user['messages_new'].'</span>)' : '').'</a></li>';
            }
            // New PMS

            if ($user->isAdmMod) {
                $nav['admin'] = [$r->link('Admin'), __('Admin')];
            }

            $nav['logout'] = [$r->link('Logout', [
                'token' => $this->c->Csrf->create('Logout'),
            ]), __('Logout')];
        }

        if ($user->gReadBoard == '1' && $this->config['o_additional_navlinks'] != '') {
            // position|name|link[|id]\n
            if (preg_match_all('%^(\d+)\|([^\|\n\r]+)\|([^\|\n\r]+)(?:\|([^\|\n\r]+))?$%m', $this->config['o_additional_navlinks']."\n", $matches)) {
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
     * Заглушка
     * @param string $name
     * @param array $arguments
     * @throws \RuntimeException
     */
    public function __call($name, array $arguments)
    {
        throw new RuntimeException("'{$name}' method is not");
    }

    /**
     * Возвращает размер в байтах, Кбайтах, ...
     * @param int $size
     * @return string
     */
    protected function size($size)
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB'];

        for ($i = 0; $size > 1024; $i++) {
            $size /= 1024;
        }

        return __('Size unit '.$units[$i], round($size, 2));
    }

    /**
     * Возращает данные для управления обработкой пользователей онлайн
     * @param bool $short
     * @return bool|array
     */
    public function getDataForOnline($short = false)
    {
        return $short
            ? null !== $this->onlinePos
            : [$this->onlinePos, $this->onlineType, $this->onlineFilter];
    }

    /**
     * Возвращает число в формате языка текущего пользователя
     * @param mixed $number
     * @param int $decimals
     * @return string
     */
    protected function number($number, $decimals = 0)
    {
        return is_numeric($number) ? number_format($number, $decimals, __('lang_decimal_point'), __('lang_thousands_sep')) : 'not a number';
    }


    /**
     * Возвращает время в формате текущего пользователя
     * @param int|string $timestamp
     * @param bool $dateOnly
     * @param string $dateFormat
     * @param string $timeFormat
     * @param bool $timeOnly
     * @param bool $noText
     * @return string
     */
    protected function time($timestamp, $dateOnly = false, $dateFormat = null, $timeFormat = null, $timeOnly = false, $noText = false)
    {
        if (empty($timestamp)) {
            return __('Never');
        }

        $user = $this->c->user;

        $diff = ($user->timezone + $user->dst) * 3600;
        $timestamp += $diff;

        if (null === $dateFormat) {
            $dateFormat = $this->c->date_formats[$user->dateFormat];
        }
        if(null === $timeFormat) {
            $timeFormat = $this->c->time_formats[$user->timeFormat];
        }

        $date = gmdate($dateFormat, $timestamp);

        if(! $noText) {
            $now = time() + $diff;

            if ($date == gmdate($dateFormat, $now)) {
                $date = __('Today');
            } elseif ($date == gmdate($dateFormat, $now - 86400)) {
                $date = __('Yesterday');
            }
        }

        if ($dateOnly) {
            return $date;
        } elseif ($timeOnly) {
            return gmdate($timeFormat, $timestamp);
        } else {
            return $date . ' ' . gmdate($timeFormat, $timestamp);
        }
    }
}
