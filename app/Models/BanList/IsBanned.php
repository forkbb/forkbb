<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;
use ForkBB\Models\User\User;
use InvalidArgumentException;

class IsBanned extends Method
{
    /**
     * Проверяет наличие бана пользователя на основании email
     *
     * результат: 0 - для этого email нет бана
     *            1 - забанен именно этот email
     *            2 - забанен домен
     */
    public function isBanned(User $user): int
    {
        if (empty($this->model->emailList)) {
            return 0;
        }

        $email = $this->model->trimToNull($user->email_normal);

        if (null === $email) {
            throw new InvalidArgumentException('Expected email, not empty string');
        }

        $stage = 0;

        do {
            if (isset($this->model->emailList[$email])) {
                return false === \strpos($email, '@') ? 2 : 1;
            }

            switch ($stage) {                               // "super@user"@example.com
                case 0:
                    $pos = \strrpos($email, '@');

                    if (false !== $pos) {
                        $email = \substr($email, $pos + 1); // -> example.com

                        break;
                    }

                    ++$stage;
                case 1:
                    $email = '.' . $email;                  // -> .example.com
                    $pos   = true;

                    break;
                default:
                    $pos = \strpos($email, '.', 1);

                    if (false !== $pos) {
                        $email = \substr($email, $pos);     // -> .com
                    }

                    break;
            }

            ++$stage;
        } while (false !== $pos);

        return 0;
    }
}
