<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Notification;

use ForkBB\Core\Container;
use ForkBB\Models\User\User;

abstract class Notification
{
    protected User $user;
    protected int $localRule;

    abstract public function init(array $data): bool;
    abstract public function title(): array|string;
    abstract public function text(): array|string;

    public function __construct(protected Container $c)
    {
    }

    public function user(): User
    {
        return $this->user;
    }

    public function rule(): int
    {
        return $this->localRule;
    }
}
