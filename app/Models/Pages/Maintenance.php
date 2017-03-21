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
     * Подготовленные данные для шаблона
     * @var array
     */
    protected $data = [];

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
     * Возвращает данные для шаблона
     * @return array
     */
    public function getData()
    {
        $this->titles = [
            __('Maintenance'),
        ];
        $this->data = [
            'maintenanceMessage' => $this->config['o_maintenance_message'],
        ];
        return parent::getData();
    }

    /**
     * Возвращает массив ссылок с описанием для построения навигации
     * @return array
     */
    protected function fNavigation()
    {
        return [];
    }

    /**
     * Возврат info, success, warning, error, validation информации
     * @return array
     */
    protected function getIswev()
    {
        return [];
    }
}
