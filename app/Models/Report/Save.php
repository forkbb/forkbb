<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Report;

use ForkBB\Models\Action;
use ForkBB\Models\Report\Report;
use RuntimeException;

class Save extends Action
{
    const CACHE_KEY = 'report';

    /**
     * Обновляет репорт в БД
     */
    public function update(Report $report): Report
    {
        if ($report->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }

        $modified = $report->getModified();

        if (empty($modified)) {
            return $report;
        }

        $values = $report->getAttrs();
        $fields = $this->c->dbMap->reports;
        $set = $vars = [];

        foreach ($modified as $name) {
            if (! isset($fields[$name])) {
                continue;
            }

            $vars[] = $values[$name];
            $set[]  = $name . '=?' . $fields[$name];
        }

        if (empty($set)) {
            return $report;
        }

        $vars[] = $report->id;

        $set   = \implode(', ', $set);
        $query = "UPDATE ::reports
            SET {$set}
            WHERE id=?i";

        $this->c->DB->exec($query, $vars);
        $report->resModified();

        return $report;
    }

    /**
     * Добавляет новый репорт в БД
     */
    public function insert(Report $report): int
    {
        if (null !== $report->id) {
            throw new RuntimeException('The model has ID');
        }

        $report->created = \time();

        $attrs  = $report->getAttrs();
        $fields = $this->c->dbMap->reports;
        $set = $set2 = $vars = [];

        foreach ($attrs as $key => $value) {
            if (! isset($fields[$key])) {
                continue;
            }

            $vars[] = $value;
            $set[]  = $key;
            $set2[] = '?' . $fields[$key];
        }

        if (empty($set)) {
            throw new RuntimeException('The model is empty');
        }

        $set   = \implode(', ', $set);
        $set2  = \implode(', ', $set2);
        $query = "INSERT INTO ::reports ({$set})
            VALUES ({$set2})";

        $this->c->DB->exec($query, $vars);
        $report->id = (int) $this->c->DB->lastInsertId();
        $report->resModified();

        if (true !== $this->c->Cache->set(self::CACHE_KEY, $report->id)) {
            throw new RuntimeException('Unable to write value to cache - ' . self::CACHE_KEY);
        }

        return $report->id;
    }
}
