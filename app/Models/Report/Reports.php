<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Report;

use ForkBB\Models\Manager;
use ForkBB\Models\Report\Report;
use ForkBB\Models\User\User;
use RuntimeException;

class Reports extends Manager
{
    const CACHE_KEY = 'report';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Reports';

    /**
     * Создает новую модель сигнала
     */
    public function create(array $attrs = []): Report
    {
        return $this->c->ReportModel->setModelAttrs($attrs);
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
        $last = $this->c->Cache->get(self::CACHE_KEY);

        if (null === $last) {
            $query = 'SELECT MAX(id) FROM ::reports';
            $last  = (int) $this->c->DB->query($query)->fetchColumn();

            if (true !== $this->c->Cache->set(self::CACHE_KEY, $last)) {
                throw new RuntimeException('Unable to write value to cache - ' . self::CACHE_KEY);
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
            LIMIT 1 OFFSET 10';

        $time = (int) $this->c->DB->query($query)->fetchColumn();

        if ($time > 0) {
            $vars = [
                ':time' => $time,
            ];
            $query = 'DELETE
                FROM ::reports
                WHERE zapped!=0 AND zapped<=?i:time';

            $this->c->DB->exec($query, $vars);
        }
    }
}
