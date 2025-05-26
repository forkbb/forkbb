<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Draft;

use ForkBB\Models\Action;
use ForkBB\Models\Draft\Draft;
use RuntimeException;

class Save extends Action
{
    /**
     * Обновляет черновик в БД
     */
    public function update(Draft $draft): Draft
    {
        if ($draft->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }

        $modified = $draft->getModified();

        if (empty($modified)) {
            return $draft;
        }

        $values = $draft->getModelAttrs();
        $fields = $this->c->dbMap->drafts;
        $set = $vars = [];
        $resetPremod = false;

        foreach ($modified as $name) {
            if (! isset($fields[$name])) {
                continue;

            } elseif ('pre_mod' === $name) {
                $resetPremod = true;
            }

            $vars[] = $values[$name];
            $set[]  = $name . '=?' . $fields[$name];
        }

        if (empty($set)) {
            return $draft;
        }

        $vars[] = $draft->id;

        $set   = \implode(', ', $set);
        $query = "UPDATE ::drafts
            SET {$set}
            WHERE id=?i";

        $this->c->DB->exec($query, $vars);
        $draft->resModified();

        if ($resetPremod) {
            $this->c->premod->reset();
        }

        return $draft;
    }

    /**
     * Добавляет новый черновик в БД
     */
    public function insert(Draft $draft): int
    {
        if (null !== $draft->id) {
            throw new RuntimeException('The model has ID');
        }

        $attrs  = $draft->getModelAttrs();
        $fields = $this->c->dbMap->drafts;
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
        $query = "INSERT INTO ::drafts ({$set})
            VALUES ({$set2})";

        $this->c->DB->exec($query, $vars);

        $draft->id = (int) $this->c->DB->lastInsertId();

        $draft->resModified();

        if (1 === $draft->pre_mod) {
            $this->c->premod->reset();
        }

        return $draft->id;
    }
}
