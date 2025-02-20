<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models;

use ForkBB\Core\Container;
use ForkBB\Models\Model;
use function \ForkBB\__;

abstract class Page extends Model
{
    const FI_INDEX = 'index';
    const FI_USERS = 'userlist';
    const FI_RULES = 'rules';
    const FI_SRCH  = 'search';
    const FI_REG   = 'register';
    const FI_LOGIN = 'login';
    const FI_PROFL = 'profile';
    const FI_PM    = 'pm';
    const FI_ADMIN = 'admin';
    const FI_LGOUT = 'logout';
    const FI_DRAFT = 'drafts';

    /**
     * Заголовки страницы
     */
    protected array $pageHeaders = [];

    /**
     * Http заголовки
     */
    protected array $httpHeaders = [];

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $container->Lang->load('common');

        $this->identifier   = 'unknown';      # string|array Идентификатор(ы) для установки классов элемента #fork
        $this->fIndex       = self::FI_INDEX; # string       Указатель на активный пункт навигации
        $this->httpStatus   = 200;            # int          HTTP статус ответа для данной страницы
//      $this->nameTpl      = null;           # null|string  Имя шаблона
//      $this->titles       = [];             # array        Массив титула страницы | setTitles()
//      $this->fIswev       = [];             # array        Массив info, success, warning, error, validation информации
//      $this->onlinePos    = '';             # null|string  Позиция для таблицы онлайн текущего пользователя
        $this->onlineDetail = false;          # null|bool    Формировать данные по посетителям online или нет
        $this->onlineFilter = true;           # bool         Посетители только по текущей странице или по всем
//      $this->robots       = '';             # string       Переменная для meta name="robots"
//      $this->canonical    = '';             # string       Переменная для link rel="canonical"
        $this->hhsLevel     = 'common';       # string       Ключ для $c->HTTP_HEADERS (для вывода заголовков HTTP из конфига)

        $this->fTitle       = $container->config->o_board_title;
        $this->fDescription = $container->config->o_board_desc;
        $this->fRootLink    = $container->Router->link('Index');

        $this->mDescription = $this->c->config->s_meta_desc;

        if (! empty($this->c->config->a_og_image['file'])) {
            $this->mOgImage  = $this->c->PUBLIC_URL . '/img/og/' . $this->c->config->a_og_image['file'];
            $this->mOgImageX = $this->c->config->a_og_image['width'] ?? null;
            $this->mOgImageY = $this->c->config->a_og_image['height'] ?? null;
        }

        if (1 === $container->config->b_announcement) {
            $this->fAnnounce = $container->config->o_announcement_message;
        }

        // передача текущего юзера и его правил в шаблон
        $this->user      = $this->c->user;
        $this->userRules = $this->c->userRules;

        $this->pageHeader('mainStyle', 'link', 10000, [
            'rel'  => 'stylesheet',
            'type' => 'text/css',
            'href' => $this->publicLink("/style/{$this->user->style}/style.css"),
        ]);

        $now = \gmdate('D, d M Y H:i:s') . ' GMT';

        $this //->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Cache-Control', 'private, no-cache')
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Date', $now)
            ->header('Last-Modified', $now);
            //->header('Expires', $now);
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare(): void
    {
        // вспышка нового личного сообщения
        if (
            null !== $this->onlinePos
            && ! $this->user->isGuest
            && 1 === $this->user->u_pm_flash
        ) {
            $this->fPMFlash         = true;
            $this->user->u_pm_flash = 0;

            $this->c->users->update($this->user);
        }

        $this->pageHeader('commonJS', 'script', 10000, [
            'src' => $this->publicLink('/js/common.js'),
        ]);

        if ($this->useMediaJS) {
            $this->pageHeader('mediaJS', 'script', 10000, [
                'src' => $this->publicLink('/js/media.js'),
            ]);
        }

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
            self::FI_INDEX => [
                $r->link('Index'),
                'Index',
                'Home page',
            ],
        ];

        if (
            1 === $this->user->g_read_board
            && $this->userRules->viewUsers
        ) {
            $navGen[self::FI_USERS] = [
                $r->link('Userlist'),
                'User list',
                'List of users',
            ];
        }

