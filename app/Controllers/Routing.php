<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Controllers;

use ForkBB\Core\Container;
use ForkBB\Models\Page;

class Routing
{
    public function __construct(protected Container $c)
    {
    }

    /**
     * Маршрутиризация
     */
    public function routing(): Page
    {
        $user      = $this->c->user;
        $userRules = $this->c->userRules;
        $config    = $this->c->config;
        $r         = $this->c->Router;

        $r->add(
            $r::GET,
            '/sitemap{id:\d*}.xml',
            'Sitemap:view',
            'Sitemap'
        );

        // регистрация/вход/выход
        if ($user->isGuest) {
            // вход
            $r->add(
                $r::DUO,
                '/login',
                'Auth:login',
                'Login'
            );
            // забыли кодовую фразу
            $r->add(
                $r::DUO,
                '/login/forget',
                'Auth:forget',
                'Forget'
            );
            // смена кодовой фразы
            $r->add(
                $r::DUO,
                '/login/{id|i:[1-9]\d*}/{key}/{hash}',
                'Auth:changePass',
                'ChangePassword'
            );

            // регистрация
            if (1 === $config->b_regs_allow) {
                $r->add(
                    $r::GET,
                    '/registration',
                    'Rules:confirmation',
                    'Register'
                );
                $r->add(
                    $r::PST,
                    '/registration/agree',
                    'Register:reg',
                    'RegisterForm'
                );
                $r->add(
                    $r::GET,
                    '/registration/activate/{id|i:[1-9]\d*}/{key}/{hash}',
                    'Register:activate',
                    'RegActivate'
                );
            }
        } else {
            // выход
            $r->add(
                $r::GET,
                '/logout/{token}',
                'Auth:logout',
                'Logout'
            );
            // обработка "кривых" перенаправлений с логина и регистрации
            $r->add(
                $r::GET,
                '/login[/{tail:.*}]',
                'Redirect:toIndex',
                'Login' // <-- для переадресации со страницы изменения пароля
            );
            $r->add(
                $r::GET,
                '/registration[/{tail:.*}]',
                'Redirect:toIndex'
            );
        }

        // OAuth
        if (
            1 === $config->b_oauth_allow
            || $user->isAdmin
        ) {
            $r->add(
                $r::GET,
                '/reglog/callback/{name}',
                'RegLog:callback',
                'RegLogCallback'
            );
        }

        if (1 === $config->b_oauth_allow) {
            $r->add(
                $r::PST,
                '/reglog/redirect/{type}',
                'RegLog:redirect',
                'RegLogRedirect'
            );
        }

        // просмотр разрешен
        if (1 === $user->g_read_board) {
            // главная
            $r->add(
                $r::GET,
                '/',
                'Index:view',
                'Index'
            );
            $r->add(
                $r::GET,
                '/index.php',
                'Redirect:toIndex'
            );
            $r->add(
                $r::GET,
                '/index.html',
                'Redirect:toIndex'
            );

            // правила
            if (
                1 === $config->b_rules
                && (
                    ! $user->isGuest
                    || 1 === $config->b_regs_allow
                )
            ) {
                $r->add(
                    $r::GET,
                    '/rules',
                    'Rules:view',
                    'Rules'
                );
            }

            // поиск
            if (1 === $user->g_search) {
                $r->add(
                    $r::GET,
                    '/search[/simple/{keywords}[/{page|i:[1-9]\d*}]]',
                    'Search:view',
                    'Search'
                );
                $r->add(
                    $r::PST,
                    '/search',
                    'Search:view'
                );
                $r->add(
                    $r::GET,
                    '/search/advanced[/{keywords}/{author}/{forums}/{serch_in:\d}/{sort_by:\d}/{sort_dir:\d}/{show_as:\d}[/{page|i:[1-9]\d*}]]',
                    'Search:viewAdvanced',
                    'SearchAdvanced'
                );
                $r->add(
                    $r::PST,
                    '/search/advanced',
                    'Search:viewAdvanced'
                );
                $r->add(
                    $r::GET,
                    '/search[/user/{uid|i:[1-9]\d*}]/{action:(?!search)[a-z_]+}[/in_forum/{forum|i:[1-9]\d*}][/{page|i:[1-9]\d*}]',
                    'Search:action',
                    'SearchAction'
                );
                $r->add(
                    $r::GET,
                    '/opensearch.xml',
                    'Misc:opensearch',
                    'OpenSearch'
                );
            }

            // юзеры
            if ($userRules->viewUsers) {
                // список пользователей
                $r->add(
                    $r::GET,
                    '/userlist[/{group:all|[1-9]\d*}/{sort:username|registered|num_posts}/{dir:ASC|DESC}/{name}][/{page|i:[1-9]\d*}]',
                    'Userlist:view',
                    'Userlist'
                );
                $r->add(
                    $r::PST,
                    '/userlist',
                    'Userlist:view'
                );
                // юзеры
                $r->add(
                    $r::GET,
                    '/user/{id|i:[1-9]\d*}/{name}',
                    'ProfileView:view',
                    'User'
                );

                $idPattern = '[1-9]\d*';
            } elseif (! $user->isGuest) {
                // только свой профиль
                $r->add(
                    $r::GET,
                    '/user/{id|i:' . $user->id . '}/{name}',
                    'ProfileView:view',
                    'User'
                );

                $idPattern = (string) $user->id;
            }

            // юзеры - редактирование
            if (! $user->isGuest) {
                $r->add(
                    $r::DUO,
                    '/user/{id|i:' . $idPattern . '}/edit/profile',
                    'ProfileEdit:edit',
                    'EditUserProfile'
                );
                $r->add(
                    $r::DUO,
                    '/user/{id|i:' . $idPattern . '}/edit/config',
                    'ProfileConfig:config',
                    'EditUserBoardConfig'
                );
                $r->add(
                    $r::DUO,
                    '/user/{id|i:' . $idPattern . '}/edit/profile/email',
                    'ProfileEmail:email',
                    'EditUserEmail'
                );
                $r->add(
                    $r::DUO,
                    '/user/{id|i:' . $idPattern . '}/edit/profile/passphrase',
                    'ProfilePass:pass',
                    'EditUserPass'
                );
                $r->add(
                    $r::DUO,
                    '/user/{id|i:' . $idPattern . '}/edit/profile/moderation',
                    'ProfileMod:moderation',
                    'EditUserModeration'
                );
                $r->add(
                    $r::DUO,
                    '/user/{id|i:' . $idPattern . '}/edit/profile/about_me',
                    'ProfileAboutMe:about',
                    'EditUserAboutMe'
                );
            }

            if ($user->isAdmin) {
                $r->add(
                    $r::GET,
                    '/user/{id|i:[1-9]\d*}/recalculate/{token}',
                    'ProfileEdit:recalc',
                    'EditUserRecalc'
                );
            }

            if (! $user->isGuest) {
                // настройка поиска
                $r->add(
                    $r::DUO,
                    '/user/{id|i:' . $user->id . '}/edit/config/search',
                    'ProfileSearch:config',
                    'EditUserSearch'
                );
                // удаление своего профиля
                $r->add(
                    $r::DUO,
                    '/user/{id|i:' . $user->id . '}/delete/profile',
                    'ProfileDelete:delete',
                    'DeleteUserProfile'
                );
            }

            // управление аккаунтами OAuth
            if (
                ! $user->isGuest
                && 1 === $config->b_oauth_allow
            ) {
                $r->add(
                    $r::GET,
                    '/user/{id|i:' . $user->id . '}/edit/profile/oauth',
                    'ProfileOAuth:list',
                    'EditUserOAuth'
                );
                $r->add(
                    $r::DUO,
                    '/user/{id|i:' . $user->id . '}/edit/profile/oauth/{key}',
                    'ProfileOAuth:action',
                    'EditUserOAuthAction'
                );
            }

            // смена своего email
            if (! $user->isGuest) {
                $r->add(
                    $r::GET,
                    '/user/{id|i:' . $user->id . '}/{email}/{key}/{hash}',
                    'ProfileEmail:setEmail',
                    'SetNewEmail'
                );
            }

            if (! $user->isGuest) {
                // пометка разделов прочитанными
                $r->add(
                    $r::GET,
                    '/forum/{id|i:\d+}/markread/{token}',
                    'Misc:markread',
                    'MarkRead'
                );
                // скролирование до топика
                $r->add(
                    $r::GET,
                    '/forum/scroll/topic/{tid|i:[1-9]\d*}',
                    'Forum:scrollToTopic',
                    'ForumScrollToTopic'
                );
            }

            // разделы
            $r->add(
                $r::GET,
                '/forum/{id|i:[1-9]\d*}/{name}[/{page|i:[1-9]\d*}]',
                'Forum:view',
                'Forum'
            );
            $r->add(
                $r::DUO,
                '/forum/{id|i:[1-9]\d*}/new/topic',
                'Post:newTopic',
                'NewTopic'
            );
            // темы
            $r->add(
                $r::GET,
                '/topic/{id|i:[1-9]\d*}/{name}[/{page|i:[1-9]\d*}]',
                'Topic:viewTopic',
                'Topic'
            );
            $r->add(
                $r::GET,
                '/topic/{id|i:[1-9]\d*}/view/new',
                'Topic:viewNew',
                'TopicViewNew'
            );
            $r->add(
                $r::GET,
                '/topic/{id|i:[1-9]\d*}/view/unread',
                'Topic:viewUnread',
                'TopicViewUnread'
            );
            $r->add(
                $r::GET,
                '/topic/{id|i:[1-9]\d*}/view/last',
                'Topic:viewLast',
                'TopicViewLast'
            );
            $r->add(
                $r::GET,
                '/topic/{id|i:[1-9]\d*}/new/reply[/{quote|i:[1-9]\d*}]',
                'Post:newReply',
                'NewReply'
            );
            $r->add(
                $r::PST,
                '/topic/{id|i:[1-9]\d*}/new/reply',
                'Post:newReply'
            );
            // сообщения
            $r->add(
                $r::GET,
                '/post/{id|i:[1-9]\d*}#p{id}',
                'Topic:viewPost',
                'ViewPost'
            );

            if (! $user->isGuest) {
                $r->add(
                    $r::DUO,
                    '/post/{id|i:[1-9]\d*}/edit',
                    'Edit:edit',
                    'EditPost'
                );
                $r->add(
                    $r::DUO,
                    '/post/{id|i:[1-9]\d*}/delete',
                    'Delete:delete',
                    'DeletePost'
                );
                $r->add(
                    $r::GET,
                    '/post/{id|i:[1-9]\d*}/solution/{token}',
                    'Misc:solution',
                    'ChSolution'
                );
            }

            if ($user->isAdmin) {
                $r->add(
                    $r::DUO,
                    '/post/{id|i:[1-9]\d*}/change',
                    'Edit:change',
                    'ChangeAnD'
                );
            }

            // сигналы (репорты)
            if (
                ! $user->isAdmin
                && ! $user->isGuest
            ) {
                $r->add(
                    $r::DUO,
                    '/post/{id|i:[1-9]\d*}/report',
                    'Report:report',
                    'ReportPost'
                );
            }

            // отправка email
            if (
                ! $user->isGuest
                && 1 === $user->g_send_email
            ) {
                $r->add(
                    $r::DUO,
                    '/send_email/{id|i:[1-9]\d*}/{hash}',
                    'Email:email',
                    'SendEmail'
                );
            }

            // feed
            $r->add(
                $r::GET,
                '/feed/{type:atom|rss}[/forum/{fid|i:[1-9]\d*}][/topic/{tid|i:[1-9]\d*}]',
                'Feed:view',
                'Feed'
            );

            // подписки
            if (
                ! $user->isGuest
                && ! $user->isUnverified
            ) {
                $r->add(
                    $r::GET,
                    '/forum/{fid|i:[1-9]\d*}/{type:subscribe|unsubscribe}/{token}',
                    'Misc:forumSubscription',
                    'ForumSubscription'
                );
                $r->add(
                    $r::GET,
                    '/topic/{tid|i:[1-9]\d*}/{type:subscribe|unsubscribe}/{token}',
                    'Misc:topicSubscription',
                    'TopicSubscription'
                );
            }

            // личные сообщения
            if ($user->usePM) {
                $r->add(
                    $r::GET,
                    '/pm',
                    'PM:action',
                    'PM'
                );
                $r->add(
                    $r::DUO,
                    '/pm[/user/{second}][/{action}[/{more1|i:[1-9]\d*}[/{more2}]]][#p{numPost}]',
                    'PM:action',
                    'PMAction'
                );
            }

            // реакции
            if (
                ! $user->isGuest
                && $userRules->showReaction
            ) {
                $r->add(
                    $r::PST,
                    '/post/{id|i:[1-9]\d*}/reaction/{token}',
                    'Reaction:reaction',
                    'Reaction'
                );
            }
        }

        // опросы
        if ($userRules->usePoll) {
            $r->add(
                $r::PST,
                '/poll/{tid|i:[1-9]\d*}',
                'Poll:vote',
                'Poll'
            );
        }

        // черновики
        if ($userRules->useDraft) {
            $r->add(
                $r::GET,
                '/drafts[/{page|i:[1-9]\d*}]',
                'Drafts:view',
                'Drafts'
            );

            $r->add(
                $r::DUO,
                '/draft/{did|i:[1-9]\d*}/edit',
                'Post:draft',
                'Draft'
            );

            $r->add(
                $r::DUO,
                '/draft/{did|i:[1-9]\d*}/delete',
                'Delete:deleteDraft',
                'DeleteDraft'
            );
        }

        // админ и модератор
        if ($user->isAdmMod) {
            $r->add(
                $r::GET,
                '/admin/',
                'AdminIndex:index',
                'Admin'
            );
            $r->add(
                $r::GET,
                '/admin/statistics',
                'AdminStatistics:statistics',
                'AdminStatistics'
            );

            if ($userRules->viewIP) {
                $r->add(
                    $r::GET,
                    '/admin/get/host/{ip:[0-9a-fA-F:.]+}',
                    'AdminHost:view',
                    'AdminHost'
                );
                $r->add(
                    $r::GET,
                    '/admin/users/user/{id|i:[1-9]\d*}[/{page|i:[1-9]\d*}]',
                    'AdminUsersStat:view',
                    'AdminUserStat'
                );
            }

            $r->add(
                $r::DUO,
                '/admin/users',
                'AdminUsers:view',
                'AdminUsers'
            );
            $r->add(
                $r::DUO,
                '/admin/users/result/{data}[/{page|i:[1-9]\d*}]',
                'AdminUsersResult:view',
                'AdminUsersResult'
            );
            $r->add(
                $r::DUO,
                '/admin/users/{action:\w+}/{ids:\d+(?:-\d+)*}[/{token}]',
                'AdminUsersAction:view',
                'AdminUsersAction'
            );
            $r->add(
                $r::GET,
                '/admin/users/promote/{uid|i:[1-9]\d*}/{pid|i:[1-9]\d*}/{token}',
                'AdminUsersPromote:promote',
                'AdminUserPromote'
            );

            if ($user->isAdmin) {
                $r->add(
                    $r::DUO,
                    '/admin/users/new',
                    'AdminUsersNew:view',
                    'AdminUsersNew'
                );
                $r->add(
                    $r::PST,
                    '/admin/users/recalculate',
                    'AdminUsers:recalculate',
                    'AdminUsersRecalculate'
                );
            }

            if ($userRules->banUsers) {
                $r->add(
                    $r::DUO,
                    '/admin/bans',
                    'AdminBans:view',
                    'AdminBans'
                );
                $r->add(
                    $r::DUO,
                    '/admin/bans/new[/{ids:\d+(?:-\d+)*}[/{uid|i:[1-9]\d*}]]',
                    'AdminBans:add',
                    'AdminBansNew'
                );
                $r->add(
                    $r::DUO,
                    '/admin/bans/edit/{id|i:[1-9]\d*}',
                    'AdminBans:edit',
                    'AdminBansEdit'
                );
                $r->add(
                    $r::GET,
                    '/admin/bans/result/{data}[/{page|i:[1-9]\d*}]',
                    'AdminBans:result',
                    'AdminBansResult'
                );
                $r->add(
                    $r::GET,
                    '/admin/bans/delete/{id|i:[1-9]\d*}/{token}[/{uid|i:[1-9]\d*}]',
                    'AdminBans:delete',
                    'AdminBansDelete'
                );
            }

            if (
                $user->isAdmin
                || 0 === $config->i_report_method
                || 2 === $config->i_report_method
            ) {
                $r->add(
                    $r::GET,
                    '/admin/reports',
                    'AdminReports:view',
                    'AdminReports'
                );
                $r->add(
                    $r::GET,
                    '/admin/reports/zap/{id|i:[1-9]\d*}/{token}',
                    'AdminReports:zap',
                    'AdminReportsZap'
                );
            }

            $r->add(
                $r::PST,
                '/moderate',
                'Moderate:action',
                'Moderate'
            );
        }

        // только админ
        if ($user->isAdmin) {
            $r->add(
                $r::GET,
                '/admin/statistics/info',
                'AdminStatistics:info',
                'AdminInfo'
            );
            $r->add(
                $r::GET,
                '/admin/statistics/info/{time|i:\d+}',
                'AdminStatistics:infoCSS',
                'AdminInfoCSS'
            );
            $r->add(
                $r::DUO,
                '/admin/options',
                'AdminOptions:edit',
                'AdminOptions'
            );
            $r->add(
                $r::DUO,
                '/admin/options/providers',
                'AdminProviders:view',
                'AdminProviders'
            );
            $r->add(
                $r::DUO,
                '/admin/options/providers/{name}',
                'AdminProviders:edit',
                'AdminProvider'
            );
            $r->add(
                $r::DUO,
                '/admin/parser',
                'AdminParser:edit',
                'AdminParser'
            );
            $r->add(
                $r::DUO,
                '/admin/parser/bbcode',
                'AdminParserBBCode:view',
                'AdminBBCode'
            );
            $r->add(
                $r::DUO,
                '/admin/parser/bbcode/delete/{id|i:[1-9]\d*}',
                'AdminParserBBCode:delete',
                'AdminBBCodeDelete'
            );
            $r->add(
                $r::DUO,
                '/admin/parser/bbcode/edit/{id|i:[1-9]\d*}',
                'AdminParserBBCode:edit',
                'AdminBBCodeEdit'
            );
            $r->add(
                $r::DUO,
                '/admin/parser/bbcode/new',
                'AdminParserBBCode:edit',
                'AdminBBCodeNew'
            );
            $r->add(
                $r::GET,
                '/admin/parser/bbcode/default/{id|i:[1-9]\d*}/{token}',
                'AdminParserBBCode:default',
                'AdminBBCodeDefault'
            );
            $r->add(
                $r::DUO,
                '/admin/parser/smilies',
                'AdminParserSmilies:view',
                'AdminSmilies'
            );
            $r->add(
                $r::GET,
                '/admin/parser/smilies/delete/{name}/{token}',
                'AdminParserSmilies:delete',
                'AdminSmiliesDelete'
            );
            $r->add(
                $r::PST,
                '/admin/parser/smilies/upload',
                'AdminParserSmilies:upload',
                'AdminSmiliesUpload'
            );
            $r->add(
                $r::DUO,
                '/admin/categories',
                'AdminCategories:view',
                'AdminCategories'
            );
            $r->add(
                $r::DUO,
                '/admin/categories/delete/{id|i:[1-9]\d*}',
                'AdminCategories:delete',
                'AdminCategoriesDelete'
            );
            $r->add(
                $r::DUO,
                '/admin/forums',
                'AdminForums:view',
                'AdminForums'
            );
            $r->add(
                $r::DUO,
                '/admin/forums/new',
                'AdminForums:edit',
                'AdminForumsNew'
            );
            $r->add(
                $r::DUO,
                '/admin/forums/edit/{id|i:[1-9]\d*}',
                'AdminForums:edit',
                'AdminForumsEdit'
            );
            $r->add(
                $r::DUO,
                '/admin/forums/delete/{id|i:[1-9]\d*}',
                'AdminForums:delete',
                'AdminForumsDelete'
            );
            $r->add(
                $r::DUO,
                '/admin/forums/fields/{id|i:[1-9]\d*}[/{action:\w+}[/{field|i:[1-9]\d*}]]',
                'AdminForums:customFields',
                'AdminForumsFields'
            );
            $r->add(
                $r::GET,
                '/admin/groups',
                'AdminGroups:view',
                'AdminGroups'
            );
            $r->add(
                $r::PST,
                '/admin/groups/default',
                'AdminGroups:defaultSet',
                'AdminGroupsDefault'
            );
            $r->add(
                $r::PST,
                '/admin/groups/new[/{base|i:[1-9]\d*}]',
                'AdminGroups:edit',
                'AdminGroupsNew'
            );
            $r->add(
                $r::DUO,
                '/admin/groups/edit/{id|i:[1-9]\d*}',
                'AdminGroups:edit',
                'AdminGroupsEdit'
            );
            $r->add(
                $r::DUO,
                '/admin/groups/delete/{id|i:[1-9]\d*}',
                'AdminGroups:delete',
                'AdminGroupsDelete'
            );
            $r->add(
                $r::DUO,
                '/admin/censoring',
                'AdminCensoring:edit',
                'AdminCensoring'
            );
            $r->add(
                $r::DUO,
                '/admin/maintenance',
                'AdminMaintenance:view',
                'AdminMaintenance'
            );
            $r->add(
                $r::PST,
                '/admin/maintenance/rebuild',
                'AdminMaintenance:rebuild',
                'AdminMaintenanceRebuild'
            );
            $r->add(
                $r::GET,
                '/admin/maintenance/rebuild/{token}/{clear:[01]}/{limit|i:[1-9]\d*}/{start|i:[1-9]\d*}',
                'AdminMaintenance:rebuild',
                'AdminRebuildIndex'
            );
            $r->add(
                $r::PST,
                '/admin/maintenance/clear',
                'AdminMaintenance:clearCache',
                'AdminMaintenanceClear'
            );
            $r->add(
                $r::GET,
                '/admin/logs',
                'AdminLogs:info',
                'AdminLogs'
            );
            $r->add(
                $r::DUO,
                '/admin/logs/{action:\w+}/{hash}/{token}',
                'AdminLogs:action',
                'AdminLogsAction'
            );
            $r->add(
                $r::DUO,
                '/admin/uploads[/{page|i:[1-9]\d*}]',
                'AdminUploads:view',
                'AdminUploads'
            );
            $r->add(
                $r::DUO,
                '/admin/uploads/delete/{id|i:[1-9]\d*}',
                'AdminUploads:delete',
                'AdminUploadsDelete'
            );
            $r->add(
                $r::DUO,
                '/admin/antispam',
                'AdminAntispam:view',
                'AdminAntispam'
            );
            $r->add(
                $r::GET,
                '/admin/extensions',
                'AdminExtensions:info',
                'AdminExtensions'
            );
            $r->add(
                $r::PST,
                '/admin/extensions/action',
                'AdminExtensions:action',
                'AdminExtensionsAction'
            );
        }

        $uri = $_SERVER['REQUEST_URI'];

        if (false !== ($pos = \strpos($uri, '?'))) {
            $uri = \substr($uri, 0, $pos);
        }

        $uri    = \rawurldecode(\strtr($uri, '+', ' '));
        $method = $_SERVER['REQUEST_METHOD'];
        $route  = $r->route($method, $uri);
        $page   = null;

        switch ($route[0]) {
            case $r::OK:
                // ... 200 OK
                list($page, $action) = \explode(':', $route[1], 2);
                $page = $this->c->$page->$action($route[2], $method);

                break;
            case $r::NOT_FOUND:
                // ... 404 Not Found
                if (
                    1 !== $user->g_read_board
                    && $user->isGuest
                ) {
                    $page = $this->c->Redirect->page('Login');
                } else {
                    $page = $this->c->Message->message('Not Found', true, 404);
                }

                break;
            case $r::METHOD_NOT_ALLOWED:
                // ... 405 Method Not Allowed
                $page = $this->c->Message->message(
                    'Bad request',
                    true,
                    405,
                    [
                        ['Allow', \implode(',', $route[1])],
                    ]
                );

                break;
            case $r::NOT_IMPLEMENTED:
                // ... 501 Not implemented
                $page = $this->c->Message->message('Bad request', true, 501);

                break;
        }

        return $page;
    }
}
