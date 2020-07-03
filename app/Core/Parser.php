<?php

namespace ForkBB\Core;

use Parserus;
use ForkBB\Core\Container;

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
    protected function init(): void
    {
        if (
            '1' == $this->c->config->p_message_bbcode
            || '1' == $this->c->config->p_sig_bbcode
        ) {
            $bbcodes = include $this->c->DIR_CONFIG . '/defaultBBCode.php';
            $this->setBBCodes($bbcodes);
        }

        if (
            '1' == $this->c->user->show_smilies
            && (
                '1' == $this->c->config->o_smilies_sig
                || '1' == $this->c->config->o_smilies
            )
        ) {
            $smilies = $this->c->smilies->list; //????

            foreach ($smilies as &$cur) {
                $cur = $this->c->PUBLIC_URL . '/img/sm/' . $cur;
            }
            unset($cur);

            $info = $this->c->BBCODE_INFO;

            $this->setSmilies($smilies)->setSmTpl($info['smTpl'], $info['smTplTag'], $info['smTplBl']);
        }

        $this->setAttr('baseUrl', $this->c->BASE_URL);
        $this->setAttr('showImg', '0' != $this->c->user->show_img);
        $this->setAttr('showImgSign', '0' != $this->c->user->show_img_sig);
    }

    /**
     * Метод добавляет один bb-код
     *
     * @param array $bb
     *
     * @return Parser
     */
    public function addBBCode(array $bb): self
    {
        if ('quote' == $bb['tag']) {
            $bb['self nesting'] = (int) $this->c->config->o_quote_depth;
        }

        return parent::addBBCode($bb);
    }

    /**
     * Проверяет разметку сообщения с бб-кодами
     * Пытается исправить неточности разметки
     * Генерирует ошибки разметки
     *
     * @param string $text
     * @param bool $isSignature
     *
     * @return string
     */
    public function prepare(string $text, bool $isSignature = false): string
    {
        if ($isSignature) {
            $whiteList = '1' == $this->c->config->p_sig_bbcode ? $this->c->BBCODE_INFO['forSign'] : [];
            $blackList = '1' == $this->c->config->p_sig_img_tag ? [] : ['img'];
        } else {
            $whiteList = '1' == $this->c->config->p_message_bbcode ? null : [];
            $blackList = '1' == $this->c->config->p_message_img_tag ? [] : ['img'];
        }

        $this->setAttr('isSign', $isSignature)
             ->setWhiteList($whiteList)
             ->setBlackList($blackList)
             ->parse($text, ['strict' => true])
             ->stripEmptyTags(" \n\t\r\v", true);

        if ('1' == $this->c->config->o_make_links) {
            $this->detectUrls();
        }

        return \preg_replace('%^(\x20*\n)+|(\n\x20*)+$%D', '', $this->getCode());
    }

    /**
     * Преобразует бб-коды в html в сообщениях
     *
     * @param null|string $text
     * @param bool $hideSmilies
     *
     * @return string
     */
    public function parseMessage(string $text = null, bool $hideSmilies = false): string
    {
        // при null предполагается брать данные после prepare()
        if (null !== $text) {
            $whiteList = '1' == $this->c->config->p_message_bbcode ? null : [];
            $blackList = '1' == $this->c->config->p_message_img_tag ? [] : ['img'];

            $this->setAttr('isSign', false)
                 ->setWhiteList($whiteList)
                 ->setBlackList($blackList)
                 ->parse($text);
        }

        if (
            ! $hideSmilies
            && '1' == $this->c->config->o_smilies
        ) {
            $this->detectSmilies();
        }

        return $this->getHtml();
    }

    /**
     * Преобразует бб-коды в html в подписях пользователей
     *
     * @param null|string $text
     *
     * @return string
     */
    public function parseSignature(string $text = null): string
    {
        // при null предполагается брать данные после prepare()
        if (null !== $text) {
            $whiteList = '1' == $this->c->config->p_sig_bbcode ? $this->c->BBCODE_INFO['forSign'] : [];
            $blackList = '1' == $this->c->config->p_sig_img_tag ? [] : ['img'];

            $this->setAttr('isSign', true)
                 ->setWhiteList($whiteList)
                 ->setBlackList($blackList)
                 ->parse($text);
        }

        if ('1' == $this->c->config->o_smilies_sig) {
            $this->detectSmilies();
        }

        return $this->getHtml();
    }
}
