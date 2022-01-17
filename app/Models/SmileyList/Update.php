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
use ForkBB\Models\SmileyList\SmileyList;
use InvalidArgumentException;

class Update extends Method
{
    /**
     * Обновляет запись в БД для смайла
     */
    public function update(int $id, array $data): SmileyList
    {
        if (
            isset($data['id'])
            || ! isset($data['sm_code'], $data['sm_position'], $data['sm_image'])
            || '' === $data['sm_code']
            || '' === $data['sm_image']
        ) {
            throw new InvalidArgumentException('Expected an array with a smile description');
        }

        $data[':id'] = $id;

        $query = 'UPDATE ::smilies
            SET sm_code=?s:sm_code, sm_position=?i:sm_position, sm_image=?s:sm_image
            WHERE id=?i:id';

        $this->c->DB->exec($query, $data);

        return $this->model;
    }
}
