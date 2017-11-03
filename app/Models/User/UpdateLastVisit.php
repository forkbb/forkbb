<?php

namespace ForkBB\Models\User;

use ForkBB\Models\MethodModel;

class UpdateLastVisit extends MethodModel
{
    /**
     * Обновляет время последнего визита для конкретного пользователя
     */
    public function updateLastVisit()
    {
        if ($this->model->isLogged) {
            $this->c->DB->exec('UPDATE ::users SET last_visit=?i:loggid WHERE id=?i:id', [':loggid' => $this->model->logged, ':id' => $this->model->id]);
            $this->model->__last_visit = $this->model->logged;
        }
    }
}
