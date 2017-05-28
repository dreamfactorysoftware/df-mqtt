<?php

namespace a15lam\MQTT\Services;

use a15lam\MQTT\Components\MosquittoClient;
use a15lam\MQTT\Resources\Pub;
use a15lam\MQTT\Resources\Sub;

class MQTT extends BaseService
{
    /** @type array Service Resources */
    protected static $resources = [
        Pub::RESOURCE_NAME => [
            'name'       => Pub::RESOURCE_NAME,
            'class_name' => Pub::class,
            'label'      => 'Publish'
        ],
        Sub::RESOURCE_NAME => [
            'name'       => Sub::RESOURCE_NAME,
            'class_name' => Sub::class,
            'label'      => 'Subscribe'
        ]
    ];

    /** @inheritdoc */
    public function getResources($only_handlers = false)
    {
        return ($only_handlers) ? static::$resources : array_values(static::$resources);
    }

    /**
     * Sets the client component
     *
     * @param array $config
     */
    protected function setClient($config)
    {
        $host = array_get($config, 'host');
        $port = array_get($config, 'port');
        $clientId = array_get($config, 'client_id', 'df-client-' . time());
        $username = array_get($config, 'username');
        $password = array_get($config, 'password');
        $useTls = array_get($config, 'use_tls');
        $capath = array_get($config, 'capath');
        if (!$useTls) {
            $capath = null;
        }

        $this->client = new MosquittoClient($host, $port, $clientId, $username, $password, $capath);
    }

    /**
     * Returns the client component
     *
     * @return \a15lam\MQTT\Components\MosquittoClient
     */
    public function getClient()
    {
        return $this->client;
    }
}