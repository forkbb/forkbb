<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\BBCodeList;

use ForkBB\Models\Method;
use ForkBB\Models\BBCodeList\BBCodeList;
use PDO;

class Load extends Method
{
    /**
     * Загружает таблицу bbcode в массив
     */
    public function load(): BBCodeList
    {
        $query = 'SELECT id, bb_tag, bb_edit, bb_delete, bb_structure
            FROM ::bbcode
            ORDER BY id';

        $this->model->bbcodeTable = $this->c->DB->query($query)->fetchAll(PDO::FETCH_UNIQUE);

        return $this->model;
    }
}
