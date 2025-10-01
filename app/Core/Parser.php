<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
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
        $this->setAttr(
            'hashtagLink',
            1 === $this->c->user->g_search ? $this->c->Router->link('Search', ['keywords' => 'HASHTAG']) : null
        );
        $this->setAttr('user', $this->c->user);
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

        // создание хэштегов
        $this->detect('hashtag', '%(?<=^|\s|\n|\r)#(?=[\p{L}\p{N}_]{3})[\p{L}\p{N}]+(?:_+[\p{L}\p{N}]+)*(?=$|\s|\n|\r|\.|,)%u', true);

        return \preg_replace('%^(\x20*\n)+|(\n\x20*)+$%D', '', $this->getCode());
    }

    /**
     * Преобразует бб-коды в html в сообщениях
     */
    public function parseMessage(?string $text = null, bool $hideSmilies = false): string
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
     * Удаляет из сообщения $text теги $remove для цитирования
     * $remove = ['имя тега' => 'текст замены', ...]
     */
    public function prepareToQuote(string $text, array $remove = []): string
    {
        $whiteList = 1 === $this->c->config->b_message_bbcode ? null : [];
        $blackList = $this->c->config->a_bb_black_mes;

        $this->setAttr('isSign', false)
            ->setWhiteList($whiteList)
            ->setBlackList($blackList)
            ->parse($text);

        if ($remove) {
            $arr = $this->getIds(...(\array_keys($remove)));

            if ($arr) {
                foreach ($arr as $id => $name) {
                    $this->data[$id]['text'] = $remove[$name];
                    $this->data[$id]['tag'] = null;
                }
            }
        }

        return \preg_replace('%^(\x20*\n)+|(\n\x20*)+$%D', '', $this->getCode());
    }

    /**
     * Преобразует бб-коды в html в подписях пользователей
     */
    public function parseSignature(?string $text = null): string
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

    /**
     * Флаг использования встроенных стилей
     */
    protected bool $flagInlneStyle = false;

    /**
     * Устанавливает/возвращает флаг использования встроенных стилей в ббкодах
     * (обработчик ббкода должен вызвать этот метод со значением true)
     */
    public function inlineStyle(?bool $flag = null): bool
    {
        $prev = $this->flagInlneStyle;

        if (true === $flag) {
            $this->flagInlneStyle = $flag;
        }

        return $prev;
    }

    /**
     * Создает строку идентификатора на основе текста
     */
    public function createIdentifier(string $text): string
    {
        $text = \preg_replace('%^\s+|\s+$%uD', '', $text);
        $text = \preg_replace('%[^\p{L}\p{N}-]+%u', '_', $text);

        if (\mb_strlen($text, 'UTF-8') > 80) {
            $text = \mb_substr($text, 0, 80, 'UTF-8');
        }

        $text  = \trim($text, '-_');
        $first = \mb_substr($text, 0, 1, 'UTF-8');
        $other = \mb_substr($text, 1, null, 'UTF-8');

        return \mb_strtoupper($first, 'UTF-8') . $other;
    }

    /**
     * Просматривает сам элемент $id и всех его родителей на совпадение имени тега с $tag
     * Если совпадение найдено, вернёт номер тега в $this->data
     * Если совпадение не найдено, вернёт -1
     */
    public function closestId(string $tag, int $id): int
    {
        do {
            $cur = $this->data[$id];

            if ($tag === $cur['tag']) {
                return $id;
            }

            $id = $cur['parent'];
        } while (\is_int($id));

        return -1;
    }

    /**
     * Очищает HTML
     */
    public function purifyHTML(string $html): string
    {
        $errors = [];
        $result = $this->c->HTMLCleaner->setConfig()->parse($html, $errors);

        return empty($errors) ? $result : 'Bad HTML';
    }
}
