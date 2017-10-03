<?php

namespace ForkBB\Models\Pages;

class Index extends Page
{
    use ForumsTrait;
    use OnlineTrait;

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
        $this->data['online'] = $this->getUsersOnlineInfo();
        $this->data['forums'] = $this->getForumsData();

        $this->canonical = $this->c->Router->link('Index');
        
        return $this;
    }
}
