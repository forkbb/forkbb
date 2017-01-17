<?php

namespace ForkBB\Models\Actions;

//use ForkBB\Core\DB;

class CacheGenerator
{
    /**
     * @var ForkBB\Core\DB
     */
    protected $db;

    /**
     * Конструктор
     *
     * @param ForkBB\Core\DB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Возвращает массив конфигурации форума
     *
     * @return array
     */
    public function config()
    {
        // Get the forum config from the DB
        $result = $this->db->query('SELECT * FROM '.$this->db->prefix.'config', true) or error('Unable to fetch forum config', __FILE__, __LINE__, $this->db->error());
        $arr = [];
        while ($cur = $this->db->fetch_row($result)) {
            $arr[$cur[0]] = $cur[1];
        }
        $this->db->free_result($result);
        return $arr;
    }

    /**
     * Возвращает массив банов
     *
     * @return array
     */
    public function bans()
    {
        // Get the ban list from the DB
        $result = $this->db->query('SELECT * FROM '.$this->db->prefix.'bans', true) or error('Unable to fetch ban list', __FILE__, __LINE__, $this->db->error());
        $arr = [];
        while ($cur = $this->db->fetch_assoc($result)) {
            $arr[] = $cur;
        }
        $this->db->free_result($result);
        return $arr;
    }

    /**
     * Возвращает массив слов попадающий под цензуру
     *
     * @return array
     */
    public function censoring()
    {
        $result = $this->db->query('SELECT search_for, replace_with FROM '.$this->db->prefix.'censoring') or error('Unable to fetch censoring list', __FILE__, __LINE__, $this->db->error());
        $num_words = $this->db->num_rows($result);

        $search_for = $replace_with = [];
        for ($i = 0; $i < $num_words; $i++) {
            list($search_for[$i], $replace_with[$i]) = $this->db->fetch_row($result);
            $search_for[$i] = '%(?<=[^\p{L}\p{N}])('.str_replace('\*', '[\p{L}\p{N}]*?', preg_quote($search_for[$i], '%')).')(?=[^\p{L}\p{N}])%iu';
        }
        $this->db->free_result($result);

        return [
            'search_for' => $search_for,
            'replace_with' => $replace_with
        ];
    }

    /**
     * Возвращает информацию о последнем зарегистрированном пользователе и
     * общем числе пользователей
     *
     * @return array
     */
    public function usersInfo()
    {
        $stats = [];

        $result = $this->db->query('SELECT COUNT(id)-1 FROM '.$this->db->prefix.'users WHERE group_id!='.PUN_UNVERIFIED) or error('Unable to fetch total user count', __FILE__, __LINE__, $this->db->error());
        $stats['total_users'] = $this->db->result($result);

        $result = $this->db->query('SELECT id, username FROM '.$this->db->prefix.'users WHERE group_id!='.PUN_UNVERIFIED.' ORDER BY registered DESC LIMIT 1') or error('Unable to fetch newest registered user', __FILE__, __LINE__, $this->db->error());
        $stats['last_user'] = $this->db->fetch_assoc($result);

        return $stats;
    }

    /**
     * Возвращает спимок id админов
     *
     * @return array
     */
    public function admins()
    {
        // Get admins from the DB
        $result = $this->db->query('SELECT id FROM '.$this->db->prefix.'users WHERE group_id='.PUN_ADMIN) or error('Unable to fetch users info', __FILE__, __LINE__, $this->db->error());
        $arr = [];
        while ($row = $this->db->fetch_row($result)) {
            $arr[] = $row[0];
        }
        $this->db->free_result($result);

        return $arr;
    }

    /**
     * Возвращает массив с описанием смайлов
     *
     * @return array
     */
    public function smilies()
    {
        $arr = [];
        $result = $this->db->query('SELECT text, image FROM '.$this->db->prefix.'smilies ORDER BY disp_position') or error('Unable to retrieve smilies', __FILE__, __LINE__, $this->db->error());
        while ($cur = $this->db->fetch_assoc($result)) {
            $arr[$cur['text']] = $cur['image'];
        }
        $this->db->free_result($result);

        return $arr;
    }
}
