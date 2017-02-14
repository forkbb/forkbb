<?php

namespace ForkBB\Models\Pages\Admin;

class Statistics extends Admin
{
    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'admin/statistics';

    /**
     * Указатель на активный пункт навигации админки
     * @var string
     */
    protected $adminIndex = 'index';

    /**
     * phpinfo
     * @return Page|null
     */
    public function info()
    {
        // Is phpinfo() a disabled function?
        if (strpos(strtolower((string) ini_get('disable_functions')), 'phpinfo') !== false) {
            $this->c->get('Message')->message('PHPinfo disabled message', true, 200);
        }

        phpinfo();
        exit; //????
    }

    /**
     * Подготавливает данные для шаблона
     * @return Page
     */
    public function statistics()
    {
        $this->c->get('Lang')->load('admin_index');
        $this->data = [];
        $this->titles[] = __('Server statistics');
        $this->data['isAdmin'] = $this->c->get('user')['g_id'] == PUN_ADMIN;
        $this->data['linkInfo'] = $this->c->get('Router')->link('AdminInfo');

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
            $this->data['serverLoad'] = isset($ave[2]) ? $ave[0].' '.$ave[1].' '.$ave[2] : __('Not available');
        } elseif (!in_array(PHP_OS, array('WINNT', 'WIN32')) && preg_match('%averages?: ([\d\.]+),?\s+([\d\.]+),?\s+([\d\.]+)%i', @exec('uptime'), $ave)) {
            $this->data['serverLoad'] = $ave[1].' '.$ave[2].' '.$ave[3];
        } else {
            $this->data['serverLoad'] = __('Not available');
        }

        // Get number of current visitors
        $db = $this->c->get('DB');
        $result = $db->query('SELECT COUNT(user_id) FROM '.$db->prefix.'online WHERE idle=0') or error('Unable to fetch online count', __FILE__, __LINE__, $db->error());
        $this->data['numOnline'] = $db->result($result);

        // Collect some additional info about MySQL
        if (in_array($this->c->getParameter('DB_TYPE'), ['mysql', 'mysqli', 'mysql_innodb', 'mysqli_innodb'])) {
            // Calculate total db size/row count
            $result = $db->query('SHOW TABLE STATUS LIKE \''.$db->prefix.'%\'') or error('Unable to fetch table status', __FILE__, __LINE__, $db->error());

            $tRecords = $tSize = 0;
            while ($status = $db->fetch_assoc($result)) {
                $tRecords += $status['Rows'];
                $tSize += $status['Data_length'] + $status['Index_length'];
            }

            $this->data['tSize'] = $this->size($tSize);
            $this->data['tRecords'] = $this->number($tRecords);
        } else {
            $this->data['tSize'] = 0;
            $this->data['tRecords'] = 0;
        }

        // Check for the existence of various PHP opcode caches/optimizers
        if (function_exists('mmcache')) {
            $this->data['accelerator'] = '<a href="http://' . __('Turck MMCache link') . '">' . __('Turck MMCache') . '</a>';
        } elseif (isset($_PHPA)) {
            $this->data['accelerator'] = '<a href="http://' . __('ionCube PHP Accelerator link') . '">' . __('ionCube PHP Accelerator') . '</a>';
        } elseif (ini_get('apc.enabled')) {
            $this->data['accelerator'] ='<a href="http://' . __('Alternative PHP Cache (APC) link') . '">' . __('Alternative PHP Cache (APC)') . '</a>';
        } elseif (ini_get('zend_optimizer.optimization_level')) {
            $this->data['accelerator'] = '<a href="http://' . __('Zend Optimizer link') . '">' . __('Zend Optimizer') . '</a>';
        } elseif (ini_get('eaccelerator.enable')) {
            $this->data['accelerator'] = '<a href="http://' . __('eAccelerator link') . '">' . __('eAccelerator') . '</a>';
        } elseif (ini_get('xcache.cacher')) {
            $this->data['accelerator'] = '<a href="http://' . __('XCache link') . '">' . __('XCache') . '</a>';
        } else {
            $this->data['accelerator'] = __('NA');
        }

        $this->data['dbVersion'] = implode(' ', $db->get_version());

        return $this;
    }
}