        if (
            1 === $this->c->config->b_rules
            && 1 === $this->user->g_read_board
            && (
                ! $this->user->isGuest
                || 1 === $this->c->config->b_regs_allow
            )
        ) {
            $navGen[self::FI_RULES] = [
                $r->link('Rules'),
                'Rules',
                'Board rules',
            ];
        }

        if (
            1 === $this->user->g_read_board
            && 1 === $this->user->g_search
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

            $navGen[self::FI_SRCH] = [
                $r->link('Search'),
                'Search',
                'Search topics and posts',
                $sub
            ];
        }

        if ($this->user->isGuest) {
            if (1 === $this->c->config->b_regs_allow) {
                $navUser[self::FI_REG] = [
                    $r->link('Register'),
                    'Register',
                    'Register',
                ];
            }

            $navUser[self::FI_LOGIN] = [
                $r->link('Login'),
                'Login',
                'Login',
            ];

        } else {
            $navUser[self::FI_PROFL] = [
                $this->user->link,
                ['User %s', $this->user->username],
                'Your profile',
            ];

            if ($this->user->usePM) {
                $tmpPM = [];

                if (1 !== $this->user->u_pm) {
                    $tmpPM[] = 'pmoff';
                }

                if ($this->user->u_pm_num_new > 0) {
                    $tmpPM[] = 'pmnew';
                }

                $navUser[self::FI_PM] = [
                    $r->link('PM'),
                    $this->user->u_pm_num_new > 0 ? ['PM %s', $this->user->u_pm_num_new] : 'PM',
                    'Private messages',
                    null,
                    $tmpPM ?: null,
                ];
            }

            if (
                $this->userRules->useDraft
                && $this->user->num_drafts > 0
            ) {
                $navUser[self::FI_DRAFT] = [
                    $r->link('Drafts'),
                    ['Drafts (%s)', $this->user->num_drafts],
                    'Your drafts',
                    null,
                    ['pmnew'],
                ];
            }

            if ($this->user->isAdmMod) {
                $navUser[self::FI_ADMIN] = [
                    $r->link('Admin'),
                    'Admin',
                    'Administration functions',
                ];
            }

            $navUser[self::FI_LGOUT] = [
                $r->link('Logout'),
                'Logout',
                'Logout',
            ];
        }

