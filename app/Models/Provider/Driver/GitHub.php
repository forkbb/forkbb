<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Provider\Driver;

use ForkBB\Models\Provider\Driver;
use RuntimeException;

class GitHub extends Driver
{
    protected string $originalName = 'github';
    protected string $authURL      = 'https://github.com/login/oauth/authorize';
    protected string $tokenURL     = 'https://github.com/login/oauth/access_token';
    protected string $userURL      = 'https://api.github.com/user';
    protected string $scope        = 'read:user';

}
