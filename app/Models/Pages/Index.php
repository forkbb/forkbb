<?php

namespace ForkBB\Models\Pages;

class Index extends Page
{
    use ForumsTrait;

    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'index';

    /**
     * Позиция для таблицы онлайн текущего пользователя
     * @var null|string
     */
    protected $onlinePos = 'index';

    /**
     * Тип обработки пользователей онлайн
     * @var bool
     */
    protected $onlineType = true;

    /**
     * Тип возврата данных при onlineType === true
     * Если true, то из online должны вернутся только пользователи находящиеся на этой же странице
     * Если false, то все пользователи online
     * @var bool
     */
    protected $onlineFilter = false;

    /**
     * Подготовка данных для шаблона
     * @return Page
     */
    public function view()
    {
        $this->c->Lang->load('index');
        $this->c->Lang->load('subforums');

        $stats = $this->c->users_info;

        $stmt = $this->c->DB->query('SELECT SUM(num_topics), SUM(num_posts) FROM ::forums');
        list($stats['total_topics'], $stats['total_posts']) = array_map([$this, 'number'], array_map('intval', $stmt->fetch(\PDO::FETCH_NUM)));

        $stats['total_users'] = $this->number($stats['total_users']);

        if ($this->c->user->gViewUsers == '1') {
            $stats['newest_user'] = [
                $this->c->Router->link('User', [
                    'id' => $stats['last_user']['id'],
                    'name' => $stats['last_user']['username'],
                ]),
                $stats['last_user']['username']
            ];
        } else {
            $stats['newest_user'] = $stats['last_user']['username'];
        }
        $this->data['stats'] = $stats;

        // вывод информации об онлайн посетителях
        if ($this->config['o_users_online'] == '1') {
            $this->data['online'] = [];
            $this->data['online']['max'] = $this->number($this->config['st_max_users']);
            $this->data['online']['max_time'] = $this->time($this->config['st_max_users_time']);

            // данные онлайн посетителей
            list($users, $guests, $bots) = $this->c->Online->handle($this);
            $list = [];

            if ($this->c->user->gViewUsers == '1') {
                foreach ($users as $id => $cur) {
                    $list[] = [
                        $this->c->Router->link('User', [
                            'id' => $id,
                            'name' => $cur['name'],
                        ]),
                        $cur['name'],
                    ];
                }
            } else {
                foreach ($users as $cur) {
                    $list[] = $cur['name'];
                }
            }
            $this->data['online']['number_of_users'] = $this->number(count($users));

            $s = 0;
            foreach ($bots as $name => $cur) {
                $count = count($cur);
                $s += $count;
                if ($count > 1) {
                    $list[] = '[Bot] ' . $name . ' (' . $count . ')';
                } else {
                    $list[] = '[Bot] ' . $name;
                }
            }
            $s += count($guests);
            $this->data['online']['number_of_guests'] = $this->number($s);
            $this->data['online']['list'] = $list;
        } else {
            $this->onlineType = false;
            $this->c->Online->handle($this);
            $this->data['online'] = null;
        }
        $this->data['forums'] = $this->getForumsData();
        return $this;
    }
}
