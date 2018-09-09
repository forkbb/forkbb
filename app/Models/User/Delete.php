<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Action
{
    /**
     * Удаляет пользователя(ей)
     *
     * @param mixed ...$args
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function delete(...$args)
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected User(s)');
        }

        $users = [];

        foreach ($args as $arg) {
            if ($arg instanceof User) {
                if ($arg->isGuest) {
                    throw new RuntimeException('Guest can not be deleted');
                }
                $users[] = $arg->id;
            } else {
                throw new InvalidArgumentException('Expected User');
            }
        }

        $this->c->forums->delete(...$args);

        //???? подписки, опросы, предупреждения

        foreach ($args as $user) {
            $this->c->Online->delete($user);
        }

        $vars = [
            ':users' => $users,
        ];
        $sql = 'DELETE FROM ::users
                WHERE id IN (?ai:users)';
        $this->c->DB->exec($sql, $vars);
    }
}
