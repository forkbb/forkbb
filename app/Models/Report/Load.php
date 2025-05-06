<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Report;

use ForkBB\Models\Action;
use ForkBB\Models\Report\Report;
use InvalidArgumentException;

class Load extends Action
{
    /**
     * Загружает сигнал из БД
     */
    public function load(int $id): ?Report
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Expected a positive report id');
        }

        $vars  = [
            ':id' => $id,
        ];
        $query = 'SELECT * FROM ::reports WHERE id=?i:id';
        $data  = $this->c->DB->query($query, $vars)->fetch();

        if (empty($data)) {
            return null;
        }

        $report = $this->manager->create($data);

        return $report;
    }

    /**
     * Загрузка сигналов из БД
     */
    public function loadList(bool $noZapped): array
    {
        $result = [];
        $vars   = [];

        if ($noZapped) {
            $query = 'SELECT r.*
                FROM ::reports AS r
                WHERE r.zapped=0
                ORDER BY r.id DESC';

        } else {
            $query = 'SELECT r.*
                FROM ::reports AS r
                WHERE r.zapped!=0
                ORDER BY r.zapped DESC'; // LIMIT 10 не нужен, если при обработке сигнала будут удалены старые
        }

        $data = $this->c->DB->query($query, $vars)->fetchAll();

        foreach ($data as $row) {
            $result[] = $this->manager->create($row);
        }

        return $result;
    }
}