        if (
            1 === $this->user->g_read_board
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
                       $navGen[$matches[4][$i]] = [$matches[3][$i], $matches[2][$i], $matches[2][$i]];

                   } else {
                       $navGen = \array_merge(
                           \array_slice($navGen, 0, (int) $matches[1][$i]),
                           [$matches[4][$i] => [$matches[3][$i], $matches[2][$i], $matches[2][$i]]],
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
            1 === $this->c->config->b_maintenance
            && $this->user->isAdmin
        ) {
            if ($this->c->MAINTENANCE_OFF) {
                $this->fIswev = [FORK_MESS_ERR, ['Maintenance mode enabled off', $this->c->Router->link('AdminMaintenance')]];

            } else {
                $this->fIswev = [FORK_MESS_WARN, ['Maintenance mode enabled', $this->c->Router->link('AdminMaintenance')]];
            }
        }

        if (
            $this->user->isAdmMod
            && $this->user->last_report_id < $this->c->reports->lastId()
        ) {
            $this->fIswev = [FORK_MESS_INFO, ['New reports', $this->c->Router->link('AdminReports')]];
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

        $titles[] = ['%s', $this->c->config->o_board_title];

        return \implode(__('Title separator'), \array_map('\\ForkBB\\__', $titles));
    }

    /**
     * Задает/получает заголовок страницы
     */
    public function pageHeader(string $name, string $type, int $weight = 0, ?array $values = null): mixed
    {
        if (null === $values) {
            return $this->pageHeaders["{$name}_{$type}"] ?? null;

        } else {
            $this->pageHeaders["{$name}_{$type}"] = [
                'weight' => $weight,
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
        if (1 === $this->user->g_search) {
            $this->pageHeader('opensearch', 'link', 0, [
                'rel'   => 'search',
                'type'  => 'application/opensearchdescription+xml',
                'href'  => $this->c->Router->link('OpenSearch'),
                'title' => $this->fTitle,
            ]);
        }

        \uasort($this->pageHeaders, function (array $a, array $b) {
            return $b['weight'] <=> $a['weight'];
        });

        return $this->pageHeaders;
    }

    /**
     * Добавляет/заменяет/удаляет HTTP заголовок
     */
    public function header(string $header, ?string $value, bool $replace = true): Page
    {
        $key = \strtolower($header);

        if (\str_starts_with($key, 'http/')) {
            $key     = 'http/';
            $replace = true;

            if (
                ! empty($_SERVER['SERVER_PROTOCOL'])
                && \in_array($_SERVER['SERVER_PROTOCOL'], ['HTTP/1.1', 'HTTP/2', 'HTTP/3'], true)
            ) {
                $header = $_SERVER['SERVER_PROTOCOL'];
            }

        } else {
            $header .= ':';
        }

        if (null === $value) {
            unset($this->httpHeaders[$key]);

        } elseif (
            true === $replace
            || empty($this->httpHeaders[$key])
        ) {
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
        foreach ($this->c->HTTP_HEADERS[$this->hhsLevel] as $header => $value) {
            if ('Content-Security-Policy' === $header) {
                if (
                    $this->needUnsafeInlineStyle
                    || (
                        $this->c->isInit('Parser')
                        && $this->c->Parser->inlineStyle()
                    )
                ) {
                    $this->addRulesToCSP(['style-src' => '\'unsafe-inline\'']);
                }

                $value = $this->updateCSP($value);
            }

            $this->header($header, $value);
        }

        $this->httpStatus();

        return $this->httpHeaders;
    }

    /**
     * Массив для дополнительных правил Content-Security-Policy
     */
    protected array $addCSPRules = [];

    /**
     * Массив допустимых директив Content-Security-Policy
     */
    protected array $CSPDirectives = [
        'default-src'     => false,
        'script-src'      => 'default-src',
        'script-src-elem' => 'script-src',
        'script-src-attr' => 'script-src',
        'style-src'       => 'default-src',
        'style-src-elem'  => 'style-src',
        'style-src-attr'  => 'style-src',
        'font-src'        => 'default-src',
        'img-src'         => 'default-src',
        'connect-src'     => 'default-src',
        'worker-src'      => 'default-src',
        'object-src'      => 'default-src',
        'media-src'       => 'default-src',
        'manifest-src'    => 'default-src',
        'child-src'       => 'default-src',
        'frame-src'       => 'child-src',
        'worker-src'      => 'child-src',
        'base-uri'        => false,
        'form-action'     => false,
        'frame-ancestors' => false,
    ];

    /**
     * Сохраняет дополнительные правила для изменения Content-Security-Policy
     * Ключи массива - это имена директив, значения - добавляемые правила
     */
    public function addRulesToCSP(array $rules): Page
    {
        foreach ($rules as $directive => $rule) {
            if (isset($this->CSPDirectives[$directive])) {
                foreach (\explode(' ', $rule) as $key) {
                    if (! empty($key)) {
                        $this->addCSPRules[$directive][$key] = true;
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Вносит изменения в Content-Security-Policy на основе дополнительных правил
     */
    protected function updateCSP(string $csp): string
    {
        if (empty($this->addCSPRules)) {
            return $csp;
        }

        $raw   = \array_map('\\trim', \explode(';', $csp));
        $rules = [];

        foreach ($raw as $cur) {
            $parts     = \explode(' ', $cur);
            $directive = \array_shift($parts);

            foreach ($parts as $key) {
                if (! empty($key)) {
                    $rules[$directive][$key] = true;
                }
            }
        }

        foreach ($this->CSPDirectives as $directive => $parent) {
            if (empty($this->addCSPRules[$directive])) {
                continue;
            }

            $addDirRules = $this->addCSPRules[$directive];

            if (isset($addDirRules['\'none\''])) {
                $rules[$directive] = ['\'none\'' => true];
            }

            if (empty($rules[$directive])) {
                $oldDirRules = null;

                while (
                    \is_string($parent)
                    && null === $oldDirRules
                ) {
                    if (empty($rules[$parent])) {
                        $parent = $this->CSPDirectives[$parent];

                    } else {
                        $oldDirRules = $rules[$parent];
                    }
                }

            } else {
                $oldDirRules = $rules[$directive];
                $parent      = $directive;
            }

            if (isset($oldDirRules['\'none\''])) {
                $rules[$directive] = $addDirRules;

            } else {
                $rules[$directive] = \array_merge($oldDirRules, $addDirRules);
            }
        }

        $csp = '';

        foreach ($rules as $directive => $parts) {
            $csp .= $directive . ' ' . \implode(' ', \array_keys($parts)) . ';';
        }

        return $csp;
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
    public function settitles(string|array $value): void
    {
        $attr   = $this->getModelAttr('titles', []);
        $attr[] = $value;

        $this->setModelAttr('titles', $attr);
    }

    /**
     * Добавление новой ошибки
     * $this->fIswev = ...
     */
    public function setfIswev(array $value): void
    {
        $attr = $this->getModelAttr('fIswev', []);

        if (
            isset($value[0], $value[1])
            && \is_string($value[0])
            && 2 === \count($value)
        ) {
            $attr[$value[0]][] = $value[1];

        } else {
            $attr = \array_merge_recursive($attr, $value); // ???? добавить проверку?
        }

        $this->setModelAttr('fIswev', $attr) ;
    }

    /**
     * Возвращает массив хлебных крошек
     * Заполняет массив титула страницы
     */
    protected function crumbs(mixed ...$crumbs): array
    {
        $result = [];
        $active = true;
        $ext    = null;

        foreach ($crumbs as $crumb) {
            // модель
            if ($crumb instanceof Model) {
                do {
                    $name = $crumb->name ?? 'NO NAME';

                    if (! \is_array($name)) {
                        $name = ['%s', $name];
                    }

                    $result[]     = [$crumb, $name, null, null, $active, $ext];
                    $active       = null;
                    $this->titles = $name;

                    if ($crumb->page > 1) {
                        $this->titles = ['Page %s', $crumb->page];
                    }

                    if ($crumb->linkCrumbExt) {
                        $ext = [$crumb->linkCrumbExt, $crumb->textCrumbExt ?? '#'];

                    } else {
                        $ext = null;
                    }

                    $crumb = $crumb->parent;
                } while (
                    $crumb instanceof Model
                    && null !== $crumb->parent
                );

            // ссылка (передана массивом)
            } elseif (\is_array($crumb)) {
                $result[]     = [$crumb[0], $crumb[1], $crumb[2] ?? null, $crumb[3] ?? null, $active, $crumb[4] ?? $ext];
                $this->titles = $crumb[1];
                $ext          = null;

            // строка
            } else {
                $result[]     = [null, (string) $crumb, null, null, $active, $ext];
                $this->titles = (string) $crumb;
                $ext          = null;
            }

            $active = null;
        }

        // главная страница
        $result[] = [$this->c->Router->link('Index'), 'Index', null, 'index', $active, $ext];

        return \array_reverse($result);
    }

    /**
     * Возвращает url для $path заданного в каталоге public
     * Если для файла name.ext есть файл name.min.ext, то url возвращается для него
     * Ведущий слеш обязателен O_o
     */
    public function publicLink(string $path, bool $returnEmpty = false): string
    {
        if (\preg_match('%^(.+)(\.[^.\\/]++)$%D', $path, $matches)) {
            $variants = [
                $matches[1] . '.min' => $matches[2],
                $matches[1]          => $matches[2],
            ];

        } else {
            $variants = [
                $path                => '',
            ];
        }

        foreach ($variants as $start => $end) {
            $fullPath = $this->c->DIR_PUBLIC . $start . $end;

            if (\is_file($fullPath)) {
                if ('' === $end) {
                    return $this->c->PUBLIC_URL . $start;

                } else {
                    $time = \filemtime($fullPath) ?: '0';

                    return $this->c->PUBLIC_URL . $start . '.v.' . $time . $end;
                }
            }
        }

        return $returnEmpty ? '' : $this->c->PUBLIC_URL . $path;
    }
}
