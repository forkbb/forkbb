<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

# development
#\error_reporting(\E_ALL);
#\ini_set('display_errors', '1');
#\ini_set('log_errors', '1');

return [
    'BASE_URL'    => '_BASE_URL_',
    'EOL'         => \PHP_EOL, // Define line breaks in mail headers; possible values can be \PHP_EOL, "\r\n", "\n" or "\r"
    'DB_DSN'      => '_DB_DSN_',
    'DB_USERNAME' => '_DB_USERNAME_',
    'DB_PASSWORD' => '_DB_PASSWORD_',
    'DB_OPTIONS'  => [],
    'DB_PREFIX'   => '_DB_PREFIX_',
    'COOKIE' => [
        'prefix'   => '_COOKIE_PREFIX_',
        'domain'   => '_COOKIE_DOMAIN_',
        'path'     => '_COOKIE_PATH_',
        'secure'   => _COOKIE_SECURE_,
        'samesite' => 'Lax', // Strict, Lax or None
        'time'     => 1209600,
        'key1'     => '_COOKIE_KEY1_',
        'key2'     => '_COOKIE_KEY2_',
    ],
    'HMAC' => [
        'algo' => 'sha1',
        'salt' => '_SALT_FOR_HMAC_',
    ],
    'JQUERY_LINK'      => '//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js',
    'DEBUG'            => 0,
    'MAINTENANCE_OFF'  => false,
    'GROUP_ADMIN'      => 1,
    'GROUP_MOD'        => 2,
    'GROUP_GUEST'      => 3,
    'GROUP_MEMBER'     => 4,
    'BBCODE_INFO'      => [
        'smTpl'    => '<img src="{url}" alt="{alt}">',
        'smTplTag' => 'img',
        'smTplBl'  => ['url'],
    ],
    'MAX_POST_SIZE'    => 65536,
    'MAX_IMG_SIZE'     => '2M',
    'MAX_FILE_SIZE'    => '2M',
    'MAX_EMAIL_LENGTH' => 80,
    'FLOOD_INTERVAL'   => 3600,
    'USERNAME_PATTERN' => '%^(?=.{2,25}$)\p{L}[\p{L}\p{N}\x20\._-]+$%uD',
    'HTTP_HEADERS'     => [
        'common' => [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'DENY',
            'X-XSS-Protection'        => '1; mode=block',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => 'default-src \'self\';img-src *;object-src \'none\';frame-ancestors \'none\';base-uri \'self\';form-action \'self\'',
            'Feature-Policy'          => 'accelerometer \'none\';ambient-light-sensor \'none\';autoplay \'none\';battery \'none\';camera \'none\';document-domain \'self\';fullscreen \'self\';geolocation \'none\';gyroscope \'none\';magnetometer \'none\';microphone \'none\';midi \'none\';payment \'none\';picture-in-picture \'none\';sync-xhr \'self\';usb \'none\'',
        ],
        'secure' => [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'DENY',
            'X-XSS-Protection'        => '1; mode=block',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => 'default-src \'self\';object-src \'none\';frame-ancestors \'none\';base-uri \'self\';form-action \'self\'',
            'Feature-Policy'          => 'accelerometer \'none\';ambient-light-sensor \'none\';autoplay \'none\';battery \'none\';camera \'none\';document-domain \'self\';fullscreen \'self\';geolocation \'none\';gyroscope \'none\';magnetometer \'none\';microphone \'none\';midi \'none\';payment \'none\';picture-in-picture \'none\';sync-xhr \'self\';usb \'none\'',
        ],
    ],

    'shared' => [
        'DB' => [
            'class' => \ForkBB\Core\DB::class,
            'dsn'      => '%DB_DSN%',
            'username' => '%DB_USERNAME%',
            'password' => '%DB_PASSWORD%',
            'options'  => '%DB_OPTIONS%',
            'prefix'   => '%DB_PREFIX%',
        ],
        'Secury' => [
            'class' => \ForkBB\Core\Secury::class,
            'hmac'  => '%HMAC%',
        ],
        'Cache' => [
            'class'     => \ForkBB\Core\Cache\FileCache::class,
            'cache_dir' => '%DIR_CACHE%',
        ],
        'Validator' => \ForkBB\Core\Validator::class,
        'View' => [
            'class'     => \ForkBB\Core\View::class,
            'cache_dir' => '%DIR_CACHE%',
            'views_dir' => '%DIR_VIEWS%',
        ],
        'Router' => [
            'class'    => \ForkBB\Core\Router::class,
            'base_url' => '%BASE_URL%',
            'csrf'     => '@Csrf'
        ],
        'Lang' => \ForkBB\Core\Lang::class,
        'Mail' => [
            'class' => \ForkBB\Core\Mail::class,
            'host'  => '%config.o_smtp_host%',
            'user'  => '%config.o_smtp_user%',
            'pass'  => '%config.o_smtp_pass%',
            'ssl'   => '%config.o_smtp_ssl%',
            'eol'   => '%EOL%',
        ],
        'Func'      => \ForkBB\Core\Func::class,
        'Test'      => \ForkBB\Core\Test::class,
        'NormEmail' => \MioVisman\NormEmail\NormEmail::class,
        'Log'       => [
            'class'  => \ForkBB\Core\Log::class,
            'config' => [
                'path'       => '%DIR_LOG%/{Y-m-d}.log',
                'lineFormat' => "\\%datetime\\% [\\%level_name\\%] \\%message\\%\t\\%context\\%\n",
                'timeFormat' => 'Y-m-d H:i:s',
            ],
        ],
        'LogViewer' => [
            'class'  => \ForkBB\Core\LogViewer::class,
            'config' => [
                'dir'        => '%DIR_LOG%',
                'pattern'    => '*.log',
                'lineFormat' => "\\%datetime\\% [\\%level_name\\%] \\%message\\%\t\\%context\\%\n",
            ],
            'cache' => '%Cache%',
        ],
        'HTMLCleaner' => [
            'calss'  => \ForkBB\Core\HTMLCleaner::class,
            'config' => '%DIR_APP%/config/jevix.default.php',
        ],

        'config'        => '@ConfigModel:init',
        'bans'          => '@BanListModel:init',
        'censorship'    => '@CensorshipModel:init',
        'stats'         => '@StatsModel:init',
        'admins'        => '@AdminListModel:init',
        'smilies'       => '@SmileyListModel:init',
        'dbMap'         => '@DBMapModel:init',
        'stopwords'     => '@StopwordsModel:init',
        'forums'        => '@ForumManager:init',
        'topics'        => \ForkBB\Models\Topic\Manager::class,
        'posts'         => \ForkBB\Models\Post\Manager::class,
        'polls'         => \ForkBB\Models\Poll\Manager::class,
        'reports'       => \ForkBB\Models\Report\Manager::class,
        'user'          => '@users:current',
        'userRules'     => '@UsersRules:init',
        'users'         => \ForkBB\Models\User\Manager::class,
        'groups'        => '@GroupManager:init',
        'categories'    => '@CategoriesManager:init',
        'search'        => \ForkBB\Models\Search\Model::class,
        'subscriptions' => \ForkBB\Models\Subscription\Model::class,
        'bbcode'        => '@BBCodeListModel:init',

        'Csrf' => [
            'class'  => \ForkBB\Core\Csrf::class,
            'Secury' => '@Secury',
            'key'    => '%user.password%%user.ip%%user.id%%BASE_URL%',
        ],
        'Online' => \ForkBB\Models\Online\Model::class,
        'Cookie' => [
            'class'   => \ForkBB\Models\Cookie\Model::class,
            'options' => '%COOKIE%',
        ],

        'Parser' => [
            'class' => \ForkBB\Core\Parser::class,
            'flag'  => \ENT_HTML5,
        ],
        'Files' => [
            'class' => \ForkBB\Core\Files::class,
            'file'  => '%MAX_FILE_SIZE%',
            'img'   => '%MAX_IMG_SIZE%',
        ],

        'VLnoURL'    => \ForkBB\Models\Validators\NoURL::class,
        'VLusername' => \ForkBB\Models\Validators\Username::class,
        'VLemail'    => \ForkBB\Models\Validators\Email::class,
        'VLhtml'     => \ForkBB\Models\Validators\Html::class,

        'ProfileRules' => \ForkBB\Models\Rules\Profile::class,
        'UsersRules'   => \ForkBB\Models\Rules\Users::class,

        'PollManagerLoad'     => \ForkBB\Models\Poll\Load::class,
        'PollManagerSave'     => \ForkBB\Models\Poll\Save::class,
        'PollManagerDelete'   => \ForkBB\Models\Poll\Delete::class,
        'PollManagerRevision' => \ForkBB\Models\Poll\Revision::class,

        'SubscriptionModelSend' => \ForkBB\Models\Subscription\Send::class,

        'BanListModelIsBanned' => \ForkBB\Models\BanList\IsBanned::class,

        'SmileyListModelLoad'   => \ForkBB\Models\SmileyList\Load::class,
        'SmileyListModelUpdate' => \ForkBB\Models\SmileyList\Update::class,
        'SmileyListModelInsert' => \ForkBB\Models\SmileyList\Insert::class,
        'SmileyListModelDelete' => \ForkBB\Models\SmileyList\Delete::class,

        'BBCodeListModel'         => [
            'class' => \ForkBB\Models\BBCodeList\Model::class,
            'file'  => 'defaultBBCode.php',
        ],
        'BBCodeListModelGenerate' => \ForkBB\Models\BBCodeList\Generate::class,
        'BBCodeListModelLoad'     => \ForkBB\Models\BBCodeList\Load::class,
        'BBCodeListModelUpdate'   => \ForkBB\Models\BBCodeList\Update::class,
        'BBCodeListModelInsert'   => \ForkBB\Models\BBCodeList\Insert::class,
        'BBCodeListModelDelete'   => \ForkBB\Models\BBCodeList\Delete::class,
    ],
    'multiple'  => [
        'CtrlPrimary' => \ForkBB\Controllers\Primary::class,
        'Primary'     => '@CtrlPrimary:check',

        'CtrlRouting' => \ForkBB\Controllers\Routing::class,
        'Routing'     => '@CtrlRouting:routing',

        'Message'         => \ForkBB\Models\Pages\Message::class,
        'Index'           => \ForkBB\Models\Pages\Index::class,
        'Forum'           => \ForkBB\Models\Pages\Forum::class,
        'Topic'           => \ForkBB\Models\Pages\Topic::class,
        'Post'            => \ForkBB\Models\Pages\Post::class,
        'Edit'            => \ForkBB\Models\Pages\Edit::class,
        'Delete'          => \ForkBB\Models\Pages\Delete::class,
        'Rules'           => \ForkBB\Models\Pages\Rules::class,
        'Auth'            => \ForkBB\Models\Pages\Auth::class,
        'Userlist'        => \ForkBB\Models\Pages\Userlist::class,
        'Search'          => \ForkBB\Models\Pages\Search::class,
        'Register'        => \ForkBB\Models\Pages\Register::class,
        'Redirect'        => \ForkBB\Models\Pages\Redirect::class,
        'Maintenance'     => \ForkBB\Models\Pages\Maintenance::class,
        'Ban'             => \ForkBB\Models\Pages\Ban::class,
        'Debug'           => \ForkBB\Models\Pages\Debug::class,
        'Misc'            => \ForkBB\Models\Pages\Misc::class,
        'Moderate'        => \ForkBB\Models\Pages\Moderate::class,
        'Report'          => \ForkBB\Models\Pages\Report::class,
        'Email'           => \ForkBB\Models\Pages\Email::class,
        'Feed'            => \ForkBB\Models\Pages\Feed::class,
        'Poll'            => \ForkBB\Models\Pages\Poll::class,
        'ProfileView'     => \ForkBB\Models\Pages\Profile\View::class,
        'ProfileEdit'     => \ForkBB\Models\Pages\Profile\Edit::class,
        'ProfileConfig'   => \ForkBB\Models\Pages\Profile\Config::class,
        'ProfilePass'     => \ForkBB\Models\Pages\Profile\Pass::class,
        'ProfileEmail'    => \ForkBB\Models\Pages\Profile\Email::class,
        'ProfileMod'      => \ForkBB\Models\Pages\Profile\Mod::class,
        'AdminIndex'      => \ForkBB\Models\Pages\Admin\Index::class,
        'AdminStatistics' => \ForkBB\Models\Pages\Admin\Statistics::class,
        'AdminOptions'    => \ForkBB\Models\Pages\Admin\Options::class,
        'AdminCategories' => \ForkBB\Models\Pages\Admin\Categories::class,
        'AdminForums'     => \ForkBB\Models\Pages\Admin\Forums::class,
        'AdminGroups'     => \ForkBB\Models\Pages\Admin\Groups::class,
        'AdminCensoring'  => \ForkBB\Models\Pages\Admin\Censoring::class,
        'AdminMaintenance' => \ForkBB\Models\Pages\Admin\Maintenance::class,
        'AdminUsers'      => \ForkBB\Models\Pages\Admin\Users\View::class,
        'AdminUsersResult' => \ForkBB\Models\Pages\Admin\Users\Result::class,
        'AdminUsersStat'  => \ForkBB\Models\Pages\Admin\Users\Stat::class,
        'AdminUsersAction' => \ForkBB\Models\Pages\Admin\Users\Action::class,
        'AdminUsersPromote' => \ForkBB\Models\Pages\Admin\Users\Promote::class,
        'AdminUsersNew'   => \ForkBB\Models\Pages\Admin\Users\NewUser::class,
        'AdminHost'       => \ForkBB\Models\Pages\Admin\Host::class,
        'AdminBans'       => \ForkBB\Models\Pages\Admin\Bans::class,
        'AdminReports'    => \ForkBB\Models\Pages\Admin\Reports::class,
        'AdminParser'     => \ForkBB\Models\Pages\Admin\Parser\Edit::class,
        'AdminParserSmilies' => \ForkBB\Models\Pages\Admin\Parser\Smilies::class,
        'AdminParserBBCode' => \ForkBB\Models\Pages\Admin\Parser\BBCode::class,
        'AdminLogs'       => \ForkBB\Models\Pages\Admin\Logs::class,

        'ConfigModel'     => \ForkBB\Models\Config\Model::class,
        'ConfigModelLoad' => \ForkBB\Models\Config\Load::class,
        'ConfigModelSave' => \ForkBB\Models\Config\Save::class,

        'OnlineModelInfo' => \ForkBB\Models\Online\Info::class,
        'OnlineModelUpdateUsername' => \ForkBB\Models\Online\UpdateUsername::class,

        'BanListModel'         => \ForkBB\Models\BanList\Model::class,
        'BanListModelLoad'     => \ForkBB\Models\BanList\Load::class,
        'BanListModelCheck'    => \ForkBB\Models\BanList\Check::class,
        'BanListModelDelete'   => \ForkBB\Models\BanList\Delete::class,
        'BanListModelFilter'   => \ForkBB\Models\BanList\Filter::class,
        'BanListModelGetList'  => \ForkBB\Models\BanList\GetList::class,
        'BanListModelInsert'   => \ForkBB\Models\BanList\Insert::class,
        'BanListModelUpdate'   => \ForkBB\Models\BanList\Update::class,

        'CensorshipModel'        => \ForkBB\Models\Censorship\Model::class,
        'CensorshipModelRefresh' => \ForkBB\Models\Censorship\Refresh::class,
        'CensorshipModelLoad'    => \ForkBB\Models\Censorship\Load::class,
        'CensorshipModelSave'    => \ForkBB\Models\Censorship\Save::class,

        'StatsModel' => \ForkBB\Models\Stats\Model::class,

        'AdminListModel' => \ForkBB\Models\AdminList\Model::class,

        'SmileyListModel'     => \ForkBB\Models\SmileyList\Model::class,

        'DBMapModel'          => \ForkBB\Models\DBMap\Model::class,

        'StopwordsModel'      => \ForkBB\Models\Stopwords\Model::class,

        'UserModel'                   => \ForkBB\Models\User\Model::class,
        'UserManagerLoad'             => \ForkBB\Models\User\Load::class,
        'UserManagerSave'             => \ForkBB\Models\User\Save::class,
        'UserManagerCurrent'          => \ForkBB\Models\User\Current::class,
        'UserManagerUpdateLastVisit'  => \ForkBB\Models\User\UpdateLastVisit::class,
        'UserManagerUpdateCountPosts' => \ForkBB\Models\User\UpdateCountPosts::class,
        'UserManagerUpdateCountTopics' => \ForkBB\Models\User\UpdateCountTopics::class,
        'UserManagerUpdateLoginIpCache' => \ForkBB\Models\User\UpdateLoginIpCache::class,
        'UserManagerIsUniqueName'     => \ForkBB\Models\User\IsUniqueName::class,
        'UserManagerUsersNumber'      => \ForkBB\Models\User\UsersNumber::class,
        'UserManagerPromote'          => \ForkBB\Models\User\Promote::class,
        'UserManagerFilter'           => \ForkBB\Models\User\Filter::class,
        'UserManagerDelete'           => \ForkBB\Models\User\Delete::class,
        'UserManagerChangeGroup'      => \ForkBB\Models\User\ChangeGroup::class,
        'UserManagerAdminsIds'        => \ForkBB\Models\User\AdminsIds::class,
        'UserManagerStats'            => \ForkBB\Models\User\Stats::class,

        'ForumModel'           => \ForkBB\Models\Forum\Model::class,
        'ForumModelCalcStat'   => \ForkBB\Models\Forum\CalcStat::class,
        'ForumManager'         => \ForkBB\Models\Forum\Manager::class,
        'ForumManagerRefresh'  => \ForkBB\Models\Forum\Refresh::class,
        'ForumManagerLoadTree' => \ForkBB\Models\Forum\LoadTree::class,
        'ForumManagerSave'     => \ForkBB\Models\Forum\Save::class,
        'ForumManagerDelete'   => \ForkBB\Models\Forum\Delete::class,
        'ForumManagerMarkread' => \ForkBB\Models\Forum\Markread::class,
        'ForumManagerUpdateUsername' => \ForkBB\Models\Forum\UpdateUsername::class,

        'TopicModel'         => \ForkBB\Models\Topic\Model::class,
        'TopicModelCalcStat' => \ForkBB\Models\Topic\CalcStat::class,
        'TopicManagerLoad'   => \ForkBB\Models\Topic\Load::class,
        'TopicManagerSave'   => \ForkBB\Models\Topic\Save::class,
        'TopicManagerDelete' => \ForkBB\Models\Topic\Delete::class,
        'TopicManagerView'   => \ForkBB\Models\Topic\View::class,
        'TopicManagerAccess' => \ForkBB\Models\Topic\Access::class,
        'TopicManagerMerge'  => \ForkBB\Models\Topic\Merge::class,
        'TopicManagerMove'   => \ForkBB\Models\Topic\Move::class,
        'TopicManagerUpdateUsername' => \ForkBB\Models\Topic\UpdateUsername::class,

        'PostModel'               => \ForkBB\Models\Post\Model::class,
        'PostManagerLoad'         => \ForkBB\Models\Post\Load::class,
        'PostManagerSave'         => \ForkBB\Models\Post\Save::class,
        'PostManagerDelete'       => \ForkBB\Models\Post\Delete::class,
        'PostManagerPreviousPost' => \ForkBB\Models\Post\PreviousPost::class,
        'PostManagerView'         => \ForkBB\Models\Post\View::class,
        'PostManagerRebuildIndex' => \ForkBB\Models\Post\RebuildIndex::class,
        'PostManagerUserInfoFromIP' => \ForkBB\Models\Post\UserInfoFromIP::class,
        'PostManagerUserStat'     => \ForkBB\Models\Post\UserStat::class,
        'PostManagerMove'         => \ForkBB\Models\Post\Move::class,
        'PostManagerFeed'         => \ForkBB\Models\Post\Feed::class,
        'PostManagerUpdateUsername' => \ForkBB\Models\Post\UpdateUsername::class,

        'PollModel' => \ForkBB\Models\Poll\Model::class,


        'ReportModel'             => \ForkBB\Models\Report\Model::class,
        'ReportManagerSave'       => \ForkBB\Models\Report\Save::class,
        'ReportManagerLoad'       => \ForkBB\Models\Report\Load::class,

        'GroupModel'         => \ForkBB\Models\Group\Model::class,
        'GroupManager'       => \ForkBB\Models\Group\Manager::class,
        'GroupManagerSave'   => \ForkBB\Models\Group\Save::class,
        'GroupManagerDelete' => \ForkBB\Models\Group\Delete::class,
        'GroupManagerPerm'   => \ForkBB\Models\Group\Perm::class,

        'CategoriesManager' => \ForkBB\Models\Categories\Manager::class,

        'SearchModelActionP' => \ForkBB\Models\Search\ActionP::class,
        'SearchModelActionT' => \ForkBB\Models\Search\ActionT::class,
        'SearchModelActionF' => \ForkBB\Models\Search\ActionF::class,
        'SearchModelDelete'  => \ForkBB\Models\Search\Delete::class,
        'SearchModelIndex'   => \ForkBB\Models\Search\Index::class,
        'SearchModelTruncateIndex'   => \ForkBB\Models\Search\TruncateIndex::class,
        'SearchModelPrepare' => \ForkBB\Models\Search\Prepare::class,
        'SearchModelExecute' => \ForkBB\Models\Search\Execute::class,

        'BBStructure' => \ForkBB\Models\BBCodeList\Structure::class,
    ],
];
