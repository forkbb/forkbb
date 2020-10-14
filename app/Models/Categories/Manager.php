<?php

declare(strict_types=1);

namespace ForkBB\Models\Categories;

use ForkBB\Models\ManagerModel;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class Manager extends ManagerModel
{
    /**
     * Массив флагов измененных категорий
     * @var array
     */
    protected $modified = [];

    /**
     * Загрузка категорий из БД
     */
    public function init(): Manager
    {
        $query = 'SELECT c.id, c.cat_name, c.disp_position
            FROM ::categories AS c
            ORDER BY c.disp_position';

        $this->repository = $this->c->DB->query($query)->fetchAll(PDO::FETCH_UNIQUE);

        return $this;
    }

    public function getList(): array
    {
        return $this->repository;
    }

    public function set($key, $value): ManagerModel
    {
        if (! isset($value['cat_name'], $value['disp_position'])) {
            throw new InvalidArgumentException('Expected array with cat_name and disp_position elements');
        }

        $old = $this->get($key);

        if (empty($old)) {
            throw new RuntimeException("Category number {$key} is missing");
        }

        parent::set($key, $value);

        if ($old != $value) {
            $this->modified[$key] = true;
        }

        return $this;
    }

    public function update(): Manager
    {
        foreach ($this->modified as $key => $value) {
            $cat   = $this->get($key);
            $vars  = [
                ':name'     => $cat['cat_name'],
                ':position' => $cat['disp_position'],
                ':cid'      => $key,
            ];
            $query = 'UPDATE ::categories
                SET cat_name=?s:name, disp_position=?i:position
                WHERE id=?i:cid';

            $this->c->DB->exec($query, $vars);
        }
        $this->modified = [];

        return $this;
    }

    public function insert(string $name): int
    {
        $pos = 0;
        foreach ($this->repository as $cat) {
            if ($cat['disp_position'] > $pos) {
                $pos = $cat['disp_position'];
            }
        }
        ++$pos;

        $vars  = [
            ':name'     => $name,
            ':position' => $pos,
        ];
        $query = 'INSERT INTO ::categories (cat_name, disp_position)
            VALUES (?s:name, ?i:position)';

        $this->c->DB->exec($query, $vars);
        $cid = (int) $this->c->DB->lastInsertId();
        parent::set($cid, ['cat_name' => $name, 'disp_position' => $pos]);

        return $cid;
    }

    public function delete(int $cid): Manager
    {
        $root = $this->c->forums->get(0);
        $del  = [];

        foreach ($root->subforums as $forum) {
            if ($forum->cat_id === $cid) {
                $del = \array_merge($del, [$forum], $forum->descendants);
            }
        }

        if ($del) {
            $this->c->forums->delete(...$del);
        }

        $vars  = [
            ':cid' => $cid,
        ];
        $query = 'DELETE
            FROM ::categories
            WHERE id=?i:cid';

        $this->c->DB->exec($query, $vars);

        return $this;
    }
}
