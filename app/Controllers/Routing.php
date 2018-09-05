<?php

namespace ForkBB\Controllers;

use ForkBB\Core\Container;

class Routing
{
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
            $r->add(['GET', 'POST'], '/login', 'Auth:login', 'Login');
            // забыли кодовую фразу
            $r->add(['GET', 'POST'],  '/login/forget', 'Auth:forget', 'Forget');
            // смена кодовой фразы
            $r->add(['GET', 'POST'],  '/login/{email}/{key}/{hash}', 'Auth:changePass', 'ChangePassword');

            // регистрация
            if ('1' == $config->o_regs_allow) {
                $r->add('GET',  '/registration', 'Rules:confirmation', 'Register');
                $r->add('POST', '/registration/agree', 'Register:reg', 'RegisterForm');
                $r->add('GET',  '/registration/activate/{id:\d+}/{key}/{hash}', 'Register:activate', 'RegActivate');
            }
        } else {
            // выход
            $r->add('GET', '/logout/{token}', 'Auth:logout', 'Logout');

            // обработка "кривых" перенаправлений с логина и регистрации
            $r->add('GET', '/login[/{tail:.*}]', 'Redirect:toIndex');
            $r->add('GET', '/registration[/{tail:.*}]', 'Redirect:toIndex');
        }
        // просмотр разрешен
        if ('1' == $user->g_read_board) {
            // главная
            $r->add('GET', '/', 'Index:view', 'Index');
            // правила
            if ('1' == $config->o_rules && (! $user->isGuest || '1' == $config->o_regs_allow)) {
                $r->add('GET', '/rules', 'Rules:view', 'Rules');
            }
            // поиск
            if ('1' == $user->g_search) {
                $r->add('GET',  '/search[/simple/{keywords}[/{page:[1-9]\d*}]]', 'Search:view',   'Search');
                $r->add('POST', '/search',                                       'Search:view');

                $r->add('GET',  '/search/advanced[/{keywords}/{author}/{forums}/{serch_in:\d}/{sort_by:\d}/{sort_dir:\d}/{show_as:\d}[/{page:[1-9]\d*}]]', 'Search:viewAdvanced', 'SearchAdvanced');
                $r->add('POST', '/search/advanced',                                                                                                        'Search:viewAdvanced');

                $r->add('GET', '/search[/user/{uid:[2-9]|[1-9]\d+}]/{action:(?!search)\w+}[/{page:[1-9]\d*}]', 'Search:action', 'SearchAction');
            }
            // юзеры
            if ($user->viewUsers) {
                // список пользователей
                $r->add('GET',  '/userlist[/{sort:username|registered|num_posts}/{dir:ASC|DESC}/{group:\-1|[1-9]\d*}/{name}][/{page:[1-9]\d*}]', 'Userlist:view', 'Userlist');
                $r->add('POST', '/userlist', 'Userlist:view');
                // юзеры
                $r->add('GET',           '/user/{id:[2-9]|[1-9]\d+}/{name}',          'ProfileView:view',   'User');
                $r->add(['GET', 'POST'], '/user/{id:[2-9]|[1-9]\d+}/edit/profile',    'ProfileEdit:edit',   'EditUserProfile');
                $r->add(['GET', 'POST'], '/user/{id:[2-9]|[1-9]\d+}/edit/config',     'ProfileConfig:config', 'EditUserBoardConfig');
                $r->add(['GET', 'POST'], '/user/{id:[2-9]|[1-9]\d+}/edit/email',      'ProfileEmail:email',  'EditUserEmail');
                $r->add(['GET', 'POST'], '/user/{id:[2-9]|[1-9]\d+}/edit/passphrase', 'ProfilePass:pass',   'EditUserPass');
            } elseif (! $user->isGuest) {
                // только свой профиль
                $r->add('GET',           '/user/{id:' . $user->id . '}/{name}',          'ProfileView:view',   'User');
                $r->add(['GET', 'POST'], '/user/{id:' . $user->id . '}/edit/profile',    'ProfileEdit:edit',   'EditUserProfile');
                $r->add(['GET', 'POST'], '/user/{id:' . $user->id . '}/edit/config',     'ProfileConfig:config', 'EditUserBoardConfig');
                $r->add(['GET', 'POST'], '/user/{id:' . $user->id . '}/edit/email',      'ProfileEmail:email',  'EditUserEmail');
                $r->add(['GET', 'POST'], '/user/{id:' . $user->id . '}/edit/passphrase', 'ProfilePass:pass',   'EditUserPass');
            }
            // смена своего email
            if (! $user->isGuest) {
                $r->add('GET', '/user/{id:' . $user->id . '}/{email}/{key}/{hash}', 'ProfileEmail:setEmail',  'SetNewEmail');
            }
            // пометка разделов прочитанными
            if (! $user->isGuest) {
                $r->add('GET', '/forum/{id:\d+}/markread/{token}', 'Misc:markread', 'MarkRead');
            }

            // разделы
            $r->add('GET',           '/forum/{id:[1-9]\d*}/{name}[/{page:[1-9]\d*}]', 'Forum:view',    'Forum'   );
            $r->add(['GET', 'POST'], '/forum/{id:[1-9]\d*}/new/topic',                'Post:newTopic', 'NewTopic');
            // темы
            $r->add('GET',  '/topic/{id:[1-9]\d*}/{name}[/{page:[1-9]\d*}]',     'Topic:viewTopic',  'Topic'          );
            $r->add('GET',  '/topic/{id:[1-9]\d*}/view/new',                     'Topic:viewNew',    'TopicViewNew'   );
            $r->add('GET',  '/topic/{id:[1-9]\d*}/view/unread',                  'Topic:viewUnread', 'TopicViewUnread');
            $r->add('GET',  '/topic/{id:[1-9]\d*}/view/last',                    'Topic:viewLast',   'TopicViewLast'  );
            $r->add('GET',  '/topic/{id:[1-9]\d*}/new/reply[/{quote:[1-9]\d*}]', 'Post:newReply',    'NewReply'       );
            $r->add('POST', '/topic/{id:[1-9]\d*}/new/reply',                    'Post:newReply'                      );
            // сообщения
            $r->add('GET',           '/post/{id:[1-9]\d*}#p{id}',  'Topic:viewPost', 'ViewPost'  );
            $r->add(['GET', 'POST'], '/post/{id:[1-9]\d*}/edit',   'Edit:edit',      'EditPost'  );
            $r->add(['GET', 'POST'], '/post/{id:[1-9]\d*}/delete', 'Delete:delete',  'DeletePost');
            $r->add('GET',           '/post/{id:[1-9]\d*}/report', 'Report:report',  'ReportPost');

        }
        // админ и модератор
        if ($user->isAdmMod) {
            $r->add('GET', '/admin/', 'AdminIndex:index', 'Admin');
            $r->add('GET', '/admin/statistics', 'AdminStatistics:statistics', 'AdminStatistics');

            $r->add(['GET', 'POST'], '/admin/users', 'AdminUsers:view', 'AdminUsers');
            $r->add(['GET', 'POST'], '/admin/users/result/{data}[/{page:[1-9]\d*}]', 'AdminUsersResult:view', 'AdminUsersResult');

            if ($user->canViewIP) {
                $r->add('GET',           '/admin/get/host/{ip:[0-9a-fA-F:.]+}',      'AdminHost:view',           'AdminHost');
                $r->add('GET',           '/admin/users/user/{id:[2-9]|[1-9]\d+}[/{page:[1-9]\d*}]',      'AdminUsersStat:view',            'AdminUserStat');
            }
        }
        // только админ
        if ($user->isAdmin) {
            $r->add('GET',           '/admin/statistics/info',                   'AdminStatistics:info',     'AdminInfo'         );
            $r->add(['GET', 'POST'], '/admin/options',                           'AdminOptions:edit',        'AdminOptions'      );
            $r->add(['GET', 'POST'], '/admin/permissions',                       'AdminPermissions:edit',    'AdminPermissions'  );
            $r->add(['GET', 'POST'], '/admin/categories',                        'AdminCategories:view',     'AdminCategories'   );
            $r->add(['GET', 'POST'], '/admin/categories/{id:[1-9]\d*}/delete',   'AdminCategories:delete',   'AdminCategoriesDelete');
            $r->add(['GET', 'POST'], '/admin/forums',                            'AdminForums:view',         'AdminForums'       );
            $r->add(['GET', 'POST'], '/admin/forums/new',                        'AdminForums:edit',         'AdminForumsNew'    );
            $r->add(['GET', 'POST'], '/admin/forums/{id:[1-9]\d*}/edit',         'AdminForums:edit',         'AdminForumsEdit'   );
            $r->add(['GET', 'POST'], '/admin/forums/{id:[1-9]\d*}/delete',       'AdminForums:delete',       'AdminForumsDelete' );
            $r->add('GET',           '/admin/groups',                            'AdminGroups:view',         'AdminGroups'       );
            $r->add('POST',          '/admin/groups/default',                    'AdminGroups:defaultSet',   'AdminGroupsDefault');
            $r->add('POST',          '/admin/groups/new[/{base:[1-9]\d*}]',      'AdminGroups:edit',         'AdminGroupsNew'    );
            $r->add(['GET', 'POST'], '/admin/groups/{id:[1-9]\d*}/edit',         'AdminGroups:edit',         'AdminGroupsEdit'   );
            $r->add(['GET', 'POST'], '/admin/groups/{id:[1-9]\d*}/delete',       'AdminGroups:delete',       'AdminGroupsDelete' );
            $r->add(['GET', 'POST'], '/admin/censoring',                         'AdminCensoring:edit',      'AdminCensoring'    );
            $r->add(['GET', 'POST'], '/admin/maintenance',                       'AdminMaintenance:view',    'AdminMaintenance'  );
            $r->add('POST',          '/admin/maintenance/rebuild',               'AdminMaintenance:rebuild', 'AdminMaintenanceRebuild');
            $r->add('GET',           '/admin/maintenance/rebuild/{token}/{clear:[01]}/{limit:[1-9]\d*}/{start:[1-9]\d*}', 'AdminMaintenance:rebuild', 'AdminRebuildIndex' );

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
