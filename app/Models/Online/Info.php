<?php

namespace ForkBB\Models\Online;

use ForkBB\Models\Method;

class Info extends Method
{
    /**
     * Получение информации об онлайн посетителях
     *
     * @return null|Online
     */
    public function info()
    {
        if (! $this->model->detail) {
            return null;
        }
            
        $this->model->maxNum = $this->c->config->st_max_users;
        $this->model->maxTime = $this->c->config->st_max_users_time;

        $info   = [];
        if ($this->c->user->g_view_users == '1') {
            foreach ($this->model->users as $id => $user) {
                $info[] = [
                    $this->c->Router->link('User', [
                        'id' => $id,
                        'name' => $user['name'],
                    ]),
                    $user['name'],
                ];
            }
        } else {
            foreach ($this->model->users as $user) {
                $info[] = $user['name'];
            }
        }
        $this->model->numUsers = count($info);

        $s = 0;
        foreach ($this->model->bots as $bot => $arr) {
            $count = count($arr);
            $s += $count;
            if ($count > 1) {
                $info[] = '[Bot] ' . $bot . ' (' . $count . ')';
            } else {
                $info[] = '[Bot] ' . $bot;
            }
        }
        $this->model->numGuests = $s + count($this->model->guests);
        $this->model->info      = $info;

        return $this->model;
    }
}
