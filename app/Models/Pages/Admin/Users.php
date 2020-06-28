<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\User\Model as User;

abstract class Users extends Admin
{
    const ACTION_BAN = 'ban';
    const ACTION_DEL = 'delete';
    const ACTION_CHG = 'change_group';

    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->aIndex = 'users';

        $this->c->Lang->load('admin_users');
    }

    /**
     * Кодирует данные фильтра для url
     *
     * @param string|array $data
     *
     * @return string
     */
    protected function encodeData($data): string
    {
        if (\is_array($data)) {
            unset($data['token']);
            $data = \base64_encode(\json_encode($data));
            $hash = $this->c->Secury->hash($data);
            return "{$data}:{$hash}";
        } else {
            return "ip:{$data}";
        }
    }

    /**
     * Декодирует данные фильтра из url
     *
     * @param string $data
     *
     * @return mixed
     */
    protected function decodeData(string $data)
    {
        $data = \explode(':', $data);

        if (2 !== \count($data)) {
            return false;
        }

        if ('ip' === $data[0]) {
            $ip = \filter_var($data[1], \FILTER_VALIDATE_IP);
            return false === $ip ? false : ['ip' => $ip];
        }

        if (
            ! \hash_equals($data[1], $this->c->Secury->hash($data[0]))
            || ! \is_array($data = \json_decode(\base64_decode($data[0], true), true))
        ) {
            return false;
        }

        return $data;
    }

    /**
     * Проверяет доступность действий над выбранными пользователями
     *
     * @param array $selected
     * @param string $action
     * @param bool $profile
     *
     * @return false|array
     */
    protected function checkSelected(array $selected, string $action, bool $profile = false)
    {
        $selected = \array_map(function ($value) { // ????
            return (int) $value;
        }, $selected);
        $bad = \array_filter($selected, function ($value) {
            return $value < 2;
        });

        if (! empty($bad)) {
            $this->fIswev = ['v', \ForkBB\__('Action not available')];
            return false;
        }

        $userList = $this->c->users->loadByIds($selected);
        $result   = [];
        foreach ($userList as $user) {
            if (! $user instanceof User) {
                continue;
            }

            switch ($action) {
                case self::ACTION_BAN:
                    if ($this->c->bans->isBanned($user)) {
                        $this->fIswev = ['i', \ForkBB\__('User is ban', $user->username)];
                        return false;
                    }
                    if (! $this->c->userRules->canBanUser($user)) {
                        $this->fIswev = ['v', \ForkBB\__('You are not allowed to ban the %s', $user->username)];
                        if ($user->isAdmMod) {
                            $this->fIswev = ['i', \ForkBB\__('No ban admins message')];
                        }
                        return false;
                    }
                    break;
                case self::ACTION_DEL:
                    if (! $this->c->userRules->canDeleteUser($user)) {
                        $this->fIswev = ['v', \ForkBB\__('You are not allowed to delete the %s', $user->username)];
                        if ($user->isAdmMod) {
                            $this->fIswev = ['i', \ForkBB\__('No delete admins message')];
                        }
                        return false;
                    }
                    break;
                case self::ACTION_CHG:
                    if (! $this->c->userRules->canChangeGroup($user, $profile)) {
                        $this->fIswev = ['v', \ForkBB\__('You are not allowed to change group for %s', $user->username)];
                        if ($user->isAdmin) {
                            $this->fIswev = ['i', \ForkBB\__('No move admins message')];
                        }
                        return false;
                    }
                    break;
                default:
                    $this->fIswev = ['v', \ForkBB\__('Action not available')];
                    return false;
            }

            $result[] = $user->id;
            if ($user->id === $this->user->id) {
                $this->fIswev = ['i', \ForkBB\__('You are trying to change your own group')];
            }
        }

        if (empty($result)) {
            $this->fIswev = ['v', \ForkBB\__('No users selected')];
            return false;
        }

        return $result;
    }
}
