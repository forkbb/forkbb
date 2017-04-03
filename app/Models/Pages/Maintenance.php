<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Container;

class Maintenance extends Page
{
    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'maintenance';

    /**
     * Позиция для таблицы онлайн текущего пользователя
     * @var null|string
     */
    protected $onlinePos = null;

    /**
     * HTTP статус ответа для данной страницы
     * @var int
     */
    protected $httpStatus = 503;

    /**
     * Конструктор
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
        $this->config = $container->config;
        $container->Lang->load('common', $this->config['o_default_lang']);
    }

    /**
     * Возвращает флаг готовности данных
     * @return bool
     */
    public function isReady()
    {
        return true;
    }

    /**
     * Возвращает данные для шаблона
     * @return array
     */
    public function getData()
    {
        $this->titles[] = __('Maintenance');
        return [
            'maintenanceMessage' => $this->config['o_maintenance_message'],
            'pageTitle' => $this->pageTitle(),
            'pageHeaders' => $this->pageHeaders(),
            'fTitle' => $this->config['o_board_title'],
            'fDescription' => $this->config['o_board_desc'],
            'fNavigation' => null,
            'fIndex' => $this->index,
            'fAnnounce' => null,
            'fRootLink' => $this->c->Router->link('Index'),
            'fIswev' => null,
        ];
    }
}
