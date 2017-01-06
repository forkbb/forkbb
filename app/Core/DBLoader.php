<?php

namespace ForkBB\Core;

class DBLoader
{
    protected $host;
    protected $username;
    protected $password;
    protected $name;
    protected $prefix;
    protected $connect;

    public function __construct($host, $username, $password, $name, $prefix, $connect)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->name = $name;
        $this->prefix = $prefix;
        $this->connect = $connect;
    }

    /**
     * @param string $type
     *
     * @return \ForkBB\Core\DB\DBLayer
     */
    public function load($type)
    {
        switch ($type)
        {
            case 'mysql':
                require_once __DIR__ . '/DB/mysql.php';
                break;

            case 'mysql_innodb':
                require_once __DIR__ . '/DB/mysql_innodb.php';
                break;

            case 'mysqli':
                require_once __DIR__ . '/DB/mysqli.php';
                break;

            case 'mysqli_innodb':
                require_once __DIR__ . '/DB/mysqli_innodb.php';
                break;

            case 'pgsql':
                require_once __DIR__ . '/DB/pgsql.php';
                break;

            case 'sqlite':
                require_once __DIR__ . '/DB/sqlite.php';
                break;

            default:
                error('\''.$type.'\' is not a valid database type. Please check settings', __FILE__, __LINE__);
                break;
        }

        // Create the database adapter object (and open/connect to/select db)
        return new \ForkBB\Core\DB\DBLayer($this->host, $this->username, $this->password, $this->name, $this->prefix, $this->connect);
    }
}
