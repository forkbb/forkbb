<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\ProviderUser;

use ForkBB\Core\Container;
use ForkBB\Models\Model;
use ForkBB\Models\Provider\Driver;
use PDO;
use RuntimeException;

class ProviderUser extends Model
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'ProvUser';

    /**
     * Возращает id локального пользователя по данным провайдера или 0
     * Поиск идет по провайдеру и идентификатору пользователя
     */
    public function findUser(Driver $provider): int
    {
        if ('' == $provider->userId) {
            throw new RuntimeException('The user ID is empty');
        }

        $vars = [
            ':name' => $provider->name,
            ':id'   => $provider->userId,
        ];
        $query = 'SELECT pu.uid
            FROM ::providers_users AS pu
            WHERE pu.pr_name=?s:name AND pu.pu_uid=?s:id';

        $result = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
        $count  = \count($result);

        if ($count > 1) {
            throw new RuntimeException("Many entries for '{$provider->name}-{$provider->userId}'");
        }

        return $count ? \array_pop($result) : 0;
    }

    /**
     * Возращает id локального пользователя по данным провайдера или 0
     * Поиск идет по email
     */
    public function findEmail(Driver $provider): int
    {
        if ('' == $provider->userEmail) {
            throw new RuntimeException('The user email is empty');
        }

        $vars = [
            ':email' => $this->c->NormEmail->normalize($provider->userEmail),
        ];
        $query = 'SELECT pu.uid
            FROM ::providers_users AS pu
            WHERE pu.pu_email_normal=?s:email
            GROUP BY pu.uid';

        $result = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
        $count  = \count($result);

        if ($count > 1) {
            throw new RuntimeException("Many entries for '{$provider->userEmail}'");
        }

        return $count ? \array_pop($result) : 0;
    }
}
