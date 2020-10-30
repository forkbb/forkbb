<?php

declare(strict_types=1);

namespace ForkBB\Models\Report;

use ForkBB\Models\ManagerModel;
use ForkBB\Models\Report\Model as Report;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class Manager extends ManagerModel
{
    /**
     * Создает новую модель сигнала
     */
    public function create(array $attrs = []): Report
    {
        return $this->c->ReportModel->setAttrs($attrs);
    }

    /**
     * Загружает сигнал из БД
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
     */
    public function update(Report $report): Report
    {
        return $this->Save->update($report);
    }

    /**
     * Добавляет новый сигнал в БД
     */
    public function insert(Report $report): int
    {
        $id = $this->Save->insert($report);
        $this->set($id, $report);

        return $id;
    }

    /**
     * Id последнего репорта
     * Создает кеш
     */
    public function lastId(): int
    {
        $last = $this->list = $this->c->Cache->get('report');

        if (null === $last) {
            $query = 'SELECT r.id
                FROM ::reports AS r
                ORDER BY r.id DESC
                LIMIT 1';

            $last = (int) $this->c->DB->query($query)->fetchColumn();

            if (true !== $this->c->Cache->set('report', $last)) {
                throw new RuntimeException('Unable to write value to cache - report');
            }
        }

        return $last;
    }

    /**
     * Удаляет старые обработанные сигналы
     */
    public function clear(): void
    {
        $query = 'SELECT r.zapped
            FROM ::reports as r
            WHERE r.zapped!=0
            ORDER BY r.zapped DESC
            LIMIT 10,1';

        $time = (int) $this->c->DB->query($query)->fetchColumn();

        if ($time > 0) {
            $vars  = [
                ':time' => $time,
            ];
            $query = 'DELETE
                FROM ::reports
                WHERE zapped!=0 AND zapped<=?i:time';

            $this->c->DB->exec($query, $vars);
        }
    }
}
