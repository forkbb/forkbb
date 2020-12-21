<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
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
    /**
     * phpinfo
     */
    public function info(): Page
    {
        // Is phpinfo() a disabled function?
        if (false !== \strpos(\strtolower((string) \ini_get('disable_functions')), 'phpinfo')) {
            $this->c->Message->message('PHPinfo disabled message', true, 200);
        }

        \ob_start();
        \phpinfo();
        $page = \ob_get_clean();

        if (\preg_match('%<body[^>]*>(.*)</body[^>]*>%is', $page, $matches)) {
            $phpinfo = $matches[1];
            if (\preg_match('%<style[^>]*>(.*?)</style[^>]*>%is', $page, $matches)) {
                $style = \preg_replace_callback(
                    '%(\S[^{]*)({[^}]+})%',
                    function($match) {
                        $result = \array_map(
                            function($val) {
                                $val = \str_replace('body', '#id-phpinfo-div', $val, $count);

                                return $count ? $val : '#id-phpinfo-div ' . $val;
                            },
                            \explode(',', $match[1])
                        );

                        return \implode(', ', $result) . $match[2];
                    },
                    $matches[1]
                );
                $this->pageHeader('phpinfo', 'style', [$style]);
            }
        } else {
            $phpinfo = '- - -';
        }

        $this->nameTpl    = 'admin/phpinfo';
        $this->mainSuffix = '-one-column';
        $this->aCrumbs[]  = [
            $this->c->Router->link('AdminInfo'),
            'phpinfo()',
        ];
        $this->aCrumbs[]  = [
            $this->c->Router->link('AdminStatistics'),
            __('Server statistics'),
        ];
        $this->phpinfo    = $phpinfo;

        return $this;
    }

    /**
     * Подготавливает данные для шаблона
     */
    public function statistics(): Page
    {
        $this->c->Lang->load('admin_index');

        $this->nameTpl   = 'admin/statistics';
        $this->aCrumbs[] = [
            $this->c->Router->link('AdminStatistics'),
            __('Server statistics'),
        ];
        $this->linkInfo  = $this->c->Router->link('AdminInfo');

        // Get the server load averages (if possible)
        $this->serverLoad = __('Not available');
        switch (\PHP_OS_FAMILY) {
            case 'Windows':
                @\exec('wmic cpu get loadpercentage /all', $output);
                if (
                    ! empty($output)
                    && \preg_match('%(?:^|==)(\d+)(?:$|==)%', \implode('==', $output) , $loadPercentage)
                ) {
                    $this->serverLoad = $loadPercentage[1] . ' %';
                }
                break;
            default:
                if (\function_exists('\\sys_getloadavg')) {
                    $loadAverages     = \sys_getloadavg();
                    $this->serverLoad = num($loadAverages[0], 2)
                        . ' '
                        . num($loadAverages[1], 2)
                        . ' '
                        . num($loadAverages[2], 2);
                    break;
                }

                @\exec('uptime', $output);
                if (
                    ! empty($output)
                    && \preg_match(
                        '%averages?: ([0-9\.]+),?\s+([0-9\.]+),?\s+([0-9\.]+)%i',
                        \implode(' ', $output) ,
                        $loadAverages
                    )
                ) {
                    $this->serverLoad = num($loadAverages[1], 2)
                        . ' '
                        . num($loadAverages[2], 2)
                        . ' '
                        . num($loadAverages[3], 2);
                    break;
                }
        }

        // Get number of current visitors
        $this->numOnline = $this->c->Online->calc($this)->all;

        $stat = $this->c->DB->statistics();
        $this->dbVersion = $stat['db'];
        $this->tSize     = $stat['size'];
        $this->tRecords  = $stat['records'];
        unset($stat['db'], $stat['size'], $stat['records']);
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
        } elseif (
            \ini_get('apc.enabled')
            && \function_exists('\\apc_delete_file')
        ) {
            $this->accelerator = 'Alternative PHP Cache (APC)'; //???? частичная эмуляция APCu
            $this->linkAcc     = 'https://www.php.net/apc/';
        } else {
            $this->accelerator = __('NA');
            $this->linkAcc     = null;
        }

        return $this;
    }
}
