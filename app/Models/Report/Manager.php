<?php

namespace ForkBB\Models\Report;

use ForkBB\Models\ManagerModel;
use ForkBB\Models\Report\Model as Report;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class Manager extends ManagerModel
{
    /**
     * Создает новую модель сигнала
     *
     * @param array $attrs
     *
     * @return Report
     */
    public function create(array $attrs = []): Report
    {
        return $this->c->ReportModel->setAttrs($attrs);
    }

    /**
     * Загружает сигнал из БД
     *
     * @param int $id
     *
     * @return null|Report
     */
    public function load(int $id): ?Report
    {
        if ($this->isset($id)) {
            return $this->get($id);
        } else {
            $report = $this->Load->load($id);
            $this->set($id, $report);
            return $report;
        }
    }

    /**
     * Загрузка сигналов из БД
     *
     * @param bool $noZapped
     *
     * @return array
     */
    public function loadList(bool $noZapped = true): array
    {
        $result = [];
        foreach ($this->Load->loadList($noZapped) as $report) {
            if ($this->isset($report->id)) {
                $result[] = $this->get($report->id);
            } else {
                $result[] = $report;
                $this->set($report->id, $report);
            }
        }
        return $result;
    }

    /**
     * Обновляет сигнал в БД
     *
     * @param Report $report
     *
     * @return Report
     */
    public function update(Report $report): Report
    {
        return $this->Save->update($report);
    }

    /**
     * Добавляет новый сигнал в БД
     *
     * @param Report $report
     *
     * @return int
     */
    public function insert(Report $report): int
    {
        $id = $this->Save->insert($report);
        $this->set($id, $report);
        return $id;
    }

    /**
     * Id последнего репорта
     *
     * @return int
     */
    public function lastId(): int
    {
        if ($this->c->Cache->has('report')) {
            $last = $this->list = $this->c->Cache->get('report');
        } else {
            $last = (int) $this->c->DB->query('SELECT r.id FROM ::reports AS r ORDER BY r.id DESC LIMIT 1')->fetchColumn();

            $this->c->Cache->set('report', $last);
        }

        return $last;
    }

    /**
     * Удаляет старые обработанные сигналы
     */
    public function clear(): void
    {
        $sql = 'SELECT r.zapped
                FROM ::reports as r
                WHERE r.zapped!=0
                ORDER BY r.zapped DESC
                LIMIT 10,1';
        $time = (int) $this->c->DB->query($sql)->fetchColumn();

        if ($time > 0) {
            $vars = [
                ':time' => $time,
            ];
            $sql = 'DELETE FROM ::reports
                    WHERE zapped<=?i:time';
            $this->c->DB->exec($sql, $vars);
        }
    }
}
