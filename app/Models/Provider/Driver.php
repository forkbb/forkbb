<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Provider;

use ForkBB\Core\Container;
use ForkBB\Models\Model;
use RuntimeException;

abstract class Driver extends Model
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Provider';

    protected string $code;

    protected string $originalName;
    protected string $authURL;
    protected string $tokenURL;
    protected string $userURL;
    protected string $scope;

    public function __construct(protected string $client_id, protected string $client_secret, Container $c)
    {
        parent::__construct($c);
    }

    protected function setname(string $name):void
    {
        if ($this->originalName !== $name) {
            throw new RuntimeException("Invalid name: {$name}");
        }

        $this->setAttr('name', $name);
    }

    protected function getlinkCallback(): string
    {
        return $this->c->Router->link('RegLogCallback', ['name' => $this->name]);
    }

    protected function getclient_id(): string
    {
        return $this->client_id;
    }

    protected function getclient_secret(): string
    {
        return $this->client_secret;
    }

    protected function getlinkAuth(): string
    {
        $params = [
            'response_type' => 'code',
            'scope'         => $this->scope,
            'state'         => $this->c->Csrf->createHash($this->originalName, ['ip' => $this->c->user->ip]),
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->linkCallback,
        ];

        return $this->authURL . '?' . \http_build_query($params);
    }

    protected function verifyState(string $state): bool
    {
        return $this->c->Csrf->verify($state, $this->originalName, ['ip' => $this->c->user->ip]);
    }

    public function verifyAuth(array $data): bool|string|array
    {
        if (! \is_string($data['code'] ?? null)) {
            if (\is_string($data['error'] ?? null)) {
                return ['Provider response: %s', $data['error']];
            } else {
                return ['Provider response: %s', 'undefined'];
            }
        }

        if (
            ! \is_string($data['state'] ?? null)
            || ! $this->verifyState($data['state'])
        ) {
            return 'State error';
        }

        $this->code = $data['code'];

        return true;
    }

    public function reqAccessToken(): bool
    {
        $params = [
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'code'          => $this->code,
            'redirect_uri'  => $this->linkCallback,
        ];

        $ch = \curl_init($this->tokenURL);

        \curl_setopt($ch, \CURLOPT_HTTPHEADER, ['Accept: application/json']);
        \curl_setopt($ch, \CURLOPT_POST, true);
        \curl_setopt($ch, \CURLOPT_POSTFIELDS, \http_build_query($params));
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_HEADER, false);

        $html = \curl_exec($ch);
#        $type = \curl_getinfo($ch, \CURLINFO_CONTENT_TYPE);

        \curl_close($ch);

        if (
            ! isset($html[1])
            || '{' !== $html[0]
            || '}' !== $html[-1]
            || ! \is_array($json = \json_decode($html, true, 20, \JSON_BIGINT_AS_STRING & JSON_UNESCAPED_UNICODE & JSON_INVALID_UTF8_SUBSTITUTE))
            || ! isset($json['access_token'])
        ) {
            return false;
        }

        $this->access_token = $json['access_token'];

        return true;
    }

    public function reqUserInfo(): bool
    {
        $headers = [
            'Accept: application/json',
            "Authorization: Bearer {$this->access_token}",
            'User-Agent: ForkBB (Client ID: {$this->client_id})',
        ];

        $ch = \curl_init($this->userURL);

        \curl_setopt($ch, \CURLOPT_HTTPHEADER, $headers);
        \curl_setopt($ch, \CURLOPT_POST, false);
        #\curl_setopt($ch, \CURLOPT_POSTFIELDS, \http_build_query($params));
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_HEADER, false);

        $html = \curl_exec($ch);
#        $type = \curl_getinfo($ch, \CURLINFO_CONTENT_TYPE);

        \curl_close($ch);

        exit(var_dump("<pre>".$html));
    }
}
