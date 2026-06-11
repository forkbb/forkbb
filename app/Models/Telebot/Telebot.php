<?php
/**
 * This file is part of the ForkBB <https://forkbb.org, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Telebot;

use ForkBB\Core\Container;
use ForkBB\Models\Model;
use ForkBB\Models\User\User;
use PDO;
use RuntimeException;

class Telebot extends Model
{
    const END = -3;

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Telebot';

    protected function theEnd(string $text): string
    {
        return \substr((string) \array_sum(\array_map(function ($a) {return \ord($a);}, \str_split($text))), self::END);
    }

    /**
     * Формирует строку активации
     */
    public function initText(User $user): string
    {
        $args = [
            'id'   => $user->id,
            'name' => $user->username,
            'pass' => $user->password,
        ];
        $time = \time() + 600;
        $hash = $this->c->Csrf->createHash($this->cKey, $args, $time);
        $init = "init{$user->id}x{$hash}";

        return $init . $this->theEnd($init);
    }

    /**
     * Проверяет текст на строку активации
     */
    protected function verifyText(string $text): null|string|User
    {
        if (! \str_starts_with($text, 'init')) {
            return null;
        }

        $end  = \substr($text, self::END);
        $init = \substr($text, 0, self::END);

        if (
            true !== \hash_equals($end, $this->theEnd($init))
            || ! \preg_match('%^init([1-9]\d*)x(.+)$%', $init, $matches)
            || ! ($user = $this->c->users->load((int) $matches[1])) instanceof User
        ) {
            return 'Bad init';
        }

        $args = [
            'id'   => $user->id,
            'name' => $user->username,
            'pass' => $user->password,
        ];

        if (true !== $this->c->Csrf->verify($matches[2], $this->cKey, $args)) {
            return $this->c->Csrf->getError();
        }

        return $user;
    }

    protected function uid(int $chatId): int
    {
        $vars = [
            ':cid' => $chatId,
        ];
        $query = 'SELECT u.id
            FROM ::users AS u
            WHERE u.telegram_chat_id=?i:cid
            LIMIT 1';

        return (int) $this->c->DB->query($query, $vars)->fetchColumn();
    }

    /**
     * Обрабатывает запрос от telegram
     */
    public function hookHandler(int $chatId, string $text): ?string
    {
        $text = \trim($text);
        $uid  = $this->uid($chatId);

        // обработка команд
        if ($uid > 0) {
            return 'Setting information';

        // подключение бота
        } else {
            $status = $this->c->telebot->verifyText($text);

            if ($status instanceof User) {
                if (! empty($status->telegram_chat_id)) {
                    return 'Setting information';
                }

                $status->telegram_chat_id = $chatId;

                $this->c->users->update($status);

                return 'Bot is connected, set it up';

            } else {
                return $status;
            }
        }
    }
}
