<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\PM;

use ForkBB\Core\Container;
use ForkBB\Models\Model;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\User\Model as User;
use InvalidArgumentException;
use RuntimeException;

class PRnd extends Model
{

    /**
     * Соотвествие между номерами в топике и пользователями
     * @var array
     */
    protected $numToUser;

    /**
     * Соотвествие между id пользователей и их номерами в топике
     * @var array
     */
    protected $idToNum;

    /**
     * @var array
     */
    protected $tmpList;

    /**
     * $this->list = ....
     */
    protected function setlist(array $list): void
    {
        foreach ($list as $cur) {
            $this->list($cur['user_number'], $cur);
        }
    }

    protected function list(int $number, /* User|array*/ $data): void
    {
        if ($data instanceof User) {
            $user = $data;
            $data = [
                'user_number' => $number,
                'user_id'     => $data->id,
                'username'    => $data->username,
                'pt_status'   => Cnst::PT_START,
                'pt_visit'    => 0,
            ];
        } else {
            $user = null;
        }

        if (isset($data['user_number'], $data['user_id'], $data['username'], $data['pt_status'], $data['pt_visit'])) {
            if ($number !== $data['user_number']) {
                throw new RuntimeException('Invalid serial number');
            }
        } else {
            throw new RuntimeException('No all required data');
        }

        $list          = $this->getAttr('list', []);
        $list[$number] = $data;

        $this->setAttr('list', $list);

        $this->idToNum[$data['user_id']] = $number;
        $this->numToUser[$number]        = $user;
    }

    /**
     * Устанавливает отправителя
     */
    public function setSender(User $user): self
    {
        if (isset($this->list[0])) {
            throw new RuntimeException('Sender already set');
        } elseif (isset($this->$idToNum[$user->id])) {
            throw new RuntimeException('Sender already specified in the recipient');
        }

        $this->list(0, $user);

        return $this;
    }

    /**
     * Добавляет получателя
     */
    public function addRecipient(User $user): self
    {
        if (isset($this->$idToNum[$user->id])) {
            throw new RuntimeException('This recipient is already set');
        }

        $next = 1 + \max(\array_keys($this->list));

        $this->list($next, $user);

        return $this;
    }

    /**
     * Возвращает пользователя по номеру в топике
     */
    public function userByNum(int $number): User
    {
        if (isset($numToUser[$number])) {
            return $numToUser[$number];
        } elseif (isset($this->list[$number])) {
            $data = $this->list[$number];
            $user = $this->c->users->load($data['user_id']);

            if (
                ! $user instanceof User
                && 1 !== $data['user_id'] // ???? может сменить id гостя?
            ) {
                $user = $this->c->users->load(1);
            }

            if (! $user instanceof User) {
                throw new RuntimeException("No user data in post number {$data['user_id']}");
            } elseif ($user->isGuest) {
                $user = clone $user;

                $user->__username = $data['username'];
            }

            return $numToUser[$number] = $user;
        } else {
            throw new RuntimeException("No user of the number {$number}");
        }
    }

    protected function setstatus(int $status): void
    {
        if (! isset($this->$idToNum[$this->c->user->id])) {
            throw new RuntimeException('Someone else\'s private topic');
        }

        $number        = $this->$idToNum[$this->c->user->id];
        $this->tmpList = $this->getAttr('list', []);

        if (0 === $number) {
            switch ($status) {
                case Cnst::PT_NORMAL:
                case Cnst::PT_DELETED:
                    $this->stFromStNs($status);

                    break;
                case Cnst::PT_ARCHIVE:
                    $this->stFromStNs(Cnst::PT_NOTSENT);

                    break;
                case Cnst::PT_NOTSENT:
                default:
                    throw new InvalidArgumentException("Bad status: {$status}");
            }
        }

        $this->tmpList[$number]['pt_status'] = $status;

        $this->setAttr('list', $this->tmpList);
    }

    /**
     * Из PT_START и PT_NOTSENT в $new
     */
    protected function stFromStNs(int $new): void
    {
        \array_walk($this->tmpList, function (&$cur, $key, $new) {
            switch ($cur['pt_status']) {
                case Cnst::PT_START:
                case Cnst::PT_NOTSENT:
                    $cur['pt_status'] = $new;
            }
        }, $new);
    }
}
