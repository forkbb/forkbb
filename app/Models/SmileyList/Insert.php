<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\SmileyList;

use ForkBB\Models\Method;
use InvalidArgumentException;

class Insert extends Method
{
    /**
     * Создает запись в БД для смайла
     */
    public function insert(array $data): int
    {
        if (
            isset($data['id'])
            || ! isset($data['sm_code'], $data['sm_position'], $data['sm_image'])
            || '' == $data['sm_code']
            || '' == $data['sm_image']
        ) {
            throw new InvalidArgumentException('Expected an array with a smile description');
        }

        $query = 'INSERT INTO ::smilies (sm_code, sm_position, sm_image)
            VALUES (?s:sm_code, ?i:sm_position, ?s:sm_image)';

        $this->c->DB->exec($query, $data);

        return (int) $this->c->DB->lastInsertId();
    }
}
