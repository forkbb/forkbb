<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use Parserus;
use ForkBB\Core\Container;

class Parser extends Parserus
{
    public function __construct(int $flag, protected Container $c)
    {
        parent::__construct($flag);
        $this->init();
    }

    /**
     * Инициализация данных
     */
    protected function init(): void
    {
        if (
            1 === $this->c->config->b_message_bbcode
            || 1 === $this->c->config->b_sig_bbcode
        ) {
            $this->setBBCodes($this->c->bbcode->list);
        }

        if (
            1 === $this->c->user->show_smilies
            && (
                1 === $this->c->config->b_smilies_sig
                || 1 === $this->c->config->b_smilies
            )
        ) {
            $smilies = [];

            foreach ($this->c->smilies->list as $cur) {
                $smilies[$cur['sm_code']] = $this->c->PUBLIC_URL . '/img/sm/' . $cur['sm_image'];
            }

            $info = $this->c->BBCODE_INFO;

            $this->setSmilies($smilies)->setSmTpl($info['smTpl'], $info['smTplTag'], $info['smTplBl']);
        }

        $this->setAttr('baseUrl', $this->c->BASE_URL);
        $this->setAttr('showImg', 1 === $this->c->user->show_img);
        $this->setAttr('showImgSign', 1 === $this->c->user->show_img_sig);
    }

    /**
     * Проверяет разметку сообщения с бб-кодами
     * Пытается исправить неточности разметки
     * Генерирует ошибки разметки
     */
    public function prepare(string $text, bool $isSignature = false): string
    {
        if ($isSignature) {
            $whiteList = 1 === $this->c->config->b_sig_bbcode
                ? (empty($this->c->config->a_bb_white_sig) && empty($this->c->config->a_bb_black_sig)
                    ? null
                    : $this->c->config->a_bb_white_sig
                )
                : [];
            //$blackList = null;
        } else {
            $whiteList = 1 === $this->c->config->b_message_bbcode
                ? (empty($this->c->config->a_bb_white_mes) && empty($this->c->config->a_bb_black_mes)
                    ? null
                    : $this->c->config->a_bb_white_mes
                )
                : [];
            //$blackList = null;
        }

        $blackList = 1 === $this->c->user->g_post_links ? null : ['email', 'url', 'img'];

        $this->setAttr('isSign', $isSignature)
             ->setWhiteList($whiteList)
             ->setBlackList($blackList)
             ->parse($text, ['strict' => true])
             ->stripEmptyTags(" \n\t\r\v", true);

        if (1 === $this->c->config->b_make_links) {
            $this->detectUrls();
        }

        return \preg_replace('%^(\x20*\n)+|(\n\x20*)+$%D', '', $this->getCode());
    }

    /**
     * Преобразует бб-коды в html в сообщениях
     */
    public function parseMessage(string $text = null, bool $hideSmilies = false): string
    {
        // при null предполагается брать данные после prepare()
        if (null !== $text) {
            $whiteList = 1 === $this->c->config->b_message_bbcode ? null : [];
            $blackList = $this->c->config->a_bb_black_mes;

            $this->setAttr('isSign', false)
                 ->setWhiteList($whiteList)
                 ->setBlackList($blackList)
                 ->parse($text);
        }

        if (
            ! $hideSmilies
            && 1 === $this->c->config->b_smilies
        ) {
            $this->detectSmilies();
        }

        return $this->getHtml();
    }

    /**
     * Преобразует бб-коды в html в подписях пользователей
     */
    public function parseSignature(string $text = null): string
    {
        // при null предполагается брать данные после prepare()
        if (null !== $text) {
            $whiteList = 1 === $this->c->config->b_sig_bbcode ? null : [];
            $blackList = $this->c->config->a_bb_black_sig;

            $this->setAttr('isSign', true)
                 ->setWhiteList($whiteList)
                 ->setBlackList($blackList)
                 ->parse($text);
        }

        if (1 === $this->c->config->b_smilies_sig) {
            $this->detectSmilies();
        }

        return $this->getHtml();
    }
}
