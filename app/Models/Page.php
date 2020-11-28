<?php

declare(strict_types=1);

namespace ForkBB\Models;

use ForkBB\Core\Container;
use ForkBB\Models\Model;
use RuntimeException;
use function \ForkBB\__;

abstract class Page extends Model
{
    /**
     * Заголовки страницы
     * @var array
     */
    protected $pageHeaders = [];

    /**
     * Http заголовки
     * @var array
     */
    protected $httpHeaders = [];

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $container->Lang->load('common');

        $formats                 = $container->DATE_FORMATS;
        $formats[0]              = __($formats[0]);
        $formats[1]              = __($formats[1]);
        $container->DATE_FORMATS = $formats;

        $formats                 = $container->TIME_FORMATS;
        $formats[0]              = __($formats[0]);
        $formats[1]              = __($formats[1]);
        $container->TIME_FORMATS = $formats;

        $this->fIndex       = 'index'; # string      Указатель на активный пункт навигации
        $this->httpStatus   = 200;     # int         HTTP статус ответа для данной страницы
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

        $this->pageHeader('mainStyle', 'link', [
            'rel'  => 'stylesheet',
            'type' => 'text/css',
            'href' => $this->publicLink("/style/{$this->user->style}/style.css"),
        ]);

        $now = \gmdate('D, d M Y H:i:s') . ' GMT';

        $this //->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Cache-Control', 'private, no-cache')
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Date', $now)
            ->header('Last-Modified', $now)
            ->header('Expires', $now);
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare(): void
    {
        $this->boardNavigation();
        $this->iswevMessages();
    }

