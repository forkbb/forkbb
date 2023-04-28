<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\PM;

use ForkBB\Core\Container;
use ForkBB\Models\Page;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\User\User;
use function \ForkBB\__;

abstract class AbstractPM extends Page
{
    /**
     * @var array
     */
    protected $pmCrumbs = [];

    /**
     * @var  ForkBB\Models\PM\Manager
     */
    protected $pms;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->pms       = $container->pms;
        $this->pmIndex   = Cnst::ACTION_CURRENT; # string Указатель на активный пункт навигации в меню ЛС
        $this->fIndex    = self::FI_PM;
        $this->onlinePos = 'pm';
        $this->robots    = 'noindex, nofollow';
        $this->hhsLevel  = 'secure';
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare(): void
    {
        $this->pmNavigation = $this->pmNavigation();
        $this->crumbs       = $this->crumbs(...$this->pmCrumbs);

        if (1 !== $this->user->u_pm) {
            $this->fIswev = ['w', 'PM off'];
        }

        parent::prepare();
    }

    /**
     * Возвращает массив ссылок с описанием для построения навигации админки
     */
    protected function pmNavigation(): array
    {
        $r    = $this->c->Router;
        $args = [
            'second' => $this->pms->second,
        ];
        $nav  = [
            'pm-boxes' => [true, 'PM Folders'],
            Cnst::ACTION_NEW => [
                $r->link('PMAction', $args + ['action' => Cnst::ACTION_NEW]),
                $this->pms->numNew > 0 ? ['New messages %s', $this->pms->numNew] : 'New messages',
            ],
            Cnst::ACTION_CURRENT => [
                $r->link('PMAction', $args + ['action' => Cnst::ACTION_CURRENT]),
                $this->pms->numCurrent > 0 ? ['My talks %s', $this->pms->numCurrent] : 'My talks',
            ],
            Cnst::ACTION_ARCHIVE => [
                $r->link('PMAction', $args + ['action' => Cnst::ACTION_ARCHIVE]),
                $this->pms->numArchive > 0 ? ['Archive messages %s', $this->pms->numArchive] : 'Archive messages',
            ],
//            'pm-sp1' => [null, null],
        ];

        if ($this->user->g_pm_limit > 0) {
            $nav += [
                'pm-storage' => [true, 'PM Storage'],
                'pm-active' => [
                    false,
                    [
                        'Active: %s',
                        $this->user->g_pm_limit < 1 ? 0 : (int) (100 * $this->pms->totalCurrent / $this->user->g_pm_limit),
                    ],
                ],
                'pm-archive' => [
                    false,
                    [
                        'Archive: %s',
                        $this->user->g_pm_limit < 1 ? 0 : (int) (100 * $this->pms->totalArchive / $this->user->g_pm_limit),
                    ],
                ],
//                'pm-sp2' => [null, null],
            ];
        }

        $nav += [
            'pm-options' => [true, 'PM Options'],
            Cnst::ACTION_CONFIG => [
                $r->link('PMAction', ['action' => Cnst::ACTION_CONFIG]),
                'PM Config',
            ],
            Cnst::ACTION_BLOCK => [
                $r->link('PMAction', ['action' => Cnst::ACTION_BLOCK]),
                'Blocked users',
            ],
        ];

        return $nav;
    }

    /**
     * Возвращает массив хлебных крошек
     * Заполняет массив титула страницы
     */
    protected function crumbs(mixed ...$crumbs): array
    {
        $pms      = $this->pms;
        $action   = $this->args['action'] ?? ($this->user->u_pm_num_new > 0 ? Cnst::ACTION_NEW : Cnst::ACTION_CURRENT);
        $viewArea = false;

        switch ($action) {
            case Cnst::ACTION_NEW:
            case Cnst::ACTION_CURRENT:
            case Cnst::ACTION_ARCHIVE:
            case Cnst::ACTION_SEND:
            case Cnst::ACTION_TOPIC:
            case Cnst::ACTION_POST:
            case Cnst::ACTION_EDIT:
            case Cnst::ACTION_DELETE:
                $viewArea = true;
                break;
            case Cnst::ACTION_BLOCK:
            case Cnst::ACTION_CONFIG:
                break;
            default:
                $crumbs[] = [null, ['%s', 'unknown']];
        }

        if ($viewArea) {
            if (null !== $pms->second) {
                if (\is_int($pms->second)) {
                    if (
                        ($user = $this->c->users->load($pms->second)) instanceof User
                        && ! $user->isGuest
                    ) {
                        $name = $user->username;
                    } else {
                        $name = 'unknown'; // ????
                    }
                } else {
                    $name = \substr($pms->second, 1, -1);
                }

                switch ($pms->area) {
                    case Cnst::ACTION_NEW:     $m = ['New messages with %s', $name]; break;
                    case Cnst::ACTION_CURRENT: $m = ['My talks with %s', $name]; break;
                    case Cnst::ACTION_ARCHIVE: $m = ['Archive messages with %s', $name]; break;
                }
            } else {
                if ($this->targetUser instanceof User) {
                    $crumbs[] = [
                        $this->c->Router->link(
                            'PMAction',
                            [
                                'second' => $this->targetUser->isGuest
                                    ? '"' . $this->targetUser->username . '"'
                                    : $this->targetUser->id,
                                'action' => $pms->area,
                            ]
                        ),
                        ['"%s"', $this->targetUser->username],
                    ];
                }

                switch ($pms->area) {
                    case Cnst::ACTION_NEW:     $m = 'New messages'; break;
                    case Cnst::ACTION_CURRENT: $m = 'My talks'; break;
                    case Cnst::ACTION_ARCHIVE: $m = 'Archive messages'; break;
                }
            }

            $crumbs[] = [
                $this->c->Router->link(
                    'PMAction',
                    [
                        'second' => $pms->second,
                        'action' => $pms->area,
                    ]
                ),
                $m,
            ];

            if (null === $this->title) {
                $this->title = $m;
            }
        }

        $crumbs[] = [$this->c->Router->link('PM'), 'PM'];
        $result   = parent::crumbs(...$crumbs);

        $this->pmHeader = \end($result)[1];

        return $result;
    }
}
