<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\User;

class IsUniqueName extends Action
{
    /**
     * Добавляет экранированный символ в конец каждого элемента $input дописывая их в $output
     */
    protected function addSymbol(array $input, string $symbol, array $output = []): array
    {
        if ('#%' === $symbol) {
            $symbol = '%';

        } else {
            $symbol = \str_replace(['#', '_', '%'], ['##', '#_', '#%'], $symbol);
        }

        if (empty($input)) {
            $input = [''];
        }

        foreach ($input as $str) {
            $output[] = $str . $symbol;
        }

        return $output;
    }

    /**
     * Строит массив вариантов для сранения LIKE
     */
    protected function variants(string $str): array
    {
        \preg_match_all('%.%us', $str, $matches);

        $result = [];

        foreach ($matches[0] as $i => $symbol) {
            $tmp = [];

            if (isset($symbol[1])) {
                if ($i > 1) {
                    return $this->addSymbol($result, '#%'); // добавить % (без #) в конец каждого элемента
                }

                $symbolL = \mb_strtolower($symbol, 'UTF-8');
                $symbolU = \mb_strtoupper($symbol, 'UTF-8');

                if ($symbolL !== $symbol) {
                    $tmp = $this->addSymbol($result, $symbolL, $tmp);
                }

                if ($symbolU !== $symbol) {
                    $tmp = $this->addSymbol($result, $symbolU, $tmp);
                }
            }

            $result = $this->addSymbol($result, $symbol, $tmp);
        }

        return $result;
    }

    /**
     * Проверка на уникальность имени пользователя
     */
    public function isUniqueName(User $user): bool
    {
        $name7bit = 0 === \preg_match('%[\x80-\xFF]%', $user->username);
        $norm7bit = 0 === \preg_match('%[\x80-\xFF]%', $user->username_normal);
        $like     = 'LIKE';

        switch ($this->c->DB->getType()) {
//            case 'mysql':
//                break;
            case 'pgsql':
                $like = 'ILIKE';
//            case 'sqlite':
            default:
                // UTF-8 не нужен
                if ($name7bit && $norm7bit) {
                    break;
                }

                // бд поддерживает UTF-8 сравнение без учета регистра
                if ($this->c->config->insensitive()) {
                    break;
                }

                $vars  = [(int) $user->id];
                $query = 'SELECT u.username, u.username_normal
                    FROM ::users AS u
                    WHERE u.id!=?i
                        AND (';

                $sptr = '';
                $arr  = $this->variants($user->username);

                foreach ($arr as $value) {
                    $vars[] = $value;
                    $query .= "{$sptr}u.username {$like} ?s ESCAPE '#'";
                    $sptr   = ' OR ';
                }

                $arr  = $this->variants($user->username_normal);

                foreach ($arr as $value) {
                    $vars[] = $value;
                    $query .= "{$sptr}u.username_normal {$like} ?s ESCAPE '#'";
                    $sptr   = ' OR ';
                }

                $query .= ')';

                $nameL = \mb_strtolower($user->username, 'UTF-8');
                $nameU = \mb_strtoupper($user->username, 'UTF-8');
                $normL = \mb_strtolower($user->username_normal, 'UTF-8');
                $normU = \mb_strtoupper($user->username_normal, 'UTF-8');

                $stmt = $this->c->DB->query($query, $vars);

                while (false !== ($row = $stmt->fetch())) {
                    if (
                        \mb_strtolower($row['username'], 'UTF-8') === $nameL
                        || \mb_strtoupper($row['username'], 'UTF-8') === $nameU
                        || \mb_strtolower($row['username_normal'], 'UTF-8') === $normL
                        || \mb_strtoupper($row['username_normal'], 'UTF-8') === $normU
                    ) {
                        $stmt->closeCursor();

                        return false;
                    }
                }

                return true;
        }

        $vars = [
            ':id'    => (int) $user->id,
            ':name'  => \str_replace(['#', '_', '%'], ['##', '#_', '#%'], $user->username),
            ':norm'  => \str_replace(['#', '_', '%'], ['##', '#_', '#%'], $user->username_normal),
        ];
        $query = "SELECT 1
            FROM ::users AS u
            WHERE u.id!=?i:id
                AND (
                    u.username {$like} ?s:name ESCAPE '#'
                    OR u.username_normal {$like} ?s:norm ESCAPE '#'
                )";

        $result = $this->c->DB->query($query, $vars)->fetchAll();

        return ! \count($result);
    }
}
