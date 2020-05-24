<?php

namespace ForkBB\Models\Censorship;

use ForkBB\Models\Method;
use ForkBB\Models\Censorship\Model as Censorship;
use PDO;

class Save extends Method
{
    /**
     * Сохраняет список нецензурных слов в базу
     *
     * @param array $list
     *
     * @return Censorship
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
                if ($list[$id]['search_for'] !== $words[$id]['search_for']
                    || $list[$id]['replace_with'] !== $words[$id]['replace_with']
                ) {
                    $vars = [
                        ':id'      => $id,
                        ':search'  => $list[$id]['search_for'],
                        ':replace' => $list[$id]['replace_with'],
                    ];
                    $sql = 'UPDATE ::censoring
                            SET search_for=?s:search, replace_with=?s:replace
                            WHERE id=?i:id';
                    $this->c->DB->exec($sql, $vars);
                }
            } elseif (0 === $id) {
                $vars = [
                    ':search'  => $list[$id]['search_for'],
                    ':replace' => $list[$id]['replace_with'],
                ];
                $sql = 'INSERT INTO ::censoring (search_for, replace_with)
                        VALUES (?s:search, ?s:replace)';
                $this->c->DB->exec($sql, $vars);
            }
        }
        if ($forDel) {
            $vars = [
                ':del' => $forDel
            ];
            $sql = 'DELETE FROM ::censoring WHERE id IN (?ai:del)';
            $this->c->DB->exec($sql, $vars);
        }

        $this->c->Cache->delete('censorship');

        return $this->model;
    }
}
