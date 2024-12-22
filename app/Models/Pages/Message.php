<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;

class Message extends Page
{
    /**
     * Флаг для отключения записи данных в таблицу ::online
     * и невыполнения метода prepare() страницы
     */
    protected bool $noOnlinePos = false;

    /**
     * Подготавливает данные для шаблона
     */
    public function message(string|array $message, bool $back = true, int $status = 400, array $headers = [], bool $noOnlinePos = false): Page
    {
        $this->identifier   = 'message';
        $this->nameTpl      = 'message';
        $this->noOnlinePos  = $noOnlinePos;
        $this->onlinePos    = $noOnlinePos ? null : 'info-' . $status;
        $this->onlineDetail = null;
        $this->httpStatus   = \max(200, $status);
        $this->titles       = 'Info';
        $this->back         = $back;

        if (! empty($headers)) {
            foreach ($headers as $header) {
                $this->header($header[0], $header[1], $header[2] ?? true);
            }
        }

        if ($status < 200) {
            $type = FORK_MESS_INFO;

        } elseif ($status < 300) {
            $type = FORK_MESS_SUCC;

        } elseif ($status < 400) {
            $type = FORK_MESS_WARN;

        } else {
            $type = FORK_MESS_ERR;
        }

        if (
            '' === $message
            && empty($this->fIswev)
        ) {
            $message = 'Empty message';
        }

        if ('' !== $message) {
            $this->fIswev = [$type, $message];

            if (
                $status > 399
                && 4 & $this->c->DEBUG
            ) {
                $this->c->Log->debug("Status {$status}: {$_SERVER['REQUEST_URI']}", [
                    'user'    => $this->user->fLog(),
                    'message' => $message,
                    'headers' => true,
                ]);
            }
        }

        return $this;
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare(): void
    {
        if (false === $this->noOnlinePos) {
            parent::prepare();
        }
    }

    /**
     * Задает массивы главной навигации форума
     */
    protected function boardNavigation(): void
    {
        if ($this->c->config->i_fork_revision >= $this->c->FORK_REVISION) {
            parent::boardNavigation();
        }
    }
}
