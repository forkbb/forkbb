<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Models\Pages\Admin;

class Statistics extends Admin
{
    /**
     * phpinfo
     * 
     * @return Page
     */
    public function info()
    {
        // Is phpinfo() a disabled function?
        if (strpos(strtolower((string) ini_get('disable_functions')), 'phpinfo') !== false) {
            $this->c->Message->message('PHPinfo disabled message', true, 200);
        }

        ob_start();
        phpinfo();
        $page = ob_get_clean();
        
        if (preg_match('%<body[^>]*>(.*)</body[^>]*>%is', $page, $matches)) {
            $phpinfo = $matches[1];
            if (preg_match('%<style[^>]*>(.*?)</style[^>]*>%is', $page, $matches)) {
                $style = preg_replace_callback(
                    '%(\S[^{]*)({[^}]+})%', 
                    function($match) {
                        $result = array_map(
                            function($val) {
                                $val = str_replace('body', '.f-phpinfo-div', $val, $count);
                                return $count ? $val : '.f-phpinfo-div ' . $val;
                            }, 
                            explode(',', $match[1])
                        );
                        return implode(', ', $result) . $match[2];
                    }, 
                    $matches[1]
                );
                $this->addStyle('phpinfo', $style);
            }
        } else {
            $phpinfo = '- - -';
        }

        $this->nameTpl = 'admin/phpinfo';
        $this->titles  = 'phpinfo()';
        $this->phpinfo = $phpinfo;
        
        return $this;
    }

    /**
     * Подготавливает данные для шаблона
     * 
     * @return Page
     */
    public function statistics()
    {
        $this->c->Lang->load('admin_index');

        $this->nameTpl  = 'admin/statistics';
        $this->titles   = \ForkBB\__('Server statistics');
        $this->isAdmin  = $this->c->user->isAdmin;
        $this->linkInfo = $this->c->Router->link('AdminInfo');

        // Get the server load averages (if possible)
        if (@file_exists('/proc/loadavg') && is_readable('/proc/loadavg')) {
            // We use @ just in case
            $fh = @fopen('/proc/loadavg', 'r');
            $ave = @fread($fh, 64);
            @fclose($fh);

            if (($fh = @fopen('/proc/loadavg', 'r'))) {
                $ave = fread($fh, 64);
                fclose($fh);
            } else {
                $ave = '';
            }

            $ave = @explode(' ', $ave);
            $this->serverLoad = isset($ave[2]) ? $ave[0].' '.$ave[1].' '.$ave[2] : \ForkBB\__('Not available');
        } elseif (!in_array(PHP_OS, array('WINNT', 'WIN32')) && preg_match('%averages?: ([\d\.]+),?\s+([\d\.]+),?\s+([\d\.]+)%i', @exec('uptime'), $ave)) {
            $this->serverLoad = $ave[1].' '.$ave[2].' '.$ave[3];
        } else {
            $this->serverLoad = \ForkBB\__('Not available');
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
        if (ini_get('opcache.enable') && function_exists('opcache_invalidate')) {
            $this->accelerator = 'Zend OPcache';
            $this->linkAcc     = 'https://secure.php.net/opcache/';
        } elseif (ini_get('wincache.fcenabled')) {
            $this->accelerator = 'Windows Cache for PHP';
            $this->linkAcc     = 'https://secure.php.net/wincache/';
        } elseif (ini_get('apc.enabled') && function_exists('apc_delete_file')) {
            $this->accelerator = 'Alternative PHP Cache (APC)'; //???? частичная эмуляция APCu
            $this->linkAcc     = 'https://secure.php.net/apc/';
        } elseif (ini_get('xcache.cacher')) {
            $this->accelerator = 'XCache';
            $this->linkAcc     = 'https://xcache.lighttpd.net/';
        } else {
            $this->accelerator = \ForkBB\__('NA');
            $this->linkAcc     = null;
        }

        return $this;
    }
}
