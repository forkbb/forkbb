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
use ForkBB\Models\User\User;
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
     * Возращает id локального пользователя по email или 0
     */
    public function findByEmail(string $email): int
    {
        if ('' == $email) {
            throw new RuntimeException('The email is empty');
        }

        $vars = [
            ':email' => $this->c->NormEmail->normalize($email),
        ];
        $query = 'SELECT pu.uid
            FROM ::providers_users AS pu
            WHERE pu.pu_email_normal=?s:email
            GROUP BY pu.uid';

        $result = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
        $count  = \count($result);

        if ($count > 1) {
            throw new RuntimeException("Many entries for '{$email}'");
        }

        return $count ? \array_pop($result) : 0;
    }

    /**
     * Создает новую связь между пользователем и провайдером
     */
    public function registration(User $user, Driver $provider): bool
    {
        if ($user->isGuest) {
            throw new RuntimeException('User expected, not guest');
        } elseif ('' == $provider->userId) {
            throw new RuntimeException('The user ID is empty');
        } elseif ('' == $provider->userEmail) {
            throw new RuntimeException('The user email is empty');
        }

        $vars = [
            ':uid'    => $user->id,
            ':name'   => $provider->name,
            ':userid' => $provider->userId,
            ':email'  => $provider->userEmail,
            ':normal' => $this->c->NormEmail->normalize($provider->userEmail),
            ':verif'  => $provider->userEmailVerifed ? 1 : 0,
        ];
        $query = 'INSERT INTO ::providers_users (uid, pr_name, pu_uid, pu_email, pu_email_normal, pu_email_verified)
            VALUES (?i:uid, ?s:name, ?s:userid, ?s:email, ?s:normal, ?i:verif)';

        return false !== $this->c->DB->exec($query, $vars);
    }

    /**
     * Удаляет OAuth аккаунты удаляемых пользователей
     */
    public function delete(User ...$users): void
    {
        $ids = [];

        foreach ($users as $user) {
            $ids[$user->id] = $user->id;
        }

        $vars = [
            ':users' => $ids,
        ];
        $query = 'DELETE
            FROM ::providers_users
            WHERE uid IN (?ai:users)';

        $this->c->DB->exec($query, $vars);
    }

    /**
     * Удаляет один OAuth аккаунт данного пользователя (без проверки наличия)
     */
    public function deleteAccount(User $user, string $name, string $userId): void
    {
        if ($user->isGuest) {
            throw new RuntimeException('User expected, not guest');
        }

        $vars = [
            ':uid'    => $user->id,
            ':name'   => $name,
            ':userId' => $userId,
        ];
        $query = 'DELETE
            FROM ::providers_users
            WHERE uid=?i:uid AND pr_name=?s:name AND pu_uid=?s:userId';

        $this->c->DB->exec($query, $vars);
    }

    /**
     * Вовращает список записей по пользователю
     */
    public function loadUserData(User $user): array
    {
        $vars = [
            ':id' => $user->id,
        ];
        $query = 'SELECT pu.pr_name AS name, pu.pu_uid AS userId, pu.pu_email AS userEmail, pu.pu_email_verified AS userEmailVerifed
            FROM ::providers_users AS pu
            WHERE pu.uid=?i:id
            ORDER BY pu.pr_name, pu.pu_uid';

        return $this->c->DB->query($query, $vars)->fetchAll();
    }
}
