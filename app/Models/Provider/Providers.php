<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Provider;

use ForkBB\Core\Container;
use ForkBB\Models\Manager;
use ForkBB\Models\Provider\Driver;
use RuntimeException;

class Providers extends Manager
{
    const CACHE_KEY = 'providers';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Providers';

    /**
     * Кэш таблицы providers
     */
    protected ?array $cache = null;

    /**
     * Флаг готовности репозитория моделей
     */
    protected bool $ready = false;

    public function __construct(protected array $drivers, Container $c)
    {
        parent::__construct($c);
    }

    /**
     * Заполняет репозиторий провайдерами
     */
    public function init(): self
    {
        if (! $this->ready) {
            foreach ($this->cache() as $cur) {
                $this->create($cur);
            }

            $this->ready = true;
        }

        return $this;
    }

    /**
     * Создает провайдера
     */
    public function create(array $attrs = []): Driver
    {
        if (! isset($attrs['pr_name'])) {
            throw new RuntimeException('Provider name missing');

        } elseif (! isset($this->drivers[$attrs['pr_name']])) {
            throw new RuntimeException("No driver for '{$attrs['pr_name']}' provider");

        } elseif ($this->isset($attrs['pr_name'])) {
            throw new RuntimeException("Driver '{$attrs['pr_name']}' already exists");
        }

        $class         = $this->drivers[$attrs['pr_name']];
        $driver        = new $class($attrs['pr_cl_id'], $attrs['pr_cl_sec'], $this->c);
        $driver->name  = $attrs['pr_name'];
        $driver->pos   = $attrs['pr_pos'];
        $driver->allow = $attrs['pr_allow'];

        $this->set($driver->name, $driver);

        return $driver;
    }

    /**
     * Возращает список имён активных провайдеров
     */
    public function active(): array
    {
        if (
            ! \extension_loaded('curl')
            && ! \filter_var(\ini_get('allow_url_fopen'), \FILTER_VALIDATE_BOOL)
        ) {
            return [];
        }

        $result = [];

        foreach ($this->cache() as $cur) {
            if (
                $cur['pr_allow']
                && '' != $cur['pr_cl_id']
                && '' != $cur['pr_cl_sec']
            ) {
                $result[$cur['pr_name']] = $cur['pr_name'];
            }
        }

        return $result;
    }

    /**
     * Возращает/создает кэш таблицы providers
     */
    protected function cache(): array
    {
        if (! \is_array($this->cache)) {
            $this->cache = $this->c->Cache->get(self::CACHE_KEY);
        }

        if (! \is_array($this->cache)) {
            $query = 'SELECT pr_name, pr_allow, pr_pos, pr_cl_id, pr_cl_sec FROM ::providers ORDER BY pr_pos';
            $stmt  = $this->c->DB->query($query);

            while ($cur = $stmt->fetch()) {
                $this->cache[$cur['pr_name']] = $cur;
            }

            if (true !== $this->c->Cache->set(self::CACHE_KEY, $this->cache)) {
                throw new RuntimeException('Unable to write value to cache - ' . self::CACHE_KEY);
            }
        }

        return $this->cache;
    }

    /**
     * Проверяет поле на пустоту
     */
    protected function emptyField(string $key, array $sets, array $cache): bool
    {
        if (isset($sets[$key])) {
            return '' == $sets[$key];

        } else {
            return '' == $cache[$key];
        }
    }

    /**
     * Обновляет таблицу providers на основе данных полученных из формы
     * Удаляет кэш
     */
    public function update(array $form): self
    {
        $cache  = $this->cache();
        $fields = $this->c->dbMap->providers;

        foreach ($form as $driver => $sets) {
            if (! isset($this->drivers[$driver], $cache[$driver])) {
                continue;
            }

            if (
                (
                    ! empty($sets['pr_allow'])
                    || ! empty($cache[$driver]['pr_allow'])
                )
                && (
                    $this->emptyField('pr_cl_id', $sets, $cache[$driver])
                    || $this->emptyField('pr_cl_sec', $sets, $cache[$driver])
                )
            ) {
                $sets['pr_allow'] = 0;
            }

            $set = $vars = [];

            foreach ($sets as $name => $value) {
                if (
                    ! isset($fields[$name])
                    || $cache[$driver][$name] == $value
                ) {
                    continue;
                }

                $vars[] = $value;
                $set[]  = $name . '=?' . $fields[$name];
            }

            if (empty($set)) {
                continue;
            }

            $vars[] = $driver;
            $set    = \implode(', ', $set);
            $query  = "UPDATE ::providers
                SET {$set}
                WHERE pr_name=?s";

            $this->c->DB->exec($query, $vars);
        }

        if (true !== $this->c->Cache->delete(self::CACHE_KEY)) {
            throw new RuntimeException('Unable to delete cache - ' . self::CACHE_KEY);
        }

        return $this;
    }
}
