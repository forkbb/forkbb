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
use ForkBB\Models\PM\Cnst;
use function \ForkBB\__;

class PM extends Page
{
    /**
     * Точка входа для приватных сообщений
     */
    public function action(array $args, string $method): Page
    {
        $second = null;

        if (isset($args['second'])) {
            $second = $args['second'];

            if ('' === \trim($second, '1234567890')) {
                $second = (int) $second;

                if ($second < 2) { // ???? вынести все в роутер?
                    return $this->c->Message->message('Bad request');
                }
            } elseif (
                \strlen($second) < 3
                || '"' !== $second[0]
                || '"' !== $second[-1]
            ) {
                return $this->c->Message->message('Bad request');
            }
        }

        $pms = $this->c->pms->init($second);

        if (
            null !== $second
            && empty($pms->idsCurrent)
            && empty($pms->idsArchive)
        ) {
            return $this->c->Message->message('Not Found', true, 404);
        }

        $this->c->Lang->load('pm');

        $action = $args['action'] ?? ($this->user->u_pm_num_new > 0 ? Cnst::ACTION_NEW : Cnst::ACTION_CURRENT);

        switch ($action) {
            case Cnst::ACTION_NEW:
            case Cnst::ACTION_CURRENT:
            case Cnst::ACTION_ARCHIVE:
                $pms->area = $action;

                return $this->c->PMView->view($args, $method);
            case Cnst::ACTION_SEND:
                $pms->area = Cnst::ACTION_CURRENT;

                return $this->c->PMPost->post($args, $method);
            case Cnst::ACTION_TOPIC:
                return $this->c->PMTopic->topic($args, $method);
            case Cnst::ACTION_POST:
                return $this->c->PMTopic->post($args, $method);
            case Cnst::ACTION_DELETE:
                return $this->c->PMDelete->delete($args, $method);
            case Cnst::ACTION_EDIT:
                return $this->c->PMEdit->edit($args, $method);
            case Cnst::ACTION_BLOCK:
                return $this->c->PMBlock->block($args, $method);
            case Cnst::ACTION_CONFIG:
                return $this->c->PMConfig->config($args, $method);
            default:
                return $this->c->Message->message('Bad request');
        }
    }
}
