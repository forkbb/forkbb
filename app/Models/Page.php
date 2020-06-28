<?php

namespace ForkBB\Models;

use ForkBB\Core\Container;
use ForkBB\Models\Model;
use RuntimeException;

abstract class Page extends Model
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
        if ('1' == $container->config->o_announcement) {
            $this->fAnnounce = $container->config->o_announcement_message;
        }
        $this->user         = $this->c->user; // передача текущего юзера в шаблон
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare(): void
    {
        $this->fNavigation = $this->fNavigation();
        $this->maintenance();
    }

    /**
     * Возвращает массив ссылок с описанием для построения навигации
     *
     * @return array
     */
    protected function fNavigation(): array
    {
        $r = $this->c->Router;

        $nav = [
            'index' => [$r->link('Index'), 'Index']
        ];

        if (
            '1' == $this->user->g_read_board
            && $this->user->viewUsers
        ) {
            $nav['userlist'] = [$r->link('Userlist'), 'User list'];
        }

        if (
            '1' == $this->c->config->o_rules
            && (
                ! $this->user->isGuest
                || '1' == $this->user->g_read_board
                || '1' == $this->c->config->o_regs_allow
            )
        ) {
            $nav['rules'] = [$r->link('Rules'), 'Rules'];
        }

        if (
            '1' == $this->user->g_read_board
            && '1' == $this->user->g_search
        ) {
            $sub = [];
            $sub['latest'] = [
                $r->link('SearchAction', ['action' => 'latest_active_topics']),
                'Latest active topics',
                'Find latest active topics',
            ];
            if (! $this->user->isGuest) {
                $sub['with-your-posts'] = [
                    $r->link('SearchAction', ['action' => 'topics_with_your_posts']),
                    'Topics with your posts',
                    'Find topics with your posts',
                ];
            }
            $sub['unanswered'] = [
                $r->link('SearchAction', ['action' => 'unanswered_topics']),
                'Unanswered topics',
                'Find unanswered topics',
            ];

            $nav['search'] = [$r->link('Search'), 'Search', null, $sub];
        }

        if ($this->user->isGuest) {
            $nav['register'] = [$r->link('Register'), 'Register'];
            $nav['login']    = [$r->link('Login'), 'Login'];
        } else {
            $nav['profile'] = [$this->user->link, 'Profile'];
            // New PMS
            if (
                '1' == $this->c->config->o_pms_enabled
                && (
                    $this->user->isAdmin
                    || $this->user->messages_new > 0
                )
            ) { //????
                $nav['pmsnew'] = ['pmsnew.php', 'PM']; //'<li id="nav"'.((PUN_ACTIVE_PAGE == 'pms_new' || $user['messages_new'] > 0) ? ' class="isactive"' : '').'><a href="pmsnew.php">'.\ForkBB\__('PM').(($user['messages_new'] > 0) ? ' (<span'.((empty($this->c->config->o_pms_flasher) || PUN_ACTIVE_PAGE == 'pms_new') ? '' : ' class="remflasher"' ).'>'.$user['messages_new'].'</span>)' : '').'</a></li>';
            }
            // New PMS

            if ($this->user->isAdmMod) {
                $nav['admin'] = [$r->link('Admin'), 'Admin'];
            }

            $nav['logout'] = [$r->link('Logout', ['token' => $this->c->Csrf->create('Logout')]), 'Logout'];
        }

        if (
            '1' == $this->user->g_read_board
            && '' != $this->c->config->o_additional_navlinks
        ) {
            // position|name|link[|id]\n
            if (\preg_match_all('%^(\d+)\|([^\|\n\r]+)\|([^\|\n\r]+)(?:\|([^\|\n\r]+))?%m', $this->c->config->o_additional_navlinks . "\n", $matches)) {
               $k = \count($matches[0]);
               for ($i = 0; $i < $k; ++$i) {
                   if (empty($matches[4][$i])) {
                       $matches[4][$i] = 'extra' . $i;
                   }
                   if (isset($nav[$matches[4][$i]])) {
                       $nav[$matches[4][$i]] = [$matches[3][$i], $matches[2][$i]];
                   } else {
                       $nav = \array_merge(
                           \array_slice($nav, 0, $matches[1][$i]),
                           [$matches[4][$i] => [$matches[3][$i], $matches[2][$i]]],
                           \array_slice($nav, $matches[1][$i])
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
    protected function maintenance(): void
    {
        if (
            '1' == $this->c->config->o_maintenance
            && $this->user->isAdmin
        ) {
            $this->fIswev = ['w', \ForkBB\__('Maintenance mode enabled', $this->c->Router->link('AdminMaintenance'))];
        }

        if (
            $this->user->isAdmMod
            && $this->user->last_report_id < $this->c->reports->lastId()
        ) {
            $this->fIswev = ['i', \ForkBB\__('New reports', $this->c->Router->link('AdminReports'))];
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
    protected function getpageTitle(array $titles = []): string
    {
        if (empty($titles)) {
            $titles = $this->titles;
        }
        $titles[] = $this->c->config->o_board_title;
        return \implode(\ForkBB\__('Title separator'), $titles);
    }

    /**
     * Возвращает массива заголовков страницы
     * $this->pageHeaders
     *
     * @return array
     */
    protected function getpageHeaders(): array
    {
        $headers = [
            ['link', 'rel="stylesheet" type="text/css" href="' . $this->c->PUBLIC_URL . '/style/' . $this->user->style . '/style.css' . '"'],
        ];

        if ($this->canonical) {
            $headers[] = ['link', 'rel="canonical" href="' . $this->canonical . '"'];
        }
        if ($this->robots) {
            $headers[] = ['meta', 'name="robots" content="' . $this->robots . '"'];
        }

        $ph = $this->getAttr('pageHeaders', []);
        if (isset($ph['style'])) {
            foreach ($ph['style'] as $style) {
                $headers[] = ['style', $style];
            }
        }
        return $headers;
    }

    /**
     * Добавляет стиль на страницу
     *
     * @param string $name
     * @param string $value
     *
     * @return Page
     */
    public function addStyle(string $name, string $value): self
    {
        $attr = $this->getAttr('pageHeaders', []);
        $attr['style'][$name] = $value;
        $this->setAttr('pageHeaders', $attr);
        return $this;
    }

    /**
     * Добавляет HTTP заголовок
     *
     * @param string $key
     * @param string $value
     * @param bool   $replace
     *
     * @return Page
     */
    public function header(string $key, string $value, $replace = true): self
    {
        if ('HTTP/' === \substr($key, 0, 5)) {
            if (\preg_match('%^HTTP/\d\.\d%', $_SERVER['SERVER_PROTOCOL'], $match)) {
                $key = $match[0];
            } else {
                $key = 'HTTP/1.1';
            }
        } else {
            $key .= ':';
        }
        $attr   = $this->getAttr('httpHeaders', []);
        $attr[] = ["{$key} {$value}", $replace];
        $this->setAttr('httpHeaders', $attr);
        return $this;
    }

    /**
     * Возвращает HTTP заголовки страницы
     * $this->httpHeaders
     *
     * @return array
     */
    protected function gethttpHeaders(): array
    {
        $now = gmdate('D, d M Y H:i:s') . ' GMT';

        $this->httpStatus()
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
//            ->header('Cache-Control', 'private, no-cache')
            ->header('Content-type', 'text/html; charset=utf-8')
            ->header('Date', $now)
            ->header('Last-Modified', $now)
            ->header('Expires', $now);

        return $this->getAttr('httpHeaders', []);
    }

    /**
     * Устанавливает HTTP статус страницы
     *
     * @return Page
     */
    protected function httpStatus(): self
    {
        $list = [
            403 => '403 Forbidden',
            404 => '404 Not Found',
            405 => '405 Method Not Allowed',
            501 => '501 Not Implemented',
            503 => '503 Service Unavailable',
        ];

        if (isset($list[$this->httpStatus])) {
            $this->header('HTTP/1.0', $list[$this->httpStatus]);
        }
        return $this;
    }

    /**
     * Дописывает в массив титула страницы новый элемент
     * $this->titles = ...
     *
     * @param string $value
     */
    public function settitles(string $value): void
    {
        $attr = $this->getAttr('titles', []);
        $attr[] = $value;
        $this->setAttr('titles', $attr);
    }

    /**
     * Добавление новой ошибки
     * $this->fIswev = ...
     *
     * @param array $value
     */
    public function setfIswev(array $value): void
    {
        $attr = $this->getAttr('fIswev', []);

        if (
            isset($value[0], $value[1])
            && \is_string($value[0])
            && \is_string($value[1])
        ) {
            $attr[$value[0]][] = $value[1];
        } else {
            $attr = \array_merge_recursive($attr, $value); // ???? добавить проверку?
        }

        $this->setAttr('fIswev', $attr) ;
    }

    /**
     * Возвращает массив хлебных крошек
     * Заполняет массив титула страницы
     *
     * @param mixed $crumbs
     *
     * @return array
     */
    protected function crumbs(...$crumbs): array
    {
        $result = [];
        $active = true;

        foreach ($crumbs as $crumb) {
            // модель
            if ($crumb instanceof Model) {
                do {
                    // для поиска
                    if (isset($crumb->name)) {
                        $name = $crumb->name;
                    // для раздела
                    } elseif (isset($crumb->forum_name)) {
                        $name = $crumb->forum_name;
                    // для темы
                    } elseif (isset($crumb->subject)) {
                        $name = \ForkBB\cens($crumb->subject);
                    // все остальное
                    } else {
                        $name = 'no name';
                    }

                    $result[] = [$crumb->link, $name, $active];
                    $active   = null;

                    if ($crumb->page > 1) {
                        $name .= ' ' . \ForkBB\__('Page %s', $crumb->page);
                    }

                    $this->titles = $name;
                    $crumb        = $crumb->parent;
                } while ($crumb instanceof Model && null !== $crumb->parent);
            // ссылка (передана массивом)
            } elseif (
                \is_array($crumb)
                && isset($crumb[0], $crumb[1])
            ) {
                $result[]     = [$crumb[0], $crumb[1], $active];
                $this->titles = $crumb[1];
            // строка
            } else {
                $result[]     = [null, (string) $crumb, $active];
                $this->titles = (string) $crumb;
            }
            $active = null;
        }
        // главная страница
        $result[] = [$this->c->Router->link('Index'), \ForkBB\__('Index'), $active];

        return \array_reverse($result);
    }
}
