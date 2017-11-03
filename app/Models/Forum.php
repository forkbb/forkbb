<?php

namespace ForkBB\Models;

use ForkBB\Models\DataModel;
use ForkBB\Core\Container;

class Forum extends DataModel
{
    /**
     * @param array $attrs
     * 
     * @return Forum
     */
    public function replAtttrs(array $attrs)
    {
        foreach ($attrs as $key => $val) {
            $this->{'__' . $key} = $val; //????
        }
        $modified = array_diff(array_keys($this->modified), array_keys($attrs));
        $this->modified = [];
        foreach ($modified as $key) {
            $this->modified[$key] = true;
        }
        return $this;
    }

    protected function getSubforums()
    {
        $sub = [];
        if (! empty($this->a['subforums'])) {
            foreach ($this->a['subforums'] as $id) {
                $sub[$id] = $this->c->forums->forum($id);
            }
        }
        return $sub;
    }

    protected function getDescendants()
    {
        $all = [];
        if (! empty($this->a['descendants'])) {
            foreach ($this->a['descendants'] as $id) {
                $all[$id] = $this->c->forums->forum($id);
            }
        }
        return $all;
    }

    protected function getParent()
    {
        return $this->c->forums->forum($this->parent_forum_id);
    }
}
