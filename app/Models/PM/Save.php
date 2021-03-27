<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\PM;

use ForkBB\Models\Method;
use ForkBB\Models\DataModel;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\PM\PPost;
use ForkBB\Models\PM\PTopic;
use ForkBB\Models\PM\PRnd;
use InvalidArgumentException;
use RuntimeException;

class Save extends Method
{
    public function update(DataModel $model): DataModel
    {
        if ($model->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }

        if ($model instanceof PPost) {
            $table  = 'pm_posts';
        } elseif ($model instanceof PTopic) {
            $table  = 'pm_topics';
        } else {
            throw new InvalidArgumentException('Bad model');
        }

        $modified = $model->getModified();

        if (empty($modified)) {
            return $model;
        }

        $values = $model->getAttrs();
        $fileds = $this->c->dbMap->{$table};

        $set = $vars = [];

        foreach ($modified as $name) {
            if (! isset($fileds[$name])) {
                continue;
            }

            $vars[] = $values[$name];
            $set[]  = $name . '=?' . $fileds[$name];
        }

        if (empty($set)) {
            return $model;
        }

        $vars[] = $model->id;
        $set    = \implode(', ', $set);
        $query  = "UPDATE ::{$table} SET {$set} WHERE id=?i";

        $this->c->DB->exec($query, $vars);
        $model->resModified();

        return $model;
    }

    public function insert(DataModel $model): int
    {
        if (null !== $model->id) {
            throw new RuntimeException('The model has ID');
        }

        if ($model instanceof PPost) {
            $table  = 'pm_posts';
        } elseif ($model instanceof PTopic) {
            $table  = 'pm_topics';
        } else {
            throw new InvalidArgumentException('Bad model');
        }

        $attrs  = $model->getAttrs();
        $fileds = $this->c->dbMap->{$table};

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
        $query = "INSERT INTO ::{$table} ({$set}) VALUES ({$set2})";

        $this->c->DB->exec($query, $vars);

        $model->id = (int) $this->c->DB->lastInsertId();

        $model->resModified();

        return $model->id;
    }
}