    /**
     * Задает массивы главной навигации форума
     */
    protected function boardNavigation(): void
    {
        $r = $this->c->Router;

        $navUser = [];
        $navGen  = [
            'index' => [
                $r->link('Index'),
                'Index',
                'Home page',
            ],
        ];

        if (
            '1' == $this->user->g_read_board
            && $this->user->viewUsers
        ) {
            $navGen['userlist'] = [
                $r->link('Userlist'),
                'User list',
                'List of users',
            ];
        }

        if (
            '1' == $this->c->config->o_rules
            && '1' == $this->user->g_read_board
            && (
                ! $this->user->isGuest
                || '1' == $this->c->config->o_regs_allow
            )
        ) {
            $navGen['rules'] = [
                $r->link('Rules'),
                'Rules',
                'Board rules',
            ];
        }

        if (
            '1' == $this->user->g_read_board
            && '1' == $this->user->g_search
        ) {
            $sub = [];
            $sub['latest'] = [
                $r->link(
                    'SearchAction',
                    [
                        'action' => 'latest_active_topics',
                    ]
                ),
                'Latest active topics',
                'Find latest active topics',
            ];
            if (! $this->user->isGuest) {
                $sub['with-your-posts'] = [
                    $r->link(
                        'SearchAction',
                        [
                            'action' => 'topics_with_your_posts',
                        ]
                    ),
                    'Topics with your posts',
                    'Find topics with your posts',
                ];
            }
            $sub['unanswered'] = [
                $r->link(
                    'SearchAction',
                    [
                        'action' => 'unanswered_topics',
                    ]
                ),
                'Unanswered topics',
                'Find unanswered topics',
            ];

            $navGen['search'] = [
                $r->link('Search'),
                'Search',
                'Search topics and posts',
                $sub
            ];
        }

        if ($this->user->isGuest) {
            $navUser['register'] = [
                $r->link('Register'),
                'Register',
                'Register',
            ];
            $navUser['login'] = [
                $r->link('Login'),
                'Login',
                'Login',
            ];
        } else {
            $navUser['profile'] = [
                $this->user->link,
                __('User %s', $this->user->username),
                'Your profile',
            ];

            // New PMS
            if (
                '1' == $this->c->config->o_pms_enabled
                && (
                    $this->user->isAdmin
                    || $this->user->messages_new > 0
                )
            ) { //????
                $navUser['pmsnew'] = [
                    'pmsnew.php',
                    'PM',
                    'Private messages',
                ]; //'<li id="nav"'.((PUN_ACTIVE_PAGE == 'pms_new' || $user['messages_new'] > 0) ? ' class="isactive"' : '').'><a href="pmsnew.php">'.__('PM').(($user['messages_new'] > 0) ? ' (<span'.((empty($this->c->config->o_pms_flasher) || PUN_ACTIVE_PAGE == 'pms_new') ? '' : ' class="remflasher"' ).'>'.$user['messages_new'].'</span>)' : '').'</a></li>';
            }
            // New PMS

            if ($this->user->isAdmMod) {
                $navUser['admin'] = [
                    $r->link('Admin'),
                    'Admin',
                    'Administration functions',
                ];
            }

            $navUser['logout'] = [
                $r->link(
                    'Logout',
                    [
                        'token' => null,
                    ]
                ),
                'Logout',
                'Logout',
            ];
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
                   if (isset($navGen[$matches[4][$i]])) {
                       $navGen[$matches[4][$i]] = [$matches[3][$i], $matches[2][$i]];
                   } else {
                       $navGen = \array_merge(
                           \array_slice($navGen, 0, (int) $matches[1][$i]),
                           [$matches[4][$i] => [$matches[3][$i], $matches[2][$i]]],
                           \array_slice($navGen, (int) $matches[1][$i])
                       );
                   }
               }
            }
        }

        $this->fNavigation     = $navGen;
        $this->fNavigationUser = $navUser;
    }

    /**
     * Задает вывод различных сообщений по условиям
     */
    protected function iswevMessages(): void
    {
        if (
            '1' == $this->c->config->o_maintenance
            && $this->user->isAdmin
        ) {
            $this->fIswev = ['w', __('Maintenance mode enabled', $this->c->Router->link('AdminMaintenance'))];
        }

        if (
            $this->user->isAdmMod
            && $this->user->last_report_id < $this->c->reports->lastId()
        ) {
            $this->fIswev = ['i', __('New reports', $this->c->Router->link('AdminReports'))];
        }
    }

    /**
     * Возвращает title страницы
     * $this->pageTitle
     */
    protected function getpageTitle(array $titles = []): string
    {
        if (empty($titles)) {
            $titles = $this->titles;
        }
        $titles[] = $this->c->config->o_board_title;

        return \implode(__('Title separator'), $titles);
    }

    /**
     * Задает/получает заголовок страницы
     */
    public function pageHeader(string $name, string $type, array $values = null) /* : mixed */
    {
        if (null === $values) {
            return $this->pageHeaders["{$name}_{$type}"] ?? null;
        } else {
            $this->pageHeaders["{$name}_{$type}"] = [
                'type'   => $type,
                'values' => $values
            ];

            return $this;
        }
    }

    /**
     * Возвращает массива заголовков страницы
     * $this->pageHeaders
     */
    protected function getpageHeaders(): array
    {
        if ($this->canonical) {
            $this->pageHeader('canonical', 'link', [
                'rel'  => 'canonical',
                'href' => $this->canonical,
            ]);
        }
        if ($this->robots) {
            $this->pageHeader('robots', 'meta', [
                'name'    => 'robots',
                'content' => $this->robots,
            ]);
        }

        return $this->pageHeaders;
    }

    /**
     * Добавляет/заменяет/удаляет HTTP заголовок
     */
    public function header(string $header, ?string $value, bool $replace = true): Page
    {
        $key = \strtolower($header);

        if ('http/' === \substr($key, 0, 5)) {
            $key     = 'http/';
            $replace = true;

            if (
                ! empty($_SERVER['SERVER_PROTOCOL'])
                && 'HTTP/' === \strtoupper(\substr($_SERVER['SERVER_PROTOCOL'], 0, 5))
            ) {
                $header = 'HTTP/' . \substr($_SERVER['SERVER_PROTOCOL'], 5);
            }
        } else {
            $header .= ':';
        }

        if (null === $value) {
            unset($this->httpHeaders[$key]);
        } elseif (true === $replace || empty($this->httpHeaders[$key])) {
            $this->httpHeaders[$key] = [
                ["{$header} {$value}", $replace],
            ];
        } else {
            $this->httpHeaders[$key][] = ["{$header} {$value}", $replace];
        }

        return $this;
    }

    /**
     * Возвращает HTTP заголовки страницы
     * $this->httpHeaders
     */
    protected function gethttpHeaders(): array
    {
        $this->httpStatus();

        return $this->httpHeaders;
    }

    /**
     * Устанавливает HTTP статус страницы
     */
    protected function httpStatus(): Page
    {
        $list = [
            302 => '302 Moved Temporarily',
            400 => '400 Bad Request',
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
     */
    protected function crumbs(/* mixed */ ...$crumbs): array
    {
        $result = [];
        $active = true;

        foreach ($crumbs as $crumb) {
            // модель
            if ($crumb instanceof Model) {
                do {
                    $name     = $crumb->name ?? '<no name>';
                    $result[] = [$crumb, $name, $active];
                    $active   = null;

                    if ($crumb->page > 1) {
                        $name .= __(' Page %s', $crumb->page);
                    }

                    $this->titles = $name;
                    $crumb        = $crumb->parent;
                } while ($crumb instanceof Model && null !== $crumb->parent);
            // ссылка (передана массивом)
            } elseif (
                \is_array($crumb)
                && isset($crumb[0], $crumb[1])
            ) {
                $result[]     = [$crumb[0], (string) $crumb[1], $active];
                $this->titles = $crumb[1];
            // строка
            } else {
                $result[]     = [null, (string) $crumb, $active];
                $this->titles = (string) $crumb;
            }
            $active = null;
        }
        // главная страница
        $result[] = [$this->c->Router->link('Index'), __('Index'), $active];

        return \array_reverse($result);
    }

    /**
     * Возвращает url для $path заданного в каталоге public
     * Ведущий слеш обязателен O_o
     */
    public function publicLink(string $path): string
    {
        $fullPath = $this->c->DIR_PUBLIC . $path;

        if (\is_file($fullPath)) {
            $time = \filemtime($fullPath) ?: '0';

            if (\preg_match('%^(.+)\.([^.\\/]++)$%D', $path, $matches)) {
                return $this->c->PUBLIC_URL . "{$matches[1]}.v.{$time}.{$matches[2]}";
            }
        }

        return $this->c->PUBLIC_URL . $path;
    }
}
