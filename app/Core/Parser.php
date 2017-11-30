<?php

namespace ForkBB\Core;

use Parserus;

class Parser extends Parserus
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Конструктор
     *
     * @param int $flag
     * @param Container $container
     */
    public function __construct($flag, Container $container)
    {
        $this->c = $container;
        parent::__construct($flag);
        $this->init();
    }

    /**
     * Инициализация данных
     */
    protected function init()
    {
        $bbcodes = include $this->c->DIR_CONFIG . '/defaultBBCode.php';
        $this->setBBCodes($bbcodes);

        if ($this->c->user->show_smilies == '1'
            && ($this->c->config->o_smilies_sig == '1' || $this->c->config->o_smilies == '1')
        ) {
            $smilies = $this->c->smilies->list; //????

            foreach ($smilies as &$cur) {
                $cur = $this->c->PUBLIC_URL . '/img/sm/' . $cur;
            }
            unset($cur);

            $info = $this->c->BBCODE_INFO;

            $this->setSmilies($smilies)->setSmTpl($info['smTpl'], $info['smTplTag'], $info['smTplBl']);
        }
    }

    /**
     * Метод добавляет один bb-код
     *
     * @param array $bb
     *
     * @return Parser
     */
    public function addBBCode(array $bb)
    {
        if ($bb['tag'] == 'quote') {
            $bb['self nesting'] = (int) $this->c->config->o_quote_depth;
        }
        return parent::addBBCode($bb);
    }
}
