<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Models\Pages\Admin;

class Statistics extends Admin
{
    /**
     * phpinfo
     * 
     * @return Page|null
     */
    public function info()
    {
        // Is phpinfo() a disabled function?
        if (strpos(strtolower((string) ini_get('disable_functions')), 'phpinfo') !== false) {
            $this->c->Message->message('PHPinfo disabled message', true, 200);
        }

        phpinfo();
        exit; //????
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
        $this->titles   = __('Server statistics');
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
            $this->serverLoad = isset($ave[2]) ? $ave[0].' '.$ave[1].' '.$ave[2] : __('Not available');
        } elseif (!in_array(PHP_OS, array('WINNT', 'WIN32')) && preg_match('%averages?: ([\d\.]+),?\s+([\d\.]+),?\s+([\d\.]+)%i', @exec('uptime'), $ave)) {
            $this->serverLoad = $ave[1].' '.$ave[2].' '.$ave[3];
        } else {
            $this->serverLoad = __('Not available');
        }

        // Get number of current visitors
        $this->numOnline = $this->c->Online->calc($this)->all;

        $stat = $this->c->DB->statistics();
        $this->dbVersion = $stat['db'];
        $this->tSize     = $this->size($stat['size']);
        $this->tRecords  = $this->number($stat['records']);
        unset($stat['db'], $stat['size'], $stat['records']);
        $this->tOther    = $stat;

        // Check for the existence of various PHP opcode caches/optimizers
        if (ini_get('opcache.enable') && function_exists('opcache_invalidate')) {
            $this->accelerator = '<a href="https://secure.php.net/opcache/">Zend OPcache</a>';
        } elseif (ini_get('wincache.fcenabled')) {
            $this->accelerator = '<a href="https://secure.php.net/wincache/">Windows Cache for PHP</a>';
        } elseif (ini_get('apc.enabled') && function_exists('apc_delete_file')) {
            $this->accelerator = '<a href="https://secure.php.net/apc/">Alternative PHP Cache (APC)</a>'; //???? частичная эмуляция APCu
        } elseif (ini_get('xcache.cacher')) {
            $this->accelerator = '<a href="https://xcache.lighttpd.net/">XCache</a>';
        } else {
            $this->accelerator = __('NA');
        }

        return $this;
    }
}
