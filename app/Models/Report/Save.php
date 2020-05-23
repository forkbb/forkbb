<?php

namespace ForkBB\Models\Report;

use ForkBB\Models\Action;
use ForkBB\Models\Report\Model as Report;
use RuntimeException;

class Save extends Action
{
    /**
     * Обновляет репорт в БД
     *
     * @param Report $report
     *
     * @throws RuntimeException
     *
     * @return Report
     */
    public function update(Report $report)
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
            $set[] = $name . '=?' . $fileds[$name];
        }
        if (empty($set)) {
            return $report;
        }
        $vars[] = $report->id;
        $this->c->DB->query('UPDATE ::reports SET ' . \implode(', ', $set) . ' WHERE id=?i', $vars);
        $report->resModified();

        return $report;
    }

    /**
     * Добавляет новый репорт в БД
     *
     * @param Report $report
     *
     * @throws RuntimeException
     *
     * @return int
     */
    public function insert(Report $report)
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
        $this->c->DB->query('INSERT INTO ::reports (' . \implode(', ', $set) . ') VALUES (' . \implode(', ', $set2) . ')', $vars);
        $report->id = $this->c->DB->lastInsertId();
        $report->resModified();

        $this->c->Cache->set('report', $report->id);

        return $report->id;
    }
}
