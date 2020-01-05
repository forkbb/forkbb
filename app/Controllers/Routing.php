<?php

namespace ForkBB\Controllers;

use ForkBB\Core\Container;

class Routing
{
    const DUO = ['GET', 'POST'];
    const GET = 'GET';
    const PST = 'POST';
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    /**
     * Маршрутиризация
     *
     * @return Page
     */
    public function routing()
    {
        $user = $this->c->user;
        $config = $this->c->config;
        $r = $this->c->Router;

        // регистрация/вход/выход
        if ($user->isGuest) {
            // вход
            $r->add(self::DUO, '/login', 'Auth:login', 'Login');
            // забыли кодовую фразу
            $r->add(self::DUO, '/login/forget', 'Auth:forget', 'Forget');
            // смена кодовой фразы
            $r->add(self::DUO, '/login/{id:\d+}/{key}/{hash}', 'Auth:changePass', 'ChangePassword');

            // регистрация
            if ('1' == $config->o_regs_allow) {
                $r->add(self::GET, '/registration', 'Rules:confirmation', 'Register');
                $r->add(self::PST, '/registration/agree', 'Register:reg', 'RegisterForm');
                $r->add(self::GET, '/registration/activate/{id:\d+}/{key}/{hash}', 'Register:activate', 'RegActivate');
            }
        } else {
            // выход
            $r->add(self::GET, '/logout/{token}', 'Auth:logout', 'Logout');

            // обработка "кривых" перенаправлений с логина и регистрации
            $r->add(self::GET, '/login[/{tail:.*}]', 'Redirect:toIndex');
            $r->add(self::GET, '/registration[/{tail:.*}]', 'Redirect:toIndex');
        }
        // просмотр разрешен
        if ('1' == $user->g_read_board) {
            // главная
            $r->add(self::GET, '/',           'Index:view', 'Index');
            $r->add(self::GET, '/index.php',  'Redirect:toIndex');
            $r->add(self::GET, '/index.html', 'Redirect:toIndex');
            // правила
            if ('1' == $config->o_rules && (! $user->isGuest || '1' == $config->o_regs_allow)) {
                $r->add(self::GET, '/rules', 'Rules:view', 'Rules');
            }
            // поиск
            if ('1' == $user->g_search) {
                $r->add(self::GET, '/search[/simple/{keywords}[/{page:[1-9]\d*}]]', 'Search:view',   'Search');
                $r->add(self::PST, '/search',                                       'Search:view');

                $r->add(self::GET, '/search/advanced[/{keywords}/{author}/{forums}/{serch_in:\d}/{sort_by:\d}/{sort_dir:\d}/{show_as:\d}[/{page:[1-9]\d*}]]', 'Search:viewAdvanced', 'SearchAdvanced');
                $r->add(self::PST, '/search/advanced',                                                                                                        'Search:viewAdvanced');

                $r->add(self::GET, '/search[/user/{uid:[2-9]|[1-9]\d+}]/{action:(?!search)[a-z_]+}[/in_forum/{forum:[1-9]\d*}][/{page:[1-9]\d*}]', 'Search:action', 'SearchAction');
            }
            // юзеры
            if ($user->viewUsers) {
                // список пользователей
                $r->add(self::GET, '/userlist[/{group:all|[1-9]\d*}/{sort:username|registered|num_posts}/{dir:ASC|DESC}/{name}][/{page:[1-9]\d*}]', 'Userlist:view', 'Userlist');
                $r->add(self::PST, '/userlist', 'Userlist:view');
                // юзеры
                $r->add(self::GET, '/user/{id:[2-9]|[1-9]\d+}/{name}',          'ProfileView:view',   'User');
                $r->add(self::DUO, '/user/{id:[2-9]|[1-9]\d+}/edit/profile',    'ProfileEdit:edit',   'EditUserProfile');
                $r->add(self::DUO, '/user/{id:[2-9]|[1-9]\d+}/edit/config',     'ProfileConfig:config', 'EditUserBoardConfig');
                $r->add(self::DUO, '/user/{id:[2-9]|[1-9]\d+}/edit/email',      'ProfileEmail:email', 'EditUserEmail');
                $r->add(self::DUO, '/user/{id:[2-9]|[1-9]\d+}/edit/passphrase', 'ProfilePass:pass',   'EditUserPass');
                $r->add(self::DUO, '/user/{id:[2-9]|[1-9]\d+}/edit/moderation', 'ProfileMod:moderation', 'EditUserModeration');
            } elseif (! $user->isGuest) {
                // только свой профиль
                $r->add(self::GET, '/user/{id:' . $user->id . '}/{name}',          'ProfileView:view',   'User');
                $r->add(self::DUO, '/user/{id:' . $user->id . '}/edit/profile',    'ProfileEdit:edit',   'EditUserProfile');
                $r->add(self::DUO, '/user/{id:' . $user->id . '}/edit/config',     'ProfileConfig:config', 'EditUserBoardConfig');
                $r->add(self::DUO, '/user/{id:' . $user->id . '}/edit/email',      'ProfileEmail:email',  'EditUserEmail');
                $r->add(self::DUO, '/user/{id:' . $user->id . '}/edit/passphrase', 'ProfilePass:pass',   'EditUserPass');
            }
            // смена своего email
            if (! $user->isGuest) {
                $r->add(self::GET, '/user/{id:' . $user->id . '}/{email}/{key}/{hash}', 'ProfileEmail:setEmail',  'SetNewEmail');
            }
            // пометка разделов прочитанными
            if (! $user->isGuest) {
                $r->add(self::GET, '/forum/{id:\d+}/markread/{token}', 'Misc:markread', 'MarkRead');
            }

            // разделы
            $r->add(self::GET, '/forum/{id:[1-9]\d*}/{name}[/{page:[1-9]\d*}]', 'Forum:view',    'Forum'   );
            $r->add(self::DUO, '/forum/{id:[1-9]\d*}/new/topic',                'Post:newTopic', 'NewTopic');
            // темы
            $r->add(self::GET, '/topic/{id:[1-9]\d*}/{name}[/{page:[1-9]\d*}]',     'Topic:viewTopic',  'Topic'          );
            $r->add(self::GET, '/topic/{id:[1-9]\d*}/view/new',                     'Topic:viewNew',    'TopicViewNew'   );
            $r->add(self::GET, '/topic/{id:[1-9]\d*}/view/unread',                  'Topic:viewUnread', 'TopicViewUnread');
            $r->add(self::GET, '/topic/{id:[1-9]\d*}/view/last',                    'Topic:viewLast',   'TopicViewLast'  );
            $r->add(self::GET, '/topic/{id:[1-9]\d*}/new/reply[/{quote:[1-9]\d*}]', 'Post:newReply',    'NewReply'       );
            $r->add(self::PST, '/topic/{id:[1-9]\d*}/new/reply',                    'Post:newReply'                      );
            // сообщения
            $r->add(self::GET, '/post/{id:[1-9]\d*}#p{id}',  'Topic:viewPost', 'ViewPost'  );
            $r->add(self::DUO, '/post/{id:[1-9]\d*}/edit',   'Edit:edit',      'EditPost'  );
            $r->add(self::DUO, '/post/{id:[1-9]\d*}/delete', 'Delete:delete',  'DeletePost');
            $r->add(self::GET, '/post/{id:[1-9]\d*}/report', 'Report:report',  'ReportPost');

        }
        // админ и модератор
        if ($user->isAdmMod) {
            $r->add(self::GET, '/admin/', 'AdminIndex:index', 'Admin');
            $r->add(self::GET, '/admin/statistics', 'AdminStatistics:statistics', 'AdminStatistics');

            if ($user->canViewIP) {
                $r->add(self::GET, '/admin/get/host/{ip:[0-9a-fA-F:.]+}', 'AdminHost:view', 'AdminHost');
                $r->add(self::GET, '/admin/users/user/{id:[2-9]|[1-9]\d+}[/{page:[1-9]\d*}]', 'AdminUsersStat:view', 'AdminUserStat');
            }

            $r->add(self::DUO, '/admin/users', 'AdminUsers:view', 'AdminUsers');
            $r->add(self::DUO, '/admin/users/result/{data}[/{page:[1-9]\d*}]', 'AdminUsersResult:view', 'AdminUsersResult');
            $r->add(self::DUO, '/admin/users/{action:\w+}/{ids:\d+(?:-\d+)*}[/{token}]', 'AdminUsersAction:view', 'AdminUsersAction');

            $r->add(self::GET, '/admin/users/promote/{uid:[2-9]|[1-9]\d+}/{pid:[1-9]\d*}/{token}', 'AdminUsersPromote:promote', 'AdminUserPromote');

            if ($this->c->userRules->banUsers) {
                $r->add(self::DUO, '/admin/bans',                                                     'AdminBans:view',   'AdminBans');
                $r->add(self::DUO, '/admin/bans/new[/{ids:\d+(?:-\d+)*}[/{uid:[2-9]|[1-9]\d+}]]',     'AdminBans:add',    'AdminBansNew');
                $r->add(self::DUO, '/admin/bans/edit/{id:[1-9]\d*}',                                  'AdminBans:edit',   'AdminBansEdit');
                $r->add(self::GET, '/admin/bans/result/{data}[/{page:[1-9]\d*}]',                     'AdminBans:result', 'AdminBansResult');
                $r->add(self::GET, '/admin/bans/delete/{id:[1-9]\d*}/{token}[/{uid:[2-9]|[1-9]\d+}]', 'AdminBans:delete', 'AdminBansDelete');
            }
        }
        // только админ
        if ($user->isAdmin) {
            $r->add(self::GET, '/admin/statistics/info',                   'AdminStatistics:info',     'AdminInfo'         );
            $r->add(self::DUO, '/admin/options',                           'AdminOptions:edit',        'AdminOptions'      );
            $r->add(self::DUO, '/admin/permissions',                       'AdminPermissions:edit',    'AdminPermissions'  );
            $r->add(self::DUO, '/admin/categories',                        'AdminCategories:view',     'AdminCategories'   );
            $r->add(self::DUO, '/admin/categories/{id:[1-9]\d*}/delete',   'AdminCategories:delete',   'AdminCategoriesDelete');
            $r->add(self::DUO, '/admin/forums',                            'AdminForums:view',         'AdminForums'       );
            $r->add(self::DUO, '/admin/forums/new',                        'AdminForums:edit',         'AdminForumsNew'    );
            $r->add(self::DUO, '/admin/forums/{id:[1-9]\d*}/edit',         'AdminForums:edit',         'AdminForumsEdit'   );
            $r->add(self::DUO, '/admin/forums/{id:[1-9]\d*}/delete',       'AdminForums:delete',       'AdminForumsDelete' );
            $r->add(self::GET, '/admin/groups',                            'AdminGroups:view',         'AdminGroups'       );
            $r->add(self::PST, '/admin/groups/default',                    'AdminGroups:defaultSet',   'AdminGroupsDefault');
            $r->add(self::PST, '/admin/groups/new[/{base:[1-9]\d*}]',      'AdminGroups:edit',         'AdminGroupsNew'    );
            $r->add(self::DUO, '/admin/groups/{id:[1-9]\d*}/edit',         'AdminGroups:edit',         'AdminGroupsEdit'   );
            $r->add(self::DUO, '/admin/groups/{id:[1-9]\d*}/delete',       'AdminGroups:delete',       'AdminGroupsDelete' );
            $r->add(self::DUO, '/admin/censoring',                         'AdminCensoring:edit',      'AdminCensoring'    );
            $r->add(self::DUO, '/admin/maintenance',                       'AdminMaintenance:view',    'AdminMaintenance'  );
            $r->add(self::PST, '/admin/maintenance/rebuild',               'AdminMaintenance:rebuild', 'AdminMaintenanceRebuild');
            $r->add(self::GET, '/admin/maintenance/rebuild/{token}/{clear:[01]}/{limit:[1-9]\d*}/{start:[1-9]\d*}', 'AdminMaintenance:rebuild', 'AdminRebuildIndex' );

        }

        $uri = $_SERVER['REQUEST_URI'];
        if (($pos = \strpos($uri, '?')) !== false) {
            $uri = \substr($uri, 0, $pos);
        }
        $uri    = \rawurldecode($uri);
        $method = $_SERVER['REQUEST_METHOD'];

        $route = $r->route($method, $uri);
        $page = null;
        switch ($route[0]) {
            case $r::OK:
                // ... 200 OK
                list($page, $action) = \explode(':', $route[1], 2);
                $page = $this->c->$page->$action($route[2], $method);
                break;
            case $r::NOT_FOUND:
                // ... 404 Not Found
                if ($user->g_read_board != '1' && $user->isGuest) {
                    $page = $this->c->Redirect->page('Login');
                } else {
                    $page = $this->c->Message->message('Bad request');
                }
                break;
            case $r::METHOD_NOT_ALLOWED:
                // ... 405 Method Not Allowed
                $page = $this->c->Message->message('Bad request', true, 405, ['Allow: ' . \implode(',', $route[1])]);
                break;
            case $r::NOT_IMPLEMENTED:
                // ... 501 Not implemented
                $page = $this->c->Message->message('Bad request', true, 501);
                break;
        }
        return $page;
    }
}
