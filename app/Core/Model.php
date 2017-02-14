<?php

namespace ForkBB\Core;

use \ArrayObject;
use \ArrayIterator;
use \InvalidArgumentException;

class Model extends ArrayObject
{
    /**
     * @var array
     */
    protected $master;

    /**
     * @var array
     */
    protected $current;

    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->master = $data;
        $this->current = $data;
    }

    /**
     * @param int|string $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        $this->verifyKey($key);
        if (isset($this->current[$key])) {
            return $this->current[$key];
        }
    }

    /**
     * @param int|string $key
     * @param mixed @value
     */
    public function offsetSet($key, $value)
    {
        $this->verifyKey($key, true);
        if (null === $key) {
            $this->current[] = $value;
        } else {
            $this->current[$key] = $value;
        }
    }

    /**
     * @param int|string $key
     */
    public function offsetUnset($key)
    {
        $this->verifyKey($key);
        unset($this->current[$key]);
    }

    /**
     * @param int|string $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        $this->verifyKey($key);
        return isset($this->current[$key]) || array_key_exists($key, $this->current);
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->current);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->current);
    }

    /**
     * @param mixed $value
     */
    public function append($value)
    {
        $this->current[] = $value;
    }

    /**
     * @return array
     */
    public function export()
    {
        return $this->current;
    }

    /**
     * @return bool
     */
    public function isModify()
    {
        return $this->master !== $this->current;
    }

    /**
     * @param mixed $key
     * @param bool $allowedNull
     *
     * @throw InvalidArgumentException
     */
    protected function verifyKey($key, $allowedNull = false)
    {
        if (is_string($key)
            || is_int($key)
            || ($allowedNull && null === $key)
        ) {
            return;
        }
        throw new InvalidArgumentException('Key should be string or integer');
    }
}
