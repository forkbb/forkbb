<?php

namespace ForkBB\Models\Report;

use ForkBB\Models\Action;
use ForkBB\Models\Report\Model as Report;
use RuntimeException;

class Save extends Action
{
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
        $fileds = $this->c->dbMap->reports;
        $set = $vars = [];
        foreach ($modified as $name) {
            if (! isset($fileds[$name])) {
                continue;
            }
            $vars[] = $values[$name];
            $set[]  = $name . '=?' . $fileds[$name];
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

        $report->created = \time(); //????

        $attrs  = $report->getAttrs();
        $fileds = $this->c->dbMap->reports;
        $set = $set2 = $vars = [];
        foreach ($attrs as $key => $value) {
            if (! isset($fileds[$key])) {
                continue;
            }
            $vars[] = $value;
            $set[]  = $key;
            $set2[] = '?' . $fileds[$key];
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

        if (true !== $this->c->Cache->set('report', $report->id)) {
            throw new RuntimeException('Unable to write value to cache - report');
        }

        return $report->id;
    }
}
