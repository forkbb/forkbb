<?php

namespace ForkBB\Models\Pages;

use R2\DependencyInjection\ContainerInterface;

class Maintenance extends Page
{
    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'maintenance';

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
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->c = $container;
        $this->config = $container->get('config');
        $container->get('Lang')->load('common', $this->config['o_default_lang']);
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
            'MaintenanceMessage' => $this->config['o_maintenance_message'],
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
     * Возврат предупреждений форума
     * @return array
     */
    protected function fWarning()
    {
        return [];
    }
}
