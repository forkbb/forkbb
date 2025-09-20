<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use function \ForkBB\{__, num};

class Statistics extends Admin
{
    const CACHE_KEY = 'phpinfoCSS';

    /**
     * phpinfo
     */
    public function info(): Page
    {
        $this->c->Lang->load('admin_index');

        // Is phpinfo() a disabled function?
        if (! \function_exists('\\phpinfo')) {
            return $this->c->Message->message('PHPinfo disabled message', true, 200);
        }

        \ob_start();
        \phpinfo();
        $page = \ob_get_clean();

        if (\preg_match('%<body[^>]*>(.*)</body[^>]*>%is', $page, $matches)) {
            $phpinfo = $matches[1];

            if (\preg_match('%<style[^>]*>(.*?)</style[^>]*>%is', $page, $matches)) {
                $style = \preg_replace_callback(
                    '%(\S[^{]*)({[^}]+})%',
                    function ($match) {
                        $result = \array_map(
                            function ($val) {
                                $val = \str_replace('body', '#id-phpinfo-div', $val, $count);

                                return $count ? $val : '#id-phpinfo-div ' . $val;
                            },
                            \explode(',', $match[1])
                        );

                        return \implode(', ', $result) . $match[2];
                    },
                    $matches[1]
                );

                $this->c->Cache->set(self::CACHE_KEY, $style);
                $this->pageHeader('phpinfoStyle', 'link', 0, [
                    'rel'  => 'stylesheet',
                    'type' => 'text/css',
                    'href' => $this->c->Router->link('AdminInfoCSS', ['time' => \time()] ),
                ]);
            }

        } else {
            $phpinfo = '- - -';
        }

        $this->nameTpl    = 'admin/phpinfo';
        $this->mainSuffix = '-one-column';
        $this->aCrumbs[]  = [$this->c->Router->link('AdminInfo'), ['%s', 'phpinfo()']];
        $this->aCrumbs[]  = [$this->c->Router->link('AdminStatistics'), 'Server statistics'];
        $this->phpinfo    = $phpinfo;

        return $this;
    }

    /**
     * Возврат css из phpinfo() как файл
     */
    public function infoCSS(): Page
    {
        $this->nameTpl      = 'layouts/plain';
        $this->plainText    = $this->c->Cache->get(self::CACHE_KEY, '');
        $this->onlinePos    = null;
        $this->onlineDetail = null;
        $this->c->DEBUG     = 0;

        $this->header('Content-type', 'text/css; charset=utf-8');

        return $this;
    }

    /**
     * Подготавливает данные для шаблона
     */
    public function statistics(): Page
    {
        $this->c->Lang->load('admin_index');

        $this->nameTpl   = 'admin/statistics';
        $this->aCrumbs[] = [$this->c->Router->link('AdminStatistics'), 'Server statistics'];
        $this->linkInfo  = $this->c->Router->link('AdminInfo');

        // Get the server load averages (if possible)
        $this->serverLoad = __('Not available');

        switch (\PHP_OS_FAMILY) {
            case 'Windows':
                if (\function_exists('\\exec')) {
                    \exec('wmic cpu get loadpercentage /all', $output);

                    if (
                        \is_array($output)
                        && \preg_match('%(?:^|==)(\d+)(?:$|==)%', \implode('==', $output), $load)
                    ) {
                        $this->serverLoad = $load[1] . ' %';
                    }
                }

                break;
            default:
                if (
                    \function_exists('\\sys_getloadavg')
                    && \is_array($load = \sys_getloadavg())
                ) {
                    $this->serverLoad = num($load[0], 2) . ' ' . num($load[1], 2) . ' ' . num($load[2], 2);

                } elseif (\function_exists('\\exec')) {
                    \exec('uptime', $output);

                    if (
                        \is_array($output)
                        && \preg_match(
                            '%averages?: ([0-9\.]+),?\s+([0-9\.]+),?\s+([0-9\.]+)%i',
                            \implode(' ', $output),
                            $load
                        )
                    ) {
                        $this->serverLoad = num($load[1], 2) . ' ' . num($load[2], 2) . ' ' . num($load[3], 2);
                    }
                }

                break;
        }

        // Get number of current visitors
        $this->onlineDetail = false;
        $this->numOnline    = $this->c->Online->calc($this)->all;

        $stat = $this->c->DB->statistics();

        $this->dbVersion = $stat['db'];
        $this->tSize     = $stat['size'];
        $this->tRecords  = $stat['records'];
        $this->tTables   = $stat['tables'];

        unset($stat['db'], $stat['size'], $stat['records'], $stat['tables']);

        $this->tOther    = $stat;

        // Check for the existence of various PHP opcode caches/optimizers
        if (
            \ini_get('opcache.enable')
            && \function_exists('\\opcache_invalidate')
        ) {
            $this->accelerator = 'Zend OPcache';
            $this->linkAcc     = 'https://www.php.net/opcache/';

        } elseif (\ini_get('wincache.fcenabled')) {
            $this->accelerator = 'Windows Cache for PHP';
            $this->linkAcc     = 'https://www.php.net/wincache/';

        } else {
            $this->accelerator = __('NA');
            $this->linkAcc     = null;
        }

        return $this;
    }
}
