<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Censorship;

use ForkBB\Models\Method;

class Refresh extends Method
{
    /**
     * Загружает данные из БД для модели и кеша
     */
    public function refresh(): array
    {
        $query = 'SELECT ce.id, ce.search_for, ce.replace_with
            FROM ::censoring AS ce';

        $stmt    = $this->c->DB->query($query);
        $search  = [];
        $replace = [];

        while ($row = $stmt->fetch()) {
            $search[$row['id']]  = '%(?<![\p{L}\p{N}])('
                . \str_replace('\*', '[\p{L}\p{N}]*?', \preg_quote($row['search_for'], '%'))
                . ')(?![\p{L}\p{N}])%iu';
            $replace[$row['id']] = $row['replace_with'];
        }

        return [
            'searchList'  => $search,
            'replaceList' => $replace,
        ];
    }
}
