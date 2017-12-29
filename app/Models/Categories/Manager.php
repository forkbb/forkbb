<?php

namespace ForkBB\Models\Categories;

use ForkBB\Models\ManagerModel;
//use ForkBB\Models\Categories\Model as Categories;
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
     * 
     * @return Manager
     */
    public function init()
    {
        $sql = 'SELECT id, cat_name, disp_position 
                FROM ::categories 
                ORDER BY disp_position';
        $this->repository = $this->c->DB->query($sql)->fetchAll(\PDO::FETCH_UNIQUE);
        return $this;
    }

    public function getList()
    {
        return $this->repository;
    }

    public function set($key, $value)
    {
        if (! isset($value['cat_name']) || ! isset($value['disp_position'])) {
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
    }

    public function update()
    {
        foreach ($this->modified as $key => $value) {
            $cat = $this->get($key);
            $vars = [
                ':name'     => $cat['cat_name'],
                ':position' => $cat['disp_position'],
                ':cid'      => $key,
            ];
            $sql = 'UPDATE ::categories 
                    SET cat_name=?s:name, disp_position=?i:position 
                    WHERE id=?i:cid';
            $this->c->DB->query($sql, $vars); //????
        }
        $this->modified = [];

        return $this;
    }

    public function insert($name)
    {
        $pos = 0;
        foreach ($this->repository as $cat) {
            if ($cat['disp_position'] > $pos) {
                $pos = $cat['disp_position'];
            }
        }
        ++$pos;

        $vars = [
            ':name'     => $name,
            ':position' => $pos,
        ];
        $sql = 'INSERT INTO ::categories (cat_name, disp_position) 
                VALUES (?s:name, ?i:position)';
        $this->c->DB->query($sql, $vars);
        
        $cid = $this->c->DB->lastInsertId();

        parent::set($cid, ['cat_name' => $name, 'disp_position' => $pos]);

        return $cid;
    }
}
