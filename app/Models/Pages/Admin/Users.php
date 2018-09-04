<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Models\Pages\Admin;

abstract class Users extends Admin
{
    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->aIndex     = 'users';

        $this->c->Lang->load('admin_users');
    }

    /**
     * Кодирует данные фильтра для url
     *
     * @param string|array $data
     *
     * @return string
     */
    protected function encodeData($data)
    {
        if (\is_array($data)) {
            unset($data['token']);
            $data = \base64_encode(\json_encode($data));
            $hash = $this->c->Secury->hash($data);
            return "{$data}:{$hash}";
        } else {
            return "ip:{$data}";
        }
    }

    /**
     * Декодирует данные фильтра из url
     *
     * @param string $data
     *
     * @return mixed
     */
    protected function decodeData($data)
    {
        $data = \explode(':', $data, 2);

        if (2 !== \count($data)) {
            return false;
        }

        if ('ip' === $data[0]) {
            $ip = \filter_var($data[1], \FILTER_VALIDATE_IP);
            return false === $ip ? false : ['ip' => $ip];
        }

        if (! \hash_equals($data[1], $this->c->Secury->hash($data[0]))
            || ! \is_array($data = \json_decode(\base64_decode($data[0], true), true))
        ) {
            return false;
        }

        return $data;
    }
}
