<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Config;

use ForkBB\Models\Method;
use ForkBB\Models\Config\Config;

class Save extends Method
{
    /**
     * Сохраняет изменения модели в БД
     * Удаляет кеш
     */
    public function save(): Config
    {
        $modified = $this->model->getModified();

        if (empty($modified)) {
            return $this->model;
        }

        $values = $this->model->getAttrs();

        foreach ($modified as $name) {
            if (\array_key_exists($name, $values)) {
                switch ($name[0]) {
                    case 'a':
                        $value = \json_encode($values[$name], FORK_JSON_ENCODE);

                        break;
                    case 'b':
                        $value = $values[$name] ? '1' : '0';

                        break;
                    case 'i':
                        if (null !== $values[$name]) {
                            $value = (string) $values[$name];

                            break;
                        }
                    default:
                        $value = $values[$name];

                        break;
                }

                $vars = [
                    ':value' => $value,
                    ':name'  => $name
                ];

                switch ($this->c->DB->getType()) {
                    case 'mysql':
                        $query = 'INSERT INTO ::config (conf_name, conf_value)
                            VALUES (?s:name, ?s:value)
                            ON DUPLICATE KEY UPDATE conf_value=?s:value';

                        break;
                    case 'sqlite':
                    case 'pgsql':
                        $query = 'INSERT INTO ::config (conf_name, conf_value)
                            VALUES (?s:name, ?s:value)
                            ON CONFLICT(conf_name) DO UPDATE SET conf_value=?s:value';

                        break;
                    default:
                        $query = 'UPDATE ::config
                            SET conf_value=?s:value
                            WHERE conf_name=?s:name';

                        $this->c->DB->exec($query, $vars);

                        $query = 'INSERT INTO ::config (conf_name, conf_value)
                            SELECT ?s:name, ?s:value
                            FROM ::groups
                            WHERE NOT EXISTS (
                                SELECT 1
                                FROM ::config
                                WHERE conf_name=?s:name
                            )
                            LIMIT 1';

                        break;
                }
            } else {
                $vars = [
                    ':name'  => $name
                ];
                $query = 'DELETE FROM ::config
                    WHERE conf_name=?s:name';
            }

            $this->c->DB->exec($query, $vars);
        }

        return $this->model->reset();
    }
}
