<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Censorship;

use ForkBB\Models\Method;
use ForkBB\Models\Censorship\Censorship;
use PDO;
use RuntimeException;

class Save extends Method
{
    /**
     * Сохраняет список нецензурных слов в базу
     */
    public function save(array $list): Censorship
    {
        $words  = $this->model->load();
        $forDel = [];

        foreach ($list as $id => $row) {
            if (! isset($list[$id]['search_for'], $list[$id]['replace_with'])) {
                continue;
            }

            if ('' === \trim($list[$id]['search_for'])) {
                if ($id > 0) {
                    $forDel[] = $id;
                }
            } elseif (isset($words[$id])) {
                if (
                    $list[$id]['search_for'] !== $words[$id]['search_for']
                    || $list[$id]['replace_with'] !== $words[$id]['replace_with']
                ) {
                    $vars = [
                        ':id'      => $id,
                        ':search'  => $list[$id]['search_for'],
                        ':replace' => $list[$id]['replace_with'],
                    ];
                    $query = 'UPDATE ::censoring
                        SET search_for=?s:search, replace_with=?s:replace
                        WHERE id=?i:id';

                    $this->c->DB->exec($query, $vars);
                }
            } elseif (0 === $id) {
                $vars = [
                    ':search'  => $list[$id]['search_for'],
                    ':replace' => $list[$id]['replace_with'],
                ];
                $query = 'INSERT INTO ::censoring (search_for, replace_with)
                    VALUES (?s:search, ?s:replace)';

                $this->c->DB->exec($query, $vars);
            }
        }

        if ($forDel) {
            if (\count($forDel) > 1) {
                \sort($forDel, \SORT_NUMERIC);
            }

            $vars = [
                ':del' => $forDel,
            ];
            $query = 'DELETE
                FROM ::censoring
                WHERE id IN (?ai:del)';

            $this->c->DB->exec($query, $vars);
        }

        return $this->model->reset();
    }
}
