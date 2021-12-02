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
use ForkBB\Models\User\User;
use InvalidArgumentException;
use RuntimeException;

class PBlock extends Model
{
    /**
     * Ключ модели для контейнера
     * @var string
     */
    protected $cKey = 'PBlock';

    /**
     * @var array
     */
    protected $repository;

    /**
     * Текущий пользователь установленный в методе init()
     * @var User
     */
    protected $user;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->init($container->user);
    }

    public function init(User $user): void
    {
        $this->setAttrs([]);

        $this->repository = [];
        $this->user       = $user;

        $vars = [
            ':id' => $user->id,
        ];
        $query = 'SELECT pb.bl_first_id, pb.bl_second_id
                    FROM ::pm_block AS pb
                   WHERE pb.bl_first_id=?i:id OR pb.bl_second_id=?i:id';

        $stmt = $this->c->DB->query($query, $vars);

        while ($row = $stmt->fetch()) {
            if ($row['bl_first_id'] === $user->id) {
                $this->repository[$user->id][$row['bl_second_id']] = $row['bl_second_id'];
            } else {
                $this->repository[$row['bl_first_id']] = true;
            }
        }
    }

    /**
     * Проверяет: $user в блоке у $this->user
     */
    public function isBlock(User $user): bool
    {
        return isset($this->repository[$this->user->id][$user->id]);
    }

    /**
     * Проверяет: $this->user в блоке у $user
     */
    public function inBlock(User $user): bool
    {
        return isset($this->repository[$user->id]);
    }

    /**
     * Установка блока: $this->user -> $user
     */
    public function add(User $user): bool
    {
        $vars = [
            ':first_id'  => $this->user->id,
            ':second_id' => $user->id,
        ];
        $query = 'INSERT INTO ::pm_block (bl_first_id, bl_second_id) VALUES (:first_id, :second_id)';

        return false !== $this->c->DB->exec($query, $vars);
    }

    /**
     * Снятие блока: $this->user -> $user
     */
    public function remove(User $user): bool
    {
        $vars = [
            ':first_id'  => $this->user->id,
            ':second_id' => $user->id,
        ];
        $query = 'DELETE FROM ::pm_block WHERE bl_first_id=:first_id AND bl_second_id=:second_id';

        return false !== $this->c->DB->exec($query, $vars);
    }

    /**
     * Возвращает массив заблокированных пользователей
     */
    protected function getlist(): array
    {
        if (empty($this->repository[$this->user->id])) {
            return [];
        }

        $list = $this->c->users->loadByIds($this->repository[$this->user->id]);

        foreach ($list as &$user) {
            if ($user instanceof User) {
                $user->linkPMUnblock = $this->c->Router->link(
                    'PMAction',
                    [
                        'action' => Cnst::ACTION_BLOCK,
                        'more1'  => $user->id,
                    ]
                );
            }
        }
        unset($user);

        return $list;
    }

    /**
     * Возращает статус возможности блокировки пользователя
     */
    public function canBlock(User $user): bool
    {
        return $this->user->id !== $user->id
            && ! $user->isAdmin
            && ! $user->isGuest;
    }
}
