<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\Container;
use RuntimeException;

abstract class RulesValidator
{
    public function __construct(protected Container $c)
    {
    }

    /**
     * Выбрасывает исключение при отсутствии метода
     */
    public function __call(string $name, array $args)
    {
        throw new RuntimeException($name . ' validator not found');
    }
}
